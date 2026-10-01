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
 * §28 X-3: `offices` is a table an admin edits, while App\Enums\OfficeId is the
 * authority every office-scope policy and workflow comparison actually reads. The two
 * can only be kept in step by hand, so the screen has to say which side each row is on
 * and refuse the one edit that breaks the code silently — deleting a row whose id the
 * application compares against, or one that staff, clearance requirements or workflow
 * steps still point at (all three are RESTRICT foreign keys, so the database would
 * answer with an error rather than a reason).
 */
class OfficeAuthorityTest extends TestCase
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
        Offices::firstOrCreate(['officeId' => 2], ['officeName' => 'Accounting Office']);
    }

    private function sysAdmin(): Staffusers
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

        $staff->assignRole('SysAdmin');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    #[Test]
    public function the_screen_says_which_desk_each_office_is_wired_to(): void
    {
        Offices::firstOrCreate(['officeId' => 99], ['officeName' => 'Lost and Found']);

        $response = $this->actingAs($this->sysAdmin())
            ->get(route('admin.reference-data.offices'));

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/ReferenceData/Offices')
            ->where('offices.data.0.workflowName', 'Registrar')
            ->where('offices.data.1.workflowName', 'Accounting')
            ->where('offices.data.2.workflowName', null)
            ->where('offices.data.2.staffCount', 0)
            ->where('offices.data.2.requirementCount', 0)
            ->where('offices.data.2.stepCount', 0)
        );
    }

    #[Test]
    public function an_office_the_application_compares_against_cannot_be_deleted(): void
    {
        $response = $this->actingAs($this->sysAdmin())
            ->delete(route('admin.reference-data.offices.destroy', 1));

        $response->assertSessionHas('error', fn (string $message) => str_contains($message, 'OfficeId::Registrar'));
        $this->assertDatabaseHas('offices', ['officeId' => 1]);
    }

    #[Test]
    public function an_office_with_rows_attached_cannot_be_deleted(): void
    {
        $orphan = Offices::firstOrCreate(['officeId' => 99], ['officeName' => 'Lost and Found']);
        Clearancerequirements::create(['officeId' => 99, 'requirementName' => 'Return borrowed equipment']);

        $response = $this->actingAs($this->sysAdmin())
            ->delete(route('admin.reference-data.offices.destroy', $orphan->officeId));

        $response->assertSessionHas('error', fn (string $message) => str_contains($message, 'clearance requirement'));
        $this->assertDatabaseHas('offices', ['officeId' => 99]);
    }

    #[Test]
    public function an_office_that_nothing_uses_can_be_deleted(): void
    {
        $orphan = Offices::firstOrCreate(['officeId' => 98], ['officeName' => 'Retired Committee']);

        $this->actingAs($this->sysAdmin())
            ->delete(route('admin.reference-data.offices.destroy', $orphan->officeId))
            ->assertSessionHas('success', 'Office deleted.');

        $this->assertDatabaseMissing('offices', ['officeId' => 98]);
    }

    #[Test]
    public function creating_an_office_says_it_is_not_yet_a_desk(): void
    {
        $this->actingAs($this->sysAdmin())
            ->post(route('admin.reference-data.offices.store'), ['officeName' => 'Alumni Liaison'])
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'No desk in the application uses it yet'));

        $this->assertDatabaseHas('offices', ['officeName' => 'Alumni Liaison']);
    }

    #[Test]
    public function renaming_an_office_says_it_changes_no_ones_access(): void
    {
        $this->actingAs($this->sysAdmin())
            ->patch(route('admin.reference-data.offices.update', 2), ['officeName' => 'Cashier’s Office'])
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'changes no one\'s access'));

        $this->assertSame('Cashier’s Office', Offices::find(2)->officeName);
    }
}
