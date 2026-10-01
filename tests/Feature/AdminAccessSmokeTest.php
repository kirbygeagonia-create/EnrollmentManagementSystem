<?php

namespace Tests\Feature;

use App\Enums\OfficeId;
use App\Models\Offices;
use App\Models\Staffusers;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Every module index page must answer 200 for a super-user. This is the sweep
 * that catches a desk whose screen dies on a column, a relation or a view
 * compile — failures that no single-desk test notices because each one only
 * looks at its own module.
 *
 * The admin account is created here rather than looked up. It used to resolve
 * `staff8` from the developer's own MySQL database, so the whole sweep passed
 * locally and threw ModelNotFoundException in CI, where the migrated schema has
 * no such row.
 */
class AdminAccessSmokeTest extends TestCase
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

        // Several desks filter their queue on an office row, and the launcher
        // resolves the caller's own office, so the sweep needs the register the
        // seeded roles point at.
        foreach (OfficeId::cases() as $office) {
            Offices::firstOrCreate(['officeId' => $office->value], ['officeName' => $office->name]);
        }
    }

    private function admin(): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => Offices::value('officeId'),
            'role' => 'admin',
            'employeeNo' => 'EMP-SMOKE-'.uniqid(),
            'username' => 'smoke_admin_'.uniqid(),
            'email' => 'smoke_admin_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();

        $staff->assignRole('SysAdmin');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    public function test_admin_can_access_all_module_index_pages(): void
    {
        $admin = $this->admin();

        $routes = [
            'dashboard',
            'admission.index',
            'exam.index',
            'evaluation.index',
            'assessment.index',
            'accounting.index',
            'clearance.index',
            'blocking.index',
            'registrar.index',
            'clinic.index',
            'id.index',
            'admin.reference-data.index',
            'admin.users.index',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($admin)->get(route($route));
            $this->assertEquals(200, $response->status(), "Route [{$route}] returned {$response->status()} for admin");
        }
    }
}
