<?php

namespace App\Policies;

use App\Models\Blocks;
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
     * Determine whether the user can view a block.
     */
    public function view(Staffusers $user, Blocks $block): bool
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
     * Policy only checks permission + office; workflow/enrollment checks moved to controller.
     */
    public function assignStudents(Staffusers $user, Blocks $block): bool
    {
        if (! $user->hasPermissionTo('block.assign')) {
            return false;
        }

        // Must be Academic Department (officeId = 5)
        if ($user->officeId !== 5) {
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
