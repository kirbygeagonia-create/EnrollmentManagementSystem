<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Models\Charges;
use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Models\Studentassessments;

class AssessmentPolicy
{
    /**
     * Determine whether the user can view any assessments.
     */
    public function viewAny(Staffusers $user): bool
    {
        return $user->hasPermissionTo('assessment.view');
    }

    /**
     * Determine whether the user can view the assessment.
     */
    public function view(Staffusers $user, Studentassessments $assessment): bool
    {
        return $user->hasPermissionTo('assessment.view');
    }

    /**
     * Determine whether the user can compute assessment.
     * BR19: Full (100%) scholarships are exclusive; partial stack up to 100% cap
     */
    public function compute(Staffusers $user, Enrollments $enrollment): bool
    {
        if (! $user->hasPermissionTo('assessment.compute')) {
            return false;
        }

        // Enrollment must be evaluated (after dept evaluation)
        if ($enrollment->enrollmentStatus !== EnrollmentStatus::Evaluated) {
            return false;
        }

        // Must be Accounting/Scholarship office (officeId = 2, 3)
        return in_array($user->officeId, [2, 3]);
    }

    /**
     * Determine whether the user can apply scholarships.
     * BR19: School Grant (100%) is default; outside scholarships stack with cap
     */
    public function applyScholarships(Staffusers $user, Studentassessments $assessment): bool
    {
        if (! $user->hasPermissionTo('assessment.scholarships.apply')) {
            return false;
        }

        // Must be Scholarship office (officeId = 3)
        return $user->officeId === 3;
    }

    /**
     * Determine whether the user can adjust charges.
     */
    public function adjustCharges(Staffusers $user, Studentassessments $assessment): bool
    {
        if (! $user->hasPermissionTo('assessment.charges.adjust')) {
            return false;
        }

        // Must be Accounting office (officeId = 2)
        return $user->officeId === 2;
    }

    /**
     * Determine whether the user can finalize assessment.
     */
    public function finalize(Staffusers $user, Studentassessments $assessment): bool
    {
        if (! $user->hasPermissionTo('assessment.finalize')) {
            return false;
        }

        // Must be Accounting office (officeId = 2)
        return $user->officeId === 2;
    }
}
