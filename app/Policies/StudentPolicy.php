<?php

namespace App\Policies;

use App\Models\Staffusers;
use App\Models\Students;

/**
 * Student directory / Student 360 authorization (audit §2.2).
 *
 * The student profile aggregates PII (addresses, guardians), financial
 * history (payments, assessments), and academic records. Access requires
 * the `students.view` permission, which RbacSeeder grants only to roles
 * whose desks legitimately work with student records — never to the bare
 * `Staff` or `Instructor` roles.
 */
class StudentPolicy
{
    /**
     * Determine whether the user can browse the student directory.
     *
     * hasAnyPermission() (not hasPermissionTo) returns false — instead of
     * throwing PermissionDoesNotExist — when the permission hasn't been
     * seeded yet. This keeps Inertia shared-props evaluation (`can.studentsView`)
     * safe on every page render, even before RbacSeeder runs.
     */
    public function viewAny(Staffusers $user): bool
    {
        return $user->hasAnyPermission('students.view');
    }

    /**
     * Determine whether the user can view a single student's full record.
     */
    public function view(Staffusers $user, Students $student): bool
    {
        return $user->hasAnyPermission('students.view');
    }

    /**
     * Determine whether the user can use the global quick-search endpoint.
     */
    public function quickSearch(Staffusers $user): bool
    {
        return $user->hasAnyPermission('students.view');
    }
}
