<?php

namespace App\Listeners;

use App\Enums\OfficeId;
use App\Events\WorkflowStepSigned;
use App\Models\Notifications;
use App\Models\Staffusers;
use App\Services\WorkflowService;
use App\Support\WorkflowInbox;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWorkflowNotification implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * The notice goes to the desk that must act next, not to the applicant:
     * students are not notifiable in this application — the bell in
     * NotificationController reads only staff rows — so the student-addressed
     * writes this listener used to make could never be read by anyone.
     */
    public function handle(WorkflowStepSigned $event): void
    {
        $workflow = $event->workflow;
        $step = $event->step;
        $enrollment = $workflow->enrollment;
        $student = $enrollment->student;

        $labels = WorkflowService::stepLabels();
        $stepLabel = $labels[$step->officeId] ?? "Step {$step->stepOrder}";
        $signer = $event->signedBy->firstName.' '.$event->signedBy->lastName;
        $who = $student->lastName.', '.$student->firstName;
        $course = $enrollment->course ? $enrollment->course->courseCode : '—';

        $progress = WorkflowInbox::progress($workflow);

        if ($progress['remaining'] === 0 && $progress['total'] > 0) {
            $this->notify(
                'workflow_completed',
                WorkflowInbox::actors(OfficeId::Registrar->value),
                $enrollment,
                $workflow,
                "{$who} · {$course}: enrollment form complete — all {$progress['total']} desks signed, last by {$signer} ({$stepLabel}). Ready for the records file.",
                ['completed' => true]
            );

            return;
        }

        $next = WorkflowInbox::nextBox($workflow);
        $nextLabel = $next ? ($labels[$next->officeId] ?? 'the next desk') : 'the next desk';

        $this->notify(
            'workflow_step_signed',
            WorkflowInbox::actors($next?->officeId),
            $enrollment,
            $workflow,
            "{$who} · {$course}: '{$stepLabel}' signed by {$signer}. "
                ."Phase {$progress['signed']} of {$progress['total']} — waiting on {$nextLabel}."
                .($progress['last'] ? ' Last desk before the enrollment form closes.' : ''),
            [
                'stepOrder' => $step->stepOrder,
                'stepLabel' => $stepLabel,
                'signedBy' => $signer,
                'signedDate' => $step->signedDate?->toDateTimeString(),
                'phase' => $progress['phase'],
                'signed' => $progress['signed'],
                'total' => $progress['total'],
                'lastDesk' => $progress['last'],
            ]
        );
    }

    /**
     * One row per recipient staff member, because that is the shape the bell
     * queries. Nobody is left waiting on a notification addressed to a reader
     * that does not exist.
     *
     * @param  list<int>  $userIds
     * @param  array<string, mixed>  $extra
     */
    private function notify(string $type, array $userIds, $enrollment, $workflow, string $message, array $extra): void
    {
        foreach ($userIds as $userId) {
            Notifications::create([
                'type' => $type,
                'notifiable_type' => Staffusers::class,
                'notifiable_id' => $userId,
                'data' => array_merge([
                    'message' => $message,
                    'enrollmentId' => $enrollment->enrollmentId,
                    'workflowId' => $workflow->workflowId,
                ], $extra),
            ]);
        }
    }
}
