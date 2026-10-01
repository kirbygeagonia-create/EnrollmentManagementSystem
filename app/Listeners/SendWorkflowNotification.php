<?php

namespace App\Listeners;

use App\Events\WorkflowStepSigned;
use App\Models\Notifications;
use App\Services\WorkflowService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWorkflowNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(WorkflowStepSigned $event): void
    {
        $workflow = $event->workflow;
        $step = $event->step;
        $enrollment = $workflow->enrollment;
        $student = $enrollment->student;

        $stepLabels = WorkflowService::stepLabels();

        $stepLabel = $stepLabels[$step->officeId] ?? "Step {$step->stepOrder}";
        $message = "Workflow step '{$stepLabel}' has been signed by {$event->signedBy->firstName} {$event->signedBy->lastName}.";

        // Create in-app notification
        Notifications::create([
            'type' => 'workflow_step_signed',
            'notifiable_type' => 'App\Models\Students',
            'notifiable_id' => $student->studentId,
            'data' => [
                'message' => $message,
                'enrollmentId' => $enrollment->enrollmentId,
                'workflowId' => $workflow->workflowId,
                'stepOrder' => $step->stepOrder,
                'stepLabel' => $stepLabel,
                'signedBy' => $event->signedBy->firstName.' '.$event->signedBy->lastName,
                'signedDate' => $step->signedDate->toDateTimeString(),
            ],
        ]);

        // If workflow is completed, send completion notification
        if ($workflow->workflowStatus->value === 'completed') {
            Notifications::create([
                'type' => 'workflow_completed',
                'notifiable_type' => 'App\Models\Students',
                'notifiable_id' => $student->studentId,
                'data' => [
                    'message' => 'Your enrollment workflow is now complete! All steps have been signed.',
                    'enrollmentId' => $enrollment->enrollmentId,
                    'workflowId' => $workflow->workflowId,
                ],
            ]);
        }
    }
}
