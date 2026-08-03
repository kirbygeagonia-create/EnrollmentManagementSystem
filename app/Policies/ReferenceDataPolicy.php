<?php

namespace App\Policies;

use App\Models\Staffusers;

class ReferenceDataPolicy
{
    /**
     * Determine whether the user can view reference data.
     */
    public function viewAny(Staffusers $user): bool
    {
        return $user->hasPermissionTo('refdata.view');
    }

    /**
     * Determine whether the user can manage courses.
     */
    public function manageCourses(Staffusers $user): bool
    {
        return $user->hasPermissionTo('refdata.courses.manage');
    }

    /**
     * Determine whether the user can manage majors.
     */
    public function manageMajors(Staffusers $user): bool
    {
        return $user->hasPermissionTo('refdata.majors.manage');
    }

    /**
     * Determine whether the user can manage curriculums.
     */
    public function manageCurriculums(Staffusers $user): bool
    {
        return $user->hasPermissionTo('refdata.curriculums.manage');
    }

    /**
     * Determine whether the user can manage curriculum subjects.
     */
    public function manageCurriculumSubjects(Staffusers $user): bool
    {
        return $user->hasPermissionTo('refdata.curriculumSubjects.manage');
    }

    /**
     * Determine whether the user can manage subjects.
     */
    public function manageSubjects(Staffusers $user): bool
    {
        return $user->hasPermissionTo('refdata.subjects.manage');
    }

    /**
     * Determine whether the user can manage academic terms/years.
     */
    public function manageTerms(Staffusers $user): bool
    {
        return $user->hasPermissionTo('refdata.terms.manage');
    }

    /**
     * Determine whether the user can manage fee types.
     */
    public function manageFeeTypes(Staffusers $user): bool
    {
        return $user->hasPermissionTo('refdata.feeTypes.manage');
    }

    /**
     * Determine whether the user can manage scholarship types.
     */
    public function manageScholarshipTypes(Staffusers $user): bool
    {
        return $user->hasPermissionTo('refdata.scholarshipTypes.manage');
    }

    /**
     * Determine whether the user can manage offices.
     */
    public function manageOffices(Staffusers $user): bool
    {
        return $user->hasPermissionTo('refdata.offices.manage');
    }

    /**
     * Determine whether the user can manage rooms.
     */
    public function manageRooms(Staffusers $user): bool
    {
        return $user->hasPermissionTo('refdata.rooms.manage');
    }

    /**
     * Determine whether the user can manage blocks.
     */
    public function manageBlocks(Staffusers $user): bool
    {
        return $user->hasPermissionTo('refdata.blocks.manage');
    }

    /**
     * Determine whether the user can manage admission requirements.
     */
    public function manageAdmissionRequirements(Staffusers $user): bool
    {
        return $user->hasPermissionTo('refdata.admissionRequirements.manage');
    }

    /**
     * Determine whether the user can manage clearance requirements.
     */
    public function manageClearanceRequirements(Staffusers $user): bool
    {
        return $user->hasPermissionTo('refdata.clearanceRequirements.manage');
    }
}
