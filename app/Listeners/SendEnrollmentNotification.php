<?php

namespace App\Listeners;

use App\Events\EnrollmentStatusChanged;
use App\Models\Notifications;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendEnrollmentNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(EnrollmentStatusChanged $event): void
    {
        $enrollment = $event->enrollment;
        $student = $enrollment->student;

        $message = match ($event->toStatus) {
            'evaluated' => "Your enrollment has been evaluated. Proceed to Assessment.",
            'assessed' => "Your assessment has been computed. Proceed to Accounting for payment.",
            'paid' => "Payment received. Proceed to Registrar for approval.",
            'enrolled' => "Congratulations! You are now officially enrolled.",
            'dropped' => "Your enrollment has been dropped.",
            default => "Enrollment status changed from {$event->fromStatus} to {$event->toStatus}.",
        };

        // Create in-app notification
        Notifications::create([
            'type' => 'enrollment_status_changed',
            'notifiableType' => 'App\Models\Students',
            'notifiableId' => $student->studentId,
            'data' => json_encode([
                'message' => $message,
                'enrollmentId' => $enrollment->enrollmentId,
                'fromStatus' => $event->fromStatus,
                'toStatus' => $event->toStatus,
                'changedBy' => $event->changedBy?->firstName . ' ' . $event->changedBy?->lastName,
                'remarks' => $event->remarks,
            ]),
            'createdAt' => now(),
        ]);
    }
}