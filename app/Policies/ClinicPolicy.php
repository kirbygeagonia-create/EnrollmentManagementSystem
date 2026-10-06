<?php

namespace App\Policies;

use App\Enums\ClinicRecordStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\OfficeId;
use App\Enums\WorkflowStepStatus;
use App\Models\Clinicrecords;
use App\Models\Enrollments;
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
     * BR13/BR14: Workflow step 7 (Clinic) must be completed in order
     *
     * Recording the assessment IS the Clinic box's signature — `record()` writes the record
     * and signs the step in one transaction — so the act needs both rights: the counter's
     * (`clinic.record`, which ruling 7 left with OfficeHead) and the signature's
     * (`clinic.sign`, which ruling 7 took away from OfficeHead and gave to ClinicStaff).
     * Before this, `clinic.sign` was held by roles and checked by no caller at all, so the
     * ruling was recorded on the matrix and unenforced at the desk. This is the same shape
     * RegistrarPolicy::approve gives enrollment.approve: where the act and the signature are
     * one act, the signature governs it.
     */
    public function record(Staffusers $user, Enrollments $enrollment): bool
    {
        if (! $user->hasPermissionTo('clinic.record')) {
            return false;
        }

        if (! $user->hasPermissionTo('clinic.sign')) {
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
        if (! $workflow || $workflow->workflowsteps()->where('stepStatus', WorkflowStepStatus::Pending->value)->orderBy('stepOrder')->first()?->officeId !== OfficeId::Clinic->value) {
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
}
