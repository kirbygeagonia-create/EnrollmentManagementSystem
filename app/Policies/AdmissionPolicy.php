<?php

namespace App\Policies;

use App\Enums\AdmissionStatus;
use App\Enums\ExamType;
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

        return $this->approvalBlockers($admission) === [];
    }

    /**
     * The readiness half of approve(), as the reasons an approval is refused.
     *
     * The gate used to answer every one of these with the same bare 403, which
     * told the admission officer nothing about which document to verify or which
     * exam to record. The desk prints this list instead of letting the officer
     * discover it by clicking.
     *
     * @return list<string>
     */
    public function approvalBlockers(Admissions $admission): array
    {
        $blockers = [];

        // Only pending admissions can be approved
        if ($admission->admissionStatus !== AdmissionStatus::Pending) {
            $blockers[] = 'Only a pending application can be approved — this one is '
                .$admission->admissionStatus->value.'.';
        }

        // Check if all required documents are submitted and verified (BR32)
        $requiredSubmissions = $admission->studentrequirementsubmissions()
            ->with('requirement')
            ->whereHas('requirement', fn ($q) => $q->where('isRequired', true))
            ->get();

        foreach ($requiredSubmissions as $submission) {
            if ($submission->submissionStatus->value !== 'verified') {
                $blockers[] = 'Required document "'
                    .($submission->requirement->requirementName ?? 'Requirement #'.$submission->requirementId)
                    .'" is '.$submission->submissionStatus->value.', not verified.';
            }
        }

        // Two examinations, two flags (BR9, and §28 G-6/C-5).
        //
        // The school-wide General examination is the first one every applicant
        // takes, before an enrollment can exist. The departmental examination is
        // a second, program-level screen — and until it had its own flag a
        // program that sets requiresEntranceExam could refuse an applicant for
        // the general result while waving through one who never sat the board
        // test at all, because an absent course-specific result was treated as
        // "not administered" rather than as a missing requirement.
        // Ruling C-4 (2026-10-07): a program may waive the general paper for an
        // applicant arriving with credit from another institution. The waiver is the
        // program's own setting — a transferee admitted under it still sits the
        // departmental examination below, which is the screen that tests the program's
        // fit — and with the flag off, which is how every program is seeded, an
        // applicant to an examining program is treated exactly as before.
        // Courses::waivesGeneralEntranceExam is the single answer, because the Exam
        // desk reads the same rule when it decides whom the department may score.
        $exemptFromGeneralExam = $admission->course
            ->waivesGeneralEntranceExam($admission->applicantType);

        if ($admission->course->requiresEntranceExam && ! $exemptFromGeneralExam) {
            $generalExam = $admission->examresults()
                ->where('examStage', 'entrance')
                ->where('examType', ExamType::General->value)
                ->first();

            if (! $generalExam) {
                $blockers[] = 'No General Entrance Exam result on record for this applicant.';
            } elseif ($generalExam->examResult->value !== 'pass') {
                $blockers[] = 'The General Entrance Exam result on file is '
                    .$generalExam->examResult->value.', not pass.';
            }
        }

        // A recorded departmental result always decides the outcome; a missing one
        // only does so where the program actually requires the examination.
        $courseExam = $admission->examresults()
            ->where('examStage', 'entrance')
            ->where('examType', ExamType::CourseSpecific->value)
            ->first();

        if (! $courseExam) {
            if ($admission->course->requiresCourseSpecificExam) {
                $blockers[] = 'No Course-Specific Entrance Exam result on record for this applicant, and '
                    .$admission->course->courseCode.' requires one.';
            }
        } elseif ($courseExam->examResult->value !== 'pass') {
            $blockers[] = 'The Course-Specific Entrance Exam result on file is '
                .$courseExam->examResult->value.', not pass.';
        }

        return $blockers;
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
