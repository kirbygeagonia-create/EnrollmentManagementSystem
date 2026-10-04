<?php

namespace App\Services;

use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Events\EnrollmentStatusChanged;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Enrollmentstatushistory;
use App\Models\Staffusers;
use Illuminate\Support\Facades\DB;

class EnrollmentStateMachine
{
    /**
     * Allowed transitions for enrollment status.
     * Key = current status, value = allowed next statuses.
     */
    private const TRANSITIONS = [
        'pending' => ['evaluated'],
        'evaluated' => ['assessed'],
        // Ruling 17: the Registrar can drop a record from the point it is answerable
        // for — the assessed load, the paid one, and the enrolled one. Each of those
        // has signatures behind it, which is exactly why the act needs a reason and
        // this desk rather than the one that signed.
        'assessed' => ['paid', 'dropped'],
        // Registrar hold (item 8): a paid enrollment can be returned to
        // Department Evaluation with a required reason, and re-enters the
        // pipeline through a fresh evaluation sign-off.
        'paid' => ['enrolled', 'assessed', 'returnedToEvaluation', 'dropped'],
        'returnedToEvaluation' => ['evaluated'],
        // Rulings 14 and 15 reach past the Registrar's own signature: a refunded receipt or
        // a withdrawn grant can leave an approved enrollment owing again, and a record that
        // owes has to sit where Accounting can see it — `assessed`. Signatures already given
        // are history and are not un-signed by the reversal, the same rule the payment void
        // follows, so an undo never erases who did what.
        'enrolled' => ['dropped', 'assessed'],
        // A drop is terminal. The student re-enrolls — a new record in the same term,
        // whose seat this one released — rather than this row being un-dropped.
        'dropped' => [],
    ];

    /**
     * Allowed transitions for enrolled subject status.
     */
    private const SUBJECT_TRANSITIONS = [
        'proposed' => ['confirmed', 'dropped'],
        'confirmed' => ['dropped'],
        'dropped' => [],
    ];

    /**
     * Transition the enrollment to a new status.
     *
     * @throws InvalidStateTransitionException
     */
    public function transition(
        Enrollments $enrollment,
        EnrollmentStatus $newStatus,
        ?Staffusers $changedBy = null,
        string $remarks = ''
    ): Enrollments {
        $this->assertValidTransition($enrollment->enrollmentStatus->value, $newStatus->value);

        DB::transaction(function () use ($enrollment, $newStatus, $changedBy, $remarks) {
            $fromStatus = $enrollment->enrollmentStatus;

            $enrollment->enrollmentStatus = $newStatus;
            $enrollment->save();

            Enrollmentstatushistory::create([
                'enrollmentId' => $enrollment->enrollmentId,
                'fromStatus' => $fromStatus->value,
                'toStatus' => $newStatus->value,
                'changedBy' => $changedBy?->userId,
                'remarks' => $remarks,
                'changedAt' => now(),
            ]);

            event(new EnrollmentStatusChanged(
                $enrollment,
                $fromStatus->value,
                $newStatus->value,
                $changedBy,
                $remarks
            ));
        });

        return $enrollment->fresh();
    }

    /**
     * Transition an enrolled subject's status.
     */
    public function transitionSubject(
        Enrolledsubjects $subject,
        EnrolledSubjectStatus $newStatus,
        string $remarks = ''
    ): Enrolledsubjects {
        $this->assertValidSubjectTransition($subject->status->value, $newStatus->value);

        $subject->status = $newStatus;
        $subject->save();

        return $subject->fresh();
    }

    /**
     * Get the next allowed statuses for an enrollment.
     *
     * @return string[]
     */
    public function allowedTransitions(Enrollments $enrollment): array
    {
        return self::TRANSITIONS[$enrollment->enrollmentStatus->value];
    }

    /**
     * Check if a transition is allowed.
     */
    public function canTransition(Enrollments $enrollment, EnrollmentStatus $newStatus): bool
    {
        return in_array($newStatus->value, self::TRANSITIONS[$enrollment->enrollmentStatus->value]);
    }

    /**
     * @throws InvalidStateTransitionException
     */
    private function assertValidTransition(string $from, string $to): void
    {
        $allowed = self::TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed)) {
            throw new InvalidStateTransitionException(
                "Cannot transition enrollment from '{$from}' to '{$to}'."
            );
        }
    }

    /**
     * @throws InvalidStateTransitionException
     */
    private function assertValidSubjectTransition(string $from, string $to): void
    {
        $allowed = self::SUBJECT_TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed)) {
            throw new InvalidStateTransitionException(
                "Cannot transition subject from '{$from}' to '{$to}'."
            );
        }
    }
}
