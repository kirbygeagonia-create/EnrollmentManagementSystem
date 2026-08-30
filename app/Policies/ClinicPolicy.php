<?php

namespace App\Policies;

use App\Enums\ClinicRecordStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\OfficeId;
use App\Models\Clinicrecords;
use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Staffusers;

class ClinicPolicy
{
    /**
     * Determine whether the user can view any clinic records.
     */
    public function viewAny(Staffusers $user): bool
    {
        return $user->hasPermissionTo('clinic.view');
    }

    /**
     * Determine whether the user can view the clinic record.
     */
    public function view(Staffusers $user, Clinicrecords $clinic): bool
    {
        return $user->hasPermissionTo('clinic.view');
    }

    /**
     * Determine whether the user can record clinic assessment.
     * Phase 7: Physical exam, PhilHealth, hard-copy assessments
     * BR13/BR14: Workflow step for Clinic (office 11) must be completed in order
     */
    public function record(Staffusers $user, Enrollments $enrollment): bool
    {
        if (! $user->hasPermissionTo('clinic.record')) {
            return false;
        }

        // Must be Clinic office (officeId = 11)
        if ($user->officeId !== OfficeId::Clinic->value) {
            return false;
        }

        // Enrollment must be enrolled
        if ($enrollment->enrollmentStatus !== EnrollmentStatus::Enrolled) {
            return false;
        }

        // Check workflow step for Clinic (office 11) is current
        $workflow = $enrollment->enrollmentworkflow;
        if (! $workflow || $workflow->workflowsteps()->where('stepStatus', 'pending')->orderBy('stepOrder')->first()?->officeId !== OfficeId::Clinic->value) {
            return false;
        }

        // Check if clinic record already exists
        $existing = Clinicrecords::where('enrollmentId', $enrollment->enrollmentId)->first();
        if ($existing && $existing->status === ClinicRecordStatus::Completed) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can update clinic record.
     */
    public function update(Staffusers $user, Clinicrecords $clinic): bool
    {
        if (! $user->hasPermissionTo('clinic.update')) {
            return false;
        }

        // Must be Clinic office
        if ($user->officeId !== OfficeId::Clinic->value) {
            return false;
        }

        // Can only update if not completed
        return $clinic->status !== ClinicRecordStatus::Completed;
    }

    /**
     * Determine whether the user can reopen a completed clinic record.
     */
    public function reopen(Staffusers $user, Clinicrecords $clinic): bool
    {
        if (! $user->hasPermissionTo('clinic.reopen')) {
            return false;
        }

        // Must be Clinic office
        if ($user->officeId !== OfficeId::Clinic->value) {
            return false;
        }

        // Can only reopen if status is completed
        return $clinic->status === ClinicRecordStatus::Completed;
    }

    /**
     * Determine whether the user can sign workflow step.
     */
    public function signWorkflow(Staffusers $user, Enrollmentworkflow $workflow): bool
    {
        if (! $user->hasPermissionTo('clinic.sign')) {
            return false;
        }

        // Must be Clinic office
        return $user->officeId === OfficeId::Clinic->value;
    }
}
