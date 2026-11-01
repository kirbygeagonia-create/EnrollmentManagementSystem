<?php

namespace App\Listeners;

use App\Enums\OfficeId;
use App\Events\WorkflowStepSigned;
use App\Models\Staffusers;
use App\Services\WorkflowService;
use App\Support\NoticeDelivery;
use App\Support\NoticeFacts;
use App\Support\WorkflowInbox;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWorkflowNotification implements ShouldQueue
{
    // Prompt §8 asks that a notification not be delivered for an act that never took place. This listener is queued
    // and WorkflowStepSigned is fired inside the signature's transaction, so without this a retried request would
    // ring the next desk for a box that was never signed.
    public bool $afterCommit = true;

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

        // The signing is the act, and its timestamp is what separates this signature from the next one on the same
        // box: a return re-opens a step and the second signature must ring a bell of its own (§28.3 G-36). With no
        // signed date there is no act to name, so the key is null and the notice goes out unkeyed rather than lost.
        $signedAt = $step->signedDate?->getTimestamp();
        $stepKey = $signedAt === null ? null : "workflow_step_signed:{$step->workflowStepId}:{$signedAt}";

        if ($progress['remaining'] === 0 && $progress['total'] > 0) {
            $this->notify(
                "workflow_completed:{$workflow->workflowId}:{$step->workflowStepId}",
                'workflow_completed',
                WorkflowInbox::actors(OfficeId::Registrar->value, $enrollment),
                $enrollment,
                $workflow,
                "{$who} · {$course}: enrollment form complete — all {$progress['total']} desks signed, last by {$signer} ({$stepLabel}). Ready for the records file.",
                ['completed' => true] + NoticeFacts::forEnrollment($enrollment, "Enrollment form complete for {$who}", OfficeId::Registrar->value)
            );

            return;
        }

        $next = WorkflowInbox::nextBox($workflow);
        $nextLabel = $next ? ($labels[$next->officeId] ?? 'the next desk') : 'the next desk';

        $this->notify(
            $stepKey,
            'workflow_step_signed',
            WorkflowInbox::actors($next?->officeId, $enrollment),
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
            ] + NoticeFacts::forEnrollment($enrollment, "'{$stepLabel}' signed by {$signer}", $next?->officeId)
        );
    }

    /**
     * One row per recipient staff member, because that is the shape the bell
     * queries. Nobody is left waiting on a notification addressed to a reader
     * that does not exist.
     *
     * The prefix is the ACT's identity — the signing, or the completion, not the notice's text — and the recipient
     * is appended per row so one office ringing four desks is four deliveries rather than one. A null prefix means
     * the caller could not name the act, and then the notice is written unkeyed: G-36 exists to stop a retry
     * duplicating a bell, not to let a missing key lose one.
     *
     * @param  list<int>  $userIds
     * @param  array<string, mixed>  $extra
     */
    private function notify(?string $actKey, string $type, array $userIds, $enrollment, $workflow, string $message, array $extra): void
    {
        foreach ($userIds as $userId) {
            NoticeDelivery::once(
                $actKey === null ? null : "{$actKey}:{$userId}",
                [
                    'type' => $type,
                    'notifiable_type' => Staffusers::class,
                    'notifiable_id' => $userId,
                    'data' => array_merge([
                        'message' => $message,
                        'enrollmentId' => $enrollment->enrollmentId,
                        'workflowId' => $workflow->workflowId,
                    ], $extra),
                ]
            );
        }
    }
}
