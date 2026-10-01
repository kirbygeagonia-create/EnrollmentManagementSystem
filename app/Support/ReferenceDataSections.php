<?php

namespace App\Support;

use App\Models\Staffusers;

/**
 * The Reference Data catalogs and the permission each one needs.
 *
 * The hub linked every catalog to everyone who could open it, so a Registrar
 * clicking eleven of the twelve tiles met a 403. Both the hub's tile list and
 * the launcher's Administration entry read this map, so the screens offered are
 * the screens the signed-in user can actually use.
 */
class ReferenceDataSections
{
    /**
     * @var array<string, string> route name => permission that opens it
     */
    public const PERMISSIONS = [
        'admin.reference-data.courses' => 'refdata.courses.manage',
        'admin.reference-data.majors' => 'refdata.majors.manage',
        'admin.reference-data.curriculums' => 'refdata.curriculums.manage',
        'admin.reference-data.curriculum-subjects' => 'refdata.curriculumSubjects.manage',
        'admin.reference-data.subjects' => 'refdata.subjects.manage',
        'admin.reference-data.terms' => 'refdata.terms.manage',
        'admin.reference-data.fee-types' => 'refdata.feeTypes.manage',
        'admin.reference-data.grade-scale' => 'refdata.gradeScale.manage',
        'admin.reference-data.scholarship-types' => 'refdata.scholarshipTypes.manage',
        'admin.reference-data.offices' => 'refdata.offices.manage',
        'admin.reference-data.rooms' => 'refdata.rooms.manage',
        'admin.reference-data.blocks' => 'refdata.blocks.manage',
        'admin.reference-data.admission-requirements' => 'refdata.admissionRequirements.manage',
        'admin.reference-data.clearance-requirements' => 'refdata.clearanceRequirements.manage',
    ];

    /**
     * @return string[] route names this user may open
     */
    public static function manageable(?Staffusers $user): array
    {
        if ($user === null) {
            return [];
        }

        return array_keys(array_filter(
            static::PERMISSIONS,
            fn (string $permission) => $user->checkPermissionTo($permission)
        ));
    }

    /**
     * Whether the hub is worth offering: the user can read reference data AND
     * maintains at least one catalog in it.
     */
    public static function hubVisible(?Staffusers $user): bool
    {
        return $user !== null
            && $user->checkPermissionTo('refdata.view')
            && static::manageable($user) !== [];
    }
}
