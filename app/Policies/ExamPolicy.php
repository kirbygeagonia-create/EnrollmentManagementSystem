<?php

namespace App\Policies;

use App\Models\Staffusers;
use App\Models\Examresults;
use App\Models\Courses;
use App\Enums\ExamStage;
use App\Enums\ExamType;
use App\Enums\ExamResult;

class ExamPolicy
{
    /**
     * Determine whether the user can view any exam results.
     */
    public function viewAny(Staffusers $user): bool
    {
        return $user->hasPermissionTo('exam.view');
    }

    /**
     * Determine whether the user can view the exam result.
     */
    public function view(Staffusers $user, Examresults $exam): bool
    {
        return $user->hasPermissionTo('exam.view');
    }

    /**
     * Determine whether the user can record exam results.
     * BR9: Two-stage entrance exam for board courses
     * BR10: Retention exam for board-course continuing students
     */
    public function record(Staffusers $user, Courses $course, ExamStage $stage, ExamType $type): bool
    {
        // Guidance Office records general entrance exam
        if ($stage === ExamStage::Entrance && $type === ExamType::General) {
            return $user->hasPermissionTo('exam.record.general');
        }

        // Department records course-specific entrance exam (after verifying general)
        if ($stage === ExamStage::Entrance && $type === ExamType::CourseSpecific) {
            return $user->hasPermissionTo('exam.record.courseSpecific');
        }

        // Department records retention exam for continuing board-course students
        if ($stage === ExamStage::Retention) {
            if (!$course->requiresRetentionExam) {
                return false;
            }
            return $user->hasPermissionTo('exam.record.retention');
        }

        return false;
    }

    /**
     * Determine whether the user can verify general exam before course-specific.
     * BR9: Course-specific exam verifies Guidance result first
     */
    public function verifyGeneralExam(Staffusers $user, Examresults $exam): bool
    {
        return $user->hasPermissionTo('exam.verify.general')
            && $exam->examStage === ExamStage::Entrance
            && $exam->examType === ExamType::General
            && $exam->examResult === ExamResult::Pass;
    }
}