<?php

namespace App\Policies;

use App\Models\Staffusers;
use App\Models\Roles;
use App\Models\Permissions;

class UserManagementPolicy
{
    /**
     * Determine whether the user can view any staff users.
     */
    public function viewAny(Staffusers $user): bool
    {
        return $user->hasPermissionTo('user.view');
    }

    /**
     * Determine whether the user can view the staff user.
     */
    public function view(Staffusers $user, Staffusers $target): bool
    {
        return $user->hasPermissionTo('user.view');
    }

    /**
     * Determine whether the user can create staff users.
     */
    public function create(Staffusers $user): bool
    {
        return $user->hasPermissionTo('user.create');
    }

    /**
     * Determine whether the user can update staff users.
     */
    public function update(Staffusers $user, Staffusers $target): bool
    {
        if (!$user->hasPermissionTo('user.update')) {
            return false;
        }

        // Users can update their own profile
        if ($user->userId === $target->userId) {
            return true;
        }

        // Admins can update anyone
        return $user->hasPermissionTo('user.update.any');
    }

    /**
     * Determine whether the user can delete staff users.
     */
    public function delete(Staffusers $user, Staffusers $target): bool
    {
        if (!$user->hasPermissionTo('user.delete')) {
            return false;
        }

        // Cannot delete self
        if ($user->userId === $target->userId) {
            return false;
        }

        // Only SysAdmin can delete
        return $user->hasPermissionTo('user.delete.any');
    }

    /**
     * Determine whether the user can assign roles.
     */
    public function assignRoles(Staffusers $user, Staffusers $target): bool
    {
        if (!$user->hasPermissionTo('user.roles.assign')) {
            return false;
        }

        // Cannot assign roles to self
        if ($user->userId === $target->userId) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can manage roles.
     */
    public function manageRoles(Staffusers $user): bool
    {
        return $user->hasPermissionTo('user.roles.manage');
    }

    /**
     * Determine whether the user can manage permissions.
     */
    public function managePermissions(Staffusers $user): bool
    {
        return $user->hasPermissionTo('user.permissions.manage');
    }

    /**
     * Determine whether the user can deactivate/activate users.
     */
    public function toggleStatus(Staffusers $user, Staffusers $target): bool
    {
        if (!$user->hasPermissionTo('user.status.toggle')) {
            return false;
        }

        // Cannot toggle self
        if ($user->userId === $target->userId) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can view audit logs.
     */
    public function viewAuditLogs(Staffusers $user): bool
    {
        return $user->hasPermissionTo('audit.view');
    }

    /**
     * Determine whether the user can manage settings.
     */
    public function manageSettings(Staffusers $user): bool
    {
        return $user->hasPermissionTo('settings.manage');
    }
}