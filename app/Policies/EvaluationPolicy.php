<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\StudentType;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Models\Students;

class EvaluationPolicy
{
    /**
     * Determine whether the user can view any evaluations.
     */
    public function viewAny(Staffusers $user): bool
    {
        return $user->hasPermissionTo('evaluation.view');
    }

    /**
     * Determine whether the user can view the evaluation.
     */
    public function view(Staffusers $user, Enrollments $enrollment): bool
    {
        return $user->hasPermissionTo('evaluation.view');
    }

    /**
     * Determine whether the user can create enrollment (issue enrollment form).
     * BR31: enrollmentType derived from studentType
     * BR32: All demographic fields must be filled
     */
    public function create(Staffusers $user, Students $student, Courses $course): bool
    {
        if (! $user->hasPermissionTo('evaluation.create')) {
            return false;
        }

        // Must be department evaluator, dean, program head, or instructor
        return $user->hasRole(['Dean', 'ProgramHead', 'Instructor', 'DeptEvaluator', 'Admin', 'SysAdmin'])
            || $user->unitId !== null
            || in_array($user->officeId, [
                OfficeId::Registrar->value,
                OfficeId::Guidance->value,
                OfficeId::Blocking->value,
                OfficeId::Admission->value,
                OfficeId::Academic->value,
            ], true);
    }

    /**
     * Determine whether the user can capture full demographic profile.
     * BR32: Every field on enrollment form must be filled
     */
    public function captureProfile(Staffusers $user, Enrollments $enrollment): bool
    {
        if (! $user->hasPermissionTo('evaluation.profile.capture')) {
            return false;
        }

        // Only the evaluator who created the enrollment or dean can edit
        return $enrollment->evaluatedBy === $user->userId
            || $user->hasPermissionTo('evaluation.profile.capture.any');
    }

    /**
     * Determine whether the user can propose subject load from curriculum.
     * BR17: Student type determines which phases apply
     * BR18: Academic standing affects subject assignment
     */
    public function proposeSubjects(Staffusers $user, Enrollments $enrollment): bool
    {
        if (! $user->hasPermissionTo('evaluation.subjects.propose')) {
            return false;
        }

        // Enrollment must still be pending (the controller transitions to evaluated after proposing)
        if ($enrollment->enrollmentStatus !== EnrollmentStatus::Pending) {
            return false;
        }

        // Only evaluator or dean
        return $enrollment->evaluatedBy === $user->userId
            || $user->hasPermissionTo('evaluation.subjects.propose.any');
    }

    /**
     * Determine whether the user can process credit transfer (transferee/shifter).
     */
    public function processCredits(Staffusers $user, Enrollments $enrollment): bool
    {
        if (! $user->hasPermissionTo('evaluation.credits.process')) {
            return false;
        }

        // Only for transferee or shifter
        return in_array($enrollment->studentType->value, ['transferee', 'shifter']);
    }

    /**
     * Determine whether the user can sign evaluation (evaluator + dean).
     */
    public function sign(Staffusers $user, Enrollments $enrollment): bool
    {
        if (! $user->hasPermissionTo('evaluation.sign')) {
            return false;
        }

        // Enrollment must be evaluated
        if ($enrollment->enrollmentStatus !== EnrollmentStatus::Evaluated) {
            return false;
        }

        // Must be evaluator or dean/program head
        return $enrollment->evaluatedBy === $user->userId
            || $user->hasPermissionTo('evaluation.sign.dean');
    }

    /**
     * Determine whether the user can confirm enrolled subjects (Registrar phase).
     */
    public function confirmSubjects(Staffusers $user, Enrollments $enrollment): bool
    {
        if (! $user->hasPermissionTo('enrollment.subjects.confirm')) {
            return false;
        }

        // Must be Registrar office (officeId = 1)
        return $user->officeId === OfficeId::Registrar->value;
    }
}
