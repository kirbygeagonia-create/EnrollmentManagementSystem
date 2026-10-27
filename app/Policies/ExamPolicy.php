<?php

namespace App\Policies;

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
     * Determine whether the user can confirm a recorded result official (§7.2).
     *
     * The right alone decides this: which rows are *worth* confirming is a state question
     * and `ExamController::verify()` answers it, so a department that holds no confirm right
     * is refused here while a Registrar who re-confirms an official row is refused there with
     * the reason the desk needs. Recording and confirming are deliberately separate rights —
     * the ruling is that the office that scored the paper does not adopt it — and the same
     * account may hold both only where it holds both desks, which is SysAdmin's `Gate::before`
     * and no seeded role.
     */
    public function verify(Staffusers $user, Examresults $exam): bool
    {
        return $user->hasPermissionTo('exam.verify');
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
}
