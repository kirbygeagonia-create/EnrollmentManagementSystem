<?php

namespace Tests\Feature\Admin;

use App\Models\Clearancerequirements;
use App\Models\Offices;
use App\Models\Staffusers;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A clearance requirement has to be able to say what the student owes.
 *
 * `clearancerequirements` stored an id and an officeId and nothing else, so a
 * requirement WAS an office: the slip printed "Registrar" where the obligation should
 * read, an approving officer signed against no text, and a second line for the same
 * office — books and uniform, say — could not be added without inventing an office.
 * The text now lives on the row and Reference Data maintains it.
 */
class ClearanceRequirementManagementTest extends TestCase
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

        Offices::firstOrCreate(['officeId' => 1], ['officeName' => 'Registrar']);
        Offices::firstOrCreate(['officeId' => 2], ['officeName' => 'Accounting']);
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

    private function sysAdmin(): Staffusers
    {
        return $this->staffWithRole('SysAdmin');
    }

    #[Test]
    public function the_catalog_lists_the_obligation_text_beside_the_office(): void
    {
        Clearancerequirements::create([
            'officeId' => 1,
            'requirementName' => 'Return borrowed books',
        ]);

        $rows = $this->actingAs($this->sysAdmin())
            ->get(route('admin.reference-data.clearance-requirements'))
            ->assertOk()
            ->viewData('page')['props']['requirements']['data'];

        $this->assertSame('Return borrowed books', $rows[0]['requirementName']);
        $this->assertSame('Registrar', $rows[0]['office']['officeName']);
    }

    #[Test]
    public function a_requirement_cannot_be_created_without_its_text(): void
    {
        $before = Clearancerequirements::count();

        $this->actingAs($this->sysAdmin())->post(
            route('admin.reference-data.clearance-requirements.store'),
            ['officeId' => 1]
        )->assertSessionHasErrors('requirementName');

        $this->assertSame($before, Clearancerequirements::count());
    }

    #[Test]
    public function an_office_can_carry_more_than_one_requirement_line(): void
    {
        $sysAdmin = $this->sysAdmin();

        foreach (['Return borrowed books', 'Clear the laboratory uniform'] as $text) {
            $this->actingAs($sysAdmin)->post(
                route('admin.reference-data.clearance-requirements.store'),
                ['officeId' => 1, 'requirementName' => $text]
            )->assertSessionHasNoErrors();
        }

        // Before the text column, a second Registrar line meant adding an office.
        $this->assertSame(
            ['Return borrowed books', 'Clear the laboratory uniform'],
            Clearancerequirements::where('officeId', 1)->orderBy('clearanceRequirementId')->pluck('requirementName')->all()
        );
    }

    #[Test]
    public function the_text_of_an_existing_line_can_be_corrected(): void
    {
        $requirement = Clearancerequirements::create([
            'officeId' => 2,
            'requirementName' => 'Provisional wording',
        ]);

        $this->actingAs($this->sysAdmin())->patch(
            route('admin.reference-data.clearance-requirements.update', $requirement->clearanceRequirementId),
            ['officeId' => 2, 'requirementName' => 'Settle outstanding fees']
        )->assertSessionHasNoErrors();

        $this->assertSame('Settle outstanding fees', $requirement->fresh()->requirementName);
    }

    #[Test]
    public function searching_matches_the_requirement_text_not_only_the_office(): void
    {
        Clearancerequirements::create(['officeId' => 1, 'requirementName' => 'Return borrowed books']);
        Clearancerequirements::create(['officeId' => 2, 'requirementName' => 'Settle outstanding fees']);

        $rows = $this->actingAs($this->sysAdmin())
            ->get(route('admin.reference-data.clearance-requirements', ['search' => 'books']))
            ->assertOk()
            ->viewData('page')['props']['requirements']['data'];

        $this->assertCount(1, $rows);
        $this->assertSame('Return borrowed books', $rows[0]['requirementName']);
    }

    #[Test]
    public function a_desk_without_the_reference_data_permission_is_refused(): void
    {
        $registrar = $this->staffWithRole('RegistrarApprover');

        $this->actingAs($registrar)
            ->get(route('admin.reference-data.clearance-requirements'))
            ->assertForbidden();

        $this->actingAs($registrar)->post(
            route('admin.reference-data.clearance-requirements.store'),
            ['officeId' => 1, 'requirementName' => 'Injected line']
        )->assertForbidden();

        $this->assertNull(Clearancerequirements::query()->where('requirementName', 'Injected line')->first());
    }
}
