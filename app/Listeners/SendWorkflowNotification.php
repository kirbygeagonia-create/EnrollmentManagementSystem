<?php

namespace App\Listeners;

use App\Events\WorkflowStepSigned;
use App\Models\Notifications;
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

        $stepLabels = [
            1 => 'Clearance Desk Receipt',
            2 => 'Department Evaluation',
            3 => 'Assessment',
            4 => 'Accounting Payment',
            5 => 'Registrar Approval',
            6 => 'Blocking and Scheduling',
            7 => 'Clinic',
            8 => 'ID Office',
        ];

        $stepLabel = $stepLabels[$step->stepOrder] ?? "Step {$step->stepOrder}";
        $message = "Workflow step '{$stepLabel}' has been signed by {$event->signedBy->firstName} {$event->signedBy->lastName}.";

        // Create in-app notification
        Notifications::create([
            'type' => 'workflow_step_signed',
            'notifiableType' => 'App\Models\Students',
            'notifiableId' => $student->studentId,
            'data' => json_encode([
                'message' => $message,
                'enrollmentId' => $enrollment->enrollmentId,
                'workflowId' => $workflow->workflowId,
                'stepOrder' => $step->stepOrder,
                'stepLabel' => $stepLabel,
                'signedBy' => $event->signedBy->firstName . ' ' . $event->signedBy->lastName,
                'signedDate' => $step->signedDate->toDateTimeString(),
            ]),
            'createdAt' => now(),
        ]);

        // If workflow is completed, send completion notification
        if ($workflow->workflowStatus->value === 'completed') {
            Notifications::create([
                'type' => 'workflow_completed',
                'notifiableType' => 'App\Models\Students',
                'notifiableId' => $student->studentId,
                'data' => json_encode([
                    'message' => "Your enrollment workflow is now complete! All steps have been signed.",
                    'enrollmentId' => $enrollment->enrollmentId,
                    'workflowId' => $workflow->workflowId,
                ]),
                'createdAt' => now(),
            ]);
        }
    }
}