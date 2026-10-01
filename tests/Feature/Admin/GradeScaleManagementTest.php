<?php

namespace Tests\Feature\Admin;

use App\Models\Gradescale;
use App\Models\Staffusers;
use App\Support\ReferenceDataSections;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The grade scale is what academic standing is derived against, so it has to be
 * maintainable by a human once the Registrar signs the bands off — and it must
 * never be able to silently lose its passing band, because an empty passing set
 * drops the derivation back to the assumed 3.00 ceiling.
 */
class GradeScaleManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RbacSeeder', '--database' => 'sqlite']);

        $this->scaleBands();
    }

    private function scaleBands(): void
    {
        Gradescale::query()->truncate();

        collect([
            ['1.00', '3.00', true, 'Passing.'],
            ['3.01', '5.00', false, 'Failed; subject must be retaken.'],
        ])->each(fn (array $band) => Gradescale::create([
            'minGrade' => $band[0],
            'maxGrade' => $band[1],
            'isPassing' => $band[2],
            'description' => $band[3],
        ]));
    }

    private function staffWithRole(string $roleName): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => 1,
            'role' => 'officeHead',
            'employeeNo' => 'EMP-'.uniqid(),
            'username' => 'user'.uniqid(),
            'email' => 'user'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();

        $staff->assignRole($roleName);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    #[Test]
    public function the_permission_is_seeded_for_sysadmin_and_the_registrar_approver(): void
    {
        $permission = DB::table('permissions')
            ->where('name', 'refdata.gradeScale.manage')
            ->first();

        $this->assertNotNull($permission, 'RbacSeeder must create refdata.gradeScale.manage.');

        $roleIds = DB::table('role_permissions')
            ->where('permissionId', $permission->id)
            ->pluck('roleId')
            ->all();

        $roleNames = DB::table('roles')->whereIn('id', $roleIds)->pluck('name')->sort()->values()->all();

        // The Registrar finalizes standing, so it maintains the scale standing is
        // derived against; no other desk may change it.
        $this->assertSame(['RegistrarApprover', 'SysAdmin'], $roleNames);
    }

    #[Test]
    public function sysadmin_sees_the_bands_and_the_ceiling_in_use(): void
    {
        $response = $this->actingAs($this->staffWithRole('SysAdmin'))
            ->get(route('admin.reference-data.grade-scale'));

        $response->assertOk();
        $page = $response->viewData('page');

        $this->assertSame('Admin/ReferenceData/GradeScales', $page['component']);
        $this->assertSame(3.0, $page['props']['passingCeiling']);
        $this->assertTrue($page['props']['hasGradeScale']);
        $this->assertCount(2, $page['props']['bands']['data']);
    }

    #[Test]
    public function sysadmin_can_create_a_band(): void
    {
        $response = $this->actingAs($this->staffWithRole('SysAdmin'))->post(
            route('admin.reference-data.grade-scale.store'),
            [
                'minGrade' => '1.00',
                'maxGrade' => '1.50',
                'isPassing' => true,
                'description' => 'Excellent.',
            ]
        );

        $response->assertRedirect()->assertSessionHasNoErrors();

        $created = Gradescale::query()->where('description', 'Excellent.')->first();

        $this->assertNotNull($created);
        $this->assertTrue((bool) $created->isPassing);
    }

    #[Test]
    public function moving_the_top_of_the_passing_band_moves_the_derivation_ceiling(): void
    {
        $passing = Gradescale::query()->where('isPassing', true)->first();

        $this->actingAs($this->staffWithRole('SysAdmin'))->patch(
            route('admin.reference-data.grade-scale.update', $passing->gradeScaleId),
            [
                'minGrade' => '1.00',
                'maxGrade' => '2.50',
                'isPassing' => true,
                'description' => 'Passing, tightened.',
            ]
        )->assertSessionHasNoErrors();

        $this->assertSame(2.5, Gradescale::passingCeiling());
    }

    #[Test]
    public function a_band_whose_floor_sits_above_its_ceiling_is_rejected(): void
    {
        $passing = Gradescale::query()->where('isPassing', true)->first();

        $this->actingAs($this->staffWithRole('SysAdmin'))->post(
            route('admin.reference-data.grade-scale.store'),
            [
                'minGrade' => '4.00',
                'maxGrade' => '2.00',
                'isPassing' => true,
                'description' => 'Inverted band.',
            ]
        )->assertSessionHasErrors('minGrade');

        $this->assertSame(3.0, Gradescale::passingCeiling());
        $this->assertCount(2, Gradescale::query()->get());
    }

    #[Test]
    public function the_last_passing_band_cannot_be_deleted(): void
    {
        $sysAdmin = $this->staffWithRole('SysAdmin');
        $passing = Gradescale::query()->where('isPassing', true)->first();

        $this->actingAs($sysAdmin)->delete(
            route('admin.reference-data.grade-scale.destroy', $passing->gradeScaleId)
        )->assertSessionHasErrors('isPassing');

        $this->assertNotNull(Gradescale::find($passing->gradeScaleId));

        // With a second passing band on file, deleting one of them is allowed.
        $extra = Gradescale::create([
            'minGrade' => '1.00',
            'maxGrade' => '1.50',
            'isPassing' => true,
            'description' => 'Excellent.',
        ]);

        $this->actingAs($sysAdmin)->delete(
            route('admin.reference-data.grade-scale.destroy', $passing->gradeScaleId)
        )->assertSessionHasNoErrors();

        $this->assertNull(Gradescale::find($passing->gradeScaleId));
        $this->assertSame(1.5, Gradescale::passingCeiling());
        $this->assertNotNull(Gradescale::find($extra->gradeScaleId));
    }

    #[Test]
    public function the_last_passing_band_cannot_be_flipped_to_failing(): void
    {
        $passing = Gradescale::query()->where('isPassing', true)->first();

        $this->actingAs($this->staffWithRole('SysAdmin'))->patch(
            route('admin.reference-data.grade-scale.update', $passing->gradeScaleId),
            [
                'minGrade' => '1.00',
                'maxGrade' => '3.00',
                'isPassing' => false,
                'description' => 'Passing.',
            ]
        )->assertSessionHasErrors('isPassing');

        $this->assertTrue((bool) Gradescale::find($passing->gradeScaleId)->isPassing);
        $this->assertSame(3.0, Gradescale::passingCeiling());
    }

    #[Test]
    public function a_staff_member_without_the_permission_is_refused(): void
    {
        $staff = $this->staffWithRole('Staff');

        $this->actingAs($staff)->get(route('admin.reference-data.grade-scale'))->assertForbidden();

        $this->actingAs($staff)->post(
            route('admin.reference-data.grade-scale.store'),
            [
                'minGrade' => '1.00',
                'maxGrade' => '1.50',
                'isPassing' => true,
                'description' => 'Injected band.',
            ]
        )->assertForbidden();

        $this->assertNull(Gradescale::query()->where('description', 'Injected band.')->first());
    }

    #[Test]
    public function the_registrar_approver_can_maintain_the_scale(): void
    {
        $registrar = $this->staffWithRole('RegistrarApprover');

        $this->actingAs($registrar)->get(route('admin.reference-data.grade-scale'))->assertOk();

        $passing = Gradescale::query()->where('isPassing', true)->first();

        $this->actingAs($registrar)->patch(
            route('admin.reference-data.grade-scale.update', $passing->gradeScaleId),
            [
                'minGrade' => '1.00',
                'maxGrade' => '2.75',
                'isPassing' => true,
                'description' => 'Passing, signed off by the Registrar.',
            ]
        )->assertSessionHasNoErrors();

        $this->assertSame(2.75, Gradescale::passingCeiling());
    }

    #[Test]
    public function the_catalog_hub_offers_only_the_catalogs_the_user_maintains(): void
    {
        $registrar = $this->staffWithRole('RegistrarApprover');

        $page = $this->actingAs($registrar)
            ->get(route('admin.reference-data.index'))
            ->assertOk()
            ->viewData('page');

        // The Registrar reaches the hub, and the only catalog in it is the scale.
        $this->assertSame(['admin.reference-data.grade-scale'], $page['props']['manageable']);

        $sysAdminPage = $this->actingAs($this->staffWithRole('SysAdmin'))
            ->get(route('admin.reference-data.index'))
            ->assertOk()
            ->viewData('page');

        $this->assertCount(14, $sysAdminPage['props']['manageable']);

        // A desk that maintains no catalog is not offered the hub entry at all.
        $this->assertTrue(ReferenceDataSections::hubVisible($registrar));
        $this->assertFalse(ReferenceDataSections::hubVisible($this->staffWithRole('AccountingStaff')));
    }

    #[Test]
    public function the_junior_registrar_desk_may_not_change_the_scale(): void
    {
        $this->actingAs($this->staffWithRole('RegistrarDesk'))
            ->get(route('admin.reference-data.grade-scale'))
            ->assertForbidden();
    }
}
