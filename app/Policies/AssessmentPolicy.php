<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\OfficeId;
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

        // Must be Accounting/Scholarship office
        return in_array($user->officeId, [OfficeId::Accounting->value, OfficeId::Scholarship->value], true);
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
        return $user->officeId === OfficeId::Scholarship->value;
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
        return $user->officeId === OfficeId::Accounting->value;
    }

    /**
     * Determine whether the user can finalize assessment.
     * The Assessment office (3) finalizes what it computed and signs its own
     * workflow step (BR13/BR14); Accounting (2) may also finalize.
     */
    public function finalize(Staffusers $user, Studentassessments $assessment): bool
    {
        if (! $user->hasPermissionTo('assessment.finalize')) {
            return false;
        }

        // Must be Assessment or Accounting office
        return in_array($user->officeId, [OfficeId::Accounting->value, OfficeId::Scholarship->value], true);
    }
}
