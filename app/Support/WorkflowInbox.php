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
        // The two return states are deliberately NOT here: `RETURNED_HANDOFF` below is the one place
        // that answers them, and a second map naming the same desks is how two answers to "whose turn
        // is it" get written. A drop has no next desk, but it does have a custodian: the Registrar
        // keeps the record.
        'dropped' => OfficeId::Registrar,
    ];

    /**
     * The two states that mean "a desk sent this record back", and the desk that owes the answer.
     *
     * Not the same question as `STATUS_HANDOFF`, which is where the record goes *onward* to: a return
     * is read from its destination because the boxes are re-opened after the event fires, so the form
     * still shows the pre-return shape while the notice is being addressed.
     */
    private const RETURNED_HANDOFF = [
        'returnedToEvaluation' => OfficeId::Guidance,
        'returnedToAdmission' => OfficeId::Admission,
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

        // A screen that eager-loaded the boxes reads the same answer from the loaded
        // collection, so the page does not re-query per row. The rule — lowest open step —
        // lives here alone; the monitor used to re-derive it beside the list it filtered,
        // which is one definition of "whose turn is it" per runtime and one drift away.
        if ($workflow->relationLoaded('workflowsteps')) {
            return $workflow->workflowsteps
                ->filter(static fn (Workflowsteps $step) => $step->stepStatus === WorkflowStepStatus::Pending)
                ->sortBy('stepOrder')
                ->first();
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
        // A returned record is answered by its destination, not by the box list. Both listeners run
        // inside the act's own transaction, and `ReturnRecorder` re-opens the boxes only *after* the
        // status transition fires the event — so at that moment the destination's box still reads
        // `completed` and the first pending box is the desk that would have been next had the record
        // moved FORWARD. Measured: a return to Department Evaluation addressed office 3, which on the
        // demo dataset holds no active account, so the record came back and rang nobody. The two
        // return states are the only ones whose notice must beat the boxes.
        $returned = self::RETURNED_HANDOFF[$status] ?? null;

        if ($returned !== null) {
            return $returned->value;
        }

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
        $boxes = $workflow?->relationLoaded('workflowsteps')
            ? $workflow->workflowsteps
            : null;

        if ($boxes !== null) {
            $total = $boxes->count();
            $signed = $boxes->filter(
                static fn (Workflowsteps $step) => $step->stepStatus === WorkflowStepStatus::Completed
            )->count();
        } else {
            $total = $workflow ? $workflow->workflowsteps()->count() : 0;
            $signed = $workflow
                ? $workflow->workflowsteps()->where('stepStatus', WorkflowStepStatus::Completed->value)->count()
                : 0;
        }

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
