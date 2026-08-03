<?php

namespace App\Policies;

use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamType;
use App\Models\Courses;
use App\Models\Examresults;
use App\Models\Staffusers;

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
        // Entrance exam: Guidance records general, Department records course-specific (BR9)
        if ($stage === ExamStage::Entrance) {
            return match ($type) {
                ExamType::General => $user->hasPermissionTo('exam.record.general'),
                ExamType::CourseSpecific => $user->hasPermissionTo('exam.record.courseSpecific'),
            };
        }

        // Retention exam for continuing board-course students (BR10)
        if (! $course->requiresRetentionExam) {
            return false;
        }

        return $user->hasPermissionTo('exam.record.retention');
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
