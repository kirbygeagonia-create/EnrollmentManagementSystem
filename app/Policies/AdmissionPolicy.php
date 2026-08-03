<?php

namespace App\Policies;

use App\Enums\AdmissionStatus;
use App\Models\Admissions;
use App\Models\Staffusers;

class AdmissionPolicy
{
    /**
     * Determine whether the user can view any admissions.
     */
    public function viewAny(Staffusers $user): bool
    {
        return $user->hasPermissionTo('admission.view');
    }

    /**
     * Determine whether the user can view the admission.
     */
    public function view(Staffusers $user, Admissions $admission): bool
    {
        return $user->hasPermissionTo('admission.view');
    }

    /**
     * Determine whether the user can create admissions.
     */
    public function create(Staffusers $user): bool
    {
        return $user->hasPermissionTo('admission.create');
    }

    /**
     * Determine whether the user can update the admission.
     */
    public function update(Staffusers $user, Admissions $admission): bool
    {
        return $user->hasPermissionTo('admission.update');
    }

    /**
     * Determine whether the user can approve the admission.
     * BR7: First-year and transferee applicants must have an admission record before enrollment
     */
    public function approve(Staffusers $user, Admissions $admission): bool
    {
        if (! $user->hasPermissionTo('admission.approve')) {
            return false;
        }

        // Only pending admissions can be approved
        if ($admission->admissionStatus !== AdmissionStatus::Pending) {
            return false;
        }

        // Check if all required documents are submitted and verified (BR32)
        $requiredSubmissions = $admission->studentrequirementsubmissions()
            ->whereHas('requirement', fn ($q) => $q->where('isRequired', true))
            ->get();

        foreach ($requiredSubmissions as $submission) {
            if ($submission->submissionStatus->value !== 'verified') {
                return false;
            }
        }

        // For board courses, check entrance exam passed (BR9)
        if ($admission->course->requiresEntranceExam) {
            $generalExam = $admission->examresults()
                ->where('examStage', 'entrance')
                ->where('examType', 'general')
                ->first();

            $courseExam = $admission->examresults()
                ->where('examStage', 'entrance')
                ->where('examType', 'courseSpecific')
                ->first();

            if (! $generalExam || $generalExam->examResult->value !== 'pass') {
                return false;
            }
            if (! $courseExam || $courseExam->examResult->value !== 'pass') {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether the user can reject the admission.
     */
    public function reject(Staffusers $user, Admissions $admission): bool
    {
        if (! $user->hasPermissionTo('admission.reject')) {
            return false;
        }

        return $admission->admissionStatus === AdmissionStatus::Pending;
    }

    /**
     * Determine whether the user can delete the admission.
     */
    public function delete(Staffusers $user, Admissions $admission): bool
    {
        return $user->hasPermissionTo('admission.delete')
            && $admission->admissionStatus === AdmissionStatus::Pending;
    }

    /**
     * Determine whether the user can submit requirements.
     */
    public function submitRequirements(Staffusers $user, Admissions $admission): bool
    {
        return $user->hasPermissionTo('admission.requirements.submit')
            && $admission->admissionStatus === AdmissionStatus::Pending;
    }

    /**
     * Determine whether the user can verify requirements.
     */
    public function verifyRequirements(Staffusers $user, Admissions $admission): bool
    {
        return $user->hasPermissionTo('admission.requirements.verify')
            && $admission->admissionStatus === AdmissionStatus::Pending;
    }
}
