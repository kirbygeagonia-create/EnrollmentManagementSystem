<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Models\Blocks;
use App\Models\Enrollments;
use App\Models\Schedules;
use App\Models\Staffusers;

class BlockingPolicy
{
    /**
     * Determine whether the user can view any blocking assignments.
     */
    public function viewAny(Staffusers $user): bool
    {
        return $user->hasPermissionTo('block.view');
    }

    /**
     * Determine whether the user can view blocking for enrollment.
     */
    public function view(Staffusers $user, Enrollments $enrollment): bool
    {
        return $user->hasPermissionTo('block.view');
    }

    /**
     * Determine whether the user can manage blocks (create, update, delete).
     */
    public function manageBlocks(Staffusers $user): bool
    {
        return $user->hasPermissionTo('block.manage');
    }

    /**
     * Determine whether the user can assign students to blocks.
     * BR13/BR14: Workflow step for Academic Department (office 5) must be completed in order
     */
    public function assignStudents(Staffusers $user, Enrollments $enrollment): bool
    {
        if (! $user->hasPermissionTo('block.assign')) {
            return false;
        }

        // Must be Academic Department (officeId = 5)
        if ($user->officeId !== 5) {
            return false;
        }

        // Enrollment must be enrolled
        if ($enrollment->enrollmentStatus !== EnrollmentStatus::Enrolled) {
            return false;
        }

        // Check workflow step for Academic Department (office 5) is current
        $workflow = $enrollment->enrollmentworkflow;
        if (! $workflow || $workflow->workflowsteps()->where('stepStatus', 'pending')->orderBy('stepOrder')->first()?->officeId !== 5) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can manage schedules.
     */
    public function manageSchedules(Staffusers $user): bool
    {
        return $user->hasPermissionTo('block.schedules.manage');
    }

    /**
     * Determine whether the user can check capacity.
     */
    public function checkCapacity(Staffusers $user, Blocks $block): bool
    {
        return $user->hasPermissionTo('block.capacity.check');
    }

    /**
     * Determine whether the user can print block & schedule.
     */
    public function printBlockSchedule(Staffusers $user, Blocks $block): bool
    {
        return $user->hasPermissionTo('print.blockSchedule');
    }
}
