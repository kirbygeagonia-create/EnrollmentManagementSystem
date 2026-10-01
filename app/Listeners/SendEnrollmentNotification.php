<?php

namespace App\Listeners;

use App\Events\EnrollmentStatusChanged;
use App\Models\Notifications;
use App\Models\Staffusers;
use App\Support\WorkflowInbox;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendEnrollmentNotification implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * Addressed to the desk that inherits the record, because that is the only
     * reader the application has: the bell filters on Staffusers, and the
     * student rows this listener used to write were never readable by anyone.
     */
    public function handle(EnrollmentStatusChanged $event): void
    {
        $enrollment = $event->enrollment;
        $student = $enrollment->student;
        $workflow = $enrollment->enrollmentworkflow;

        $lead = match ($event->toStatus) {
            'evaluated' => 'evaluation signed — the Assessment desk computes the fees next',
            'assessed' => 'fees computed — Accounting collects next',
            'paid' => 'payment settled — the Registrar approves next',
            'enrolled' => 'approved and enrolled — Section Blocking picks the cohort up',
            'dropped' => 'enrollment dropped',
            'returnedToEvaluation' => 'the Registrar returned this record to Department Evaluation'
                .($event->remarks ? ": {$event->remarks}" : '.'),
            default => "status moved from {$event->fromStatus} to {$event->toStatus}",
        };

        $progress = WorkflowInbox::progress($workflow);
        $phaseNote = $progress['total'] === 0
            ? ''
            : ($progress['remaining'] === 0
                ? " All {$progress['total']} desks have signed."
                : " Phase {$progress['signed']} of {$progress['total']}.");

        $course = $enrollment->course ? $enrollment->course->courseCode : '—';
        $message = "{$student->lastName}, {$student->firstName} · {$course}: {$lead}.{$phaseNote}";

        $data = [
            'message' => $message,
            'enrollmentId' => $enrollment->enrollmentId,
            'fromStatus' => $event->fromStatus,
            'toStatus' => $event->toStatus,
            'changedBy' => $event->changedBy?->firstName.' '.$event->changedBy?->lastName,
            'remarks' => $event->remarks,
            'phase' => $progress['phase'],
            'signed' => $progress['signed'],
            'total' => $progress['total'],
        ];

        foreach (WorkflowInbox::actors(WorkflowInbox::nextOffice($workflow, $event->toStatus)) as $userId) {
            Notifications::create([
                'type' => 'enrollment_status_changed',
                'notifiable_type' => Staffusers::class,
                'notifiable_id' => $userId,
                'data' => $data,
            ]);
        }
    }
}
