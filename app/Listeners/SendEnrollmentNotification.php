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
            'returnedToEvaluation' => $this->returnLead($event, 'Department Evaluation'),
            // §6.4 (gap G-10): the second destination the owner ruled. It used to fall through to the
            // default sentence, which said only that a status moved — while the desk that owed the
            // correction is Admission, and `nextOffice()` now answers that desk for this state.
            'returnedToAdmission' => $this->returnLead($event, 'Admission'),
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

    /**
     * The bell's sentence for a return. It used to name the Registrar as the sender in prose, which
     * was a fact only while one desk could send anything back; since the one return act (development
     * prompt §6.4, gap G-10) is held by three desks, the sender is read from the act itself.
     */
    private function returnLead(EnrollmentStatusChanged $event, string $destination): string
    {
        $sender = $event->changedBy === null
            ? 'an account with no name on this record'
            : $event->changedBy->name;

        return "{$sender} returned this record to {$destination}".($event->remarks ? ": {$event->remarks}" : '.');
    }
}
