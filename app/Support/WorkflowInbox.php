<?php

namespace App\Support;

use App\Enums\OfficeId;
use App\Enums\WorkflowStepStatus;
use App\Models\Enrollmentworkflow;
use App\Models\Staffusers;
use App\Models\Workflowsteps;
use App\Services\WorkflowService;

/**
 * Who has to act on an enrollment next, and what phase the record is in.
 *
 * The two workflow and status listeners used to address every row at
 * App\Models\Students while the only reader in the application — the bell in
 * NotificationController — filters on App\Models\Staffusers. Nothing written was
 * ever readable, and the students who owned those rows have no login, so the
 * notification channel was dead at both ends. This is the single place that
 * resolves a real recipient, so the writers cannot drift from the reader again.
 */
final class WorkflowInbox
{
    /**
     * The desk each enrollment status hands the record to next, for the moments
     * a status changes before any workflow form exists. The order is the one
     * WorkflowService::stepsFor() signs boxes in.
     */
    private const STATUS_HANDOFF = [
        'evaluated' => OfficeId::Scholarship,
        'assessed' => OfficeId::Accounting,
        'paid' => OfficeId::Registrar,
        'enrolled' => OfficeId::Blocking,
        'returnedToEvaluation' => OfficeId::Guidance,
        // A drop has no next desk, but it does have a custodian: the Registrar
        // keeps the record.
        'dropped' => OfficeId::Registrar,
    ];

    /**
     * The box the record is waiting on, or null once every box is signed.
     *
     * Read fresh rather than from the loaded relation: both listeners run inside
     * the request that just signed a box, and a collection loaded before that
     * write would point the notice at the desk that has already signed.
     */
    public static function nextBox(?Enrollmentworkflow $workflow): ?Workflowsteps
    {
        if (! $workflow) {
            return null;
        }

        return $workflow->workflowsteps()
            ->where('stepStatus', WorkflowStepStatus::Pending->value)
            ->orderBy('stepOrder')
            ->first();
    }

    /**
     * The office that must act, or null when the record has no desk left.
     */
    public static function nextOffice(?Enrollmentworkflow $workflow, ?string $status = null): ?int
    {
        $box = self::nextBox($workflow);

        if ($box) {
            return $box->officeId;
        }

        // Boxes exist and none is pending: the form is signed through, so
        // whoever inherits the record is the custodian, not the next desk.
        if ($workflow && $workflow->workflowsteps()->exists()) {
            return null;
        }

        // No workflow form yet — the status says whose turn it is.
        $handoff = $status === null ? null : (self::STATUS_HANDOFF[$status] ?? null);

        return $handoff?->value;
    }

    /**
     * Every active account in that office: the people whose bell should ring.
     *
     * @return list<int>
     */
    public static function actors(?int $officeId): array
    {
        if (! $officeId) {
            return [];
        }

        return Staffusers::where('officeId', $officeId)
            ->where('status', 'active')
            ->pluck('userId')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Where the record actually is, in the vocabulary the stepper prints, so a
     * desk reads "Phase 5 of 7 — Section Blocking" instead of a bare status.
     *
     * @return array{signed: int, total: int, remaining: int, phase: ?string, last: bool}
     */
    public static function progress(?Enrollmentworkflow $workflow): array
    {
        $total = $workflow ? $workflow->workflowsteps()->count() : 0;
        $signed = $workflow
            ? $workflow->workflowsteps()->where('stepStatus', WorkflowStepStatus::Completed->value)->count()
            : 0;
        $next = self::nextBox($workflow);
        $labels = WorkflowService::stepLabels();

        return [
            'signed' => $signed,
            'total' => $total,
            'remaining' => max(0, $total - $signed),
            'phase' => $next ? ($labels[$next->officeId] ?? null) : null,
            'last' => $next !== null && $total > 0 && ($total - $signed) === 1,
        ];
    }
}
