<?php

namespace App\Policies;

use App\Enums\OfficeId;
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

        // SysAdmin/Admin act globally; everyone else must belong to the
        // Blocking & Scheduling office. An OfficeHead from another office
        // previously passed this gate and failed later inside
        // WorkflowService with a 500 instead of a clean 403.
        if ($user->hasRole(['SysAdmin', 'Admin'])) {
            return true;
        }

        if ($user->officeId !== OfficeId::Blocking->value) {
            return false;
        }

        return $user->hasRole(['BlockingCoordinator', 'OfficeHead']);
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
