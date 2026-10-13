<?php

namespace Tests\Feature\Admin;

use App\Enums\StaffRole;
use App\Models\Staffusers;
use App\Support\ReferenceDataSections;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The fourteen catalog screens, each asked the question its own save asks.
 *
 * The audit register closed lane 2 by printing the guard line of every *write* route, and left one
 * line open: "the reference-catalog GET screens are untested". Measured 2026-10-13 that is exactly
 * ten of fourteen — `courses`, `majors`, `curriculums`, `subjects`, `terms`, `fee-types`,
 * `scholarship-types`, `rooms`, `blocks` and `admission-requirements` have had their POST, PATCH and
 * DELETE called by a test, and their GET never requested. So a screen could have been registered
 * asking a neighbouring screen's ability — the copy-paste that makes a desk's own list refuse to
 * open while its save works — and the suite would not have noticed, because nothing ever loaded it.
 *
 * Each screen is gated by `ReferenceDataSections::PERMISSIONS`, the same map the hub's tiles and the
 * launcher's Administration entry read, so these tests pin three things at once: the controller asks
 * the right this map names, the map covers every route the hub can link, and no screen opens for an
 * account the chrome never offers it.
 */
class ReferenceDataScreenAccessTest extends TestCase
{
    use RefreshDatabase;

    private int $curriculumId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // One screen takes a model bound in its path, so it needs a row to point at. The chain is
        // unit → course → curriculum because the FKs say so, and `requires*Exam` are NOT NULL without
        // a default. Inserted by hand rather than through a seeder for the reason DEPLOYMENT.md §7
        // records: a name-keyed seeder hands out whatever the counter says, and this test needs *a*
        // curriculum, not number one.
        $unitId = (int) DB::table('academicunits')->insertGetId([
            'unitName' => 'Test College',
            'unitType' => 'college',
        ]);

        $courseId = (int) DB::table('courses')->insertGetId([
            'unitId' => $unitId,
            'courseName' => 'Bachelor of Test Science',
            'courseCode' => 'BTS',
            'requiresEntranceExam' => 0,
            'requiresRetentionExam' => 0,
        ]);

        $this->curriculumId = (int) DB::table('curriculums')->insertGetId([
            'courseId' => $courseId,
            'effectiveYear' => '2026-06-01',
            'curriculumName' => 'BTS 2026 Curriculum',
        ]);
    }

    /** The screen's URL, with the one bound parameter the route table asks for. */
    private function uri(string $route): string
    {
        return $route === 'admin.reference-data.curriculum-subjects'
            ? route($route, ['curriculum' => $this->curriculumId])
            : route($route);
    }

    private function staff(array $spatieRoles, array $extraPermissions = []): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-RD-'.uniqid(),
            'username' => 'rd_'.uniqid(),
            'email' => 'rd_'.uniqid().'@example.com',
        ]);

        foreach ($spatieRoles as $role) {
            $staff->assignRole($role);
        }
        if ($extraPermissions !== []) {
            $staff->givePermissionTo($extraPermissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    #[Test]
    public function every_catalog_screen_opens_for_the_desk_that_holds_its_own_right(): void
    {
        foreach (ReferenceDataSections::PERMISSIONS as $route => $permission) {
            $staff = $this->staff([], ['refdata.view', $permission]);

            // Deliberately not SysAdmin: Spatie's Gate::before grants that role everything before a
            // policy body runs, so a SysAdmin passing proves nothing about the ability the screen asks.
            $this->actingAs($staff)
                ->get($this->uri($route))
                ->assertOk();
        }
    }

    #[Test]
    public function a_screen_refuses_an_account_that_holds_nothing_but_the_hub_right(): void
    {
        $staff = $this->staff(['Staff']);

        $this->assertTrue($staff->hasPermissionTo('refdata.view'),
            'The Staff role is expected to hold refdata.view; if that changed, this test is measuring the wrong account.');

        foreach (array_keys(ReferenceDataSections::PERMISSIONS) as $route) {
            $this->actingAs($staff)
                ->get($this->uri($route))
                ->assertForbidden();
        }
    }

    #[Test]
    public function one_desk_right_opens_its_own_screen_and_no_other(): void
    {
        // The symmetry the map promises: a right is scoped to its catalog, so a desk handed Rooms can
        // not read Blocks. If a controller method ever asks a neighbour's ability, the mismatch shows
        // up here rather than as a screen that opens for the wrong office.
        $staff = $this->staff([], ['refdata.view', 'refdata.rooms.manage']);

        $this->actingAs($staff)->get($this->uri('admin.reference-data.rooms'))->assertOk();

        foreach (array_keys(ReferenceDataSections::PERMISSIONS) as $route) {
            if ($route === 'admin.reference-data.rooms') {
                continue;
            }

            $this->actingAs($staff)->get($this->uri($route))->assertForbidden();
        }
    }

    #[Test]
    public function the_hub_is_offered_only_where_there_is_something_to_maintain(): void
    {
        // Measured on live `ems` 2026-10-13: Admin, Dean, OfficeHead and Staff hold refdata.view and no
        // manage right, so the chrome hides the hub while the door — which answers to refdata.view
        // alone, pinned in PermissionMatrixTest — stays open. That is a decision, not an oversight:
        // the page renders an EmptyState naming the reason, so the desk is not left on a blank grid.
        $viewer = $this->staff(['Staff']);
        $approver = $this->staff(['RegistrarApprover']);

        $this->assertFalse($approver->hasPermissionTo('refdata.courses.manage'),
            'RegistrarApprover is expected to maintain one catalog only; the test below depends on that.');

        $this->actingAs($viewer)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('can.refdataHub', false));

        $this->actingAs($viewer)
            ->get(route('admin.reference-data.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('manageable', []));

        $this->actingAs($approver)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('can.refdataHub', true));

        $this->actingAs($approver)
            ->get(route('admin.reference-data.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('manageable', ['admin.reference-data.grade-scale']));
    }

    #[Test]
    public function every_route_the_hub_map_names_is_a_route_the_router_has(): void
    {
        // The map is used to build links; a renamed route there is a tile that throws when clicked.
        $missing = array_values(array_filter(
            array_keys(ReferenceDataSections::PERMISSIONS),
            fn (string $route) => ! Route::getRoutes()->hasNamedRoute($route)
        ));

        $this->assertSame([], $missing, 'ReferenceDataSections links routes the router does not have: '.implode(', ', $missing));

        // And the other direction: a catalog screen with no entry in the map would be unlinked from
        // the hub forever, which is how the permission-catalog screen ended up orphaned.
        $screens = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => is_string($r->getName()) && str_starts_with((string) $r->getName(), 'admin.reference-data.'))
            ->map(fn ($r) => (string) $r->getName())
            ->reject(fn (string $name) => in_array($name, ['admin.reference-data.index'], true))
            ->reject(fn (string $name) => str_ends_with($name, '.store') || str_ends_with($name, '.update') || str_ends_with($name, '.destroy'))
            ->values()
            ->all();

        $unmapped = array_values(array_diff($screens, array_keys(ReferenceDataSections::PERMISSIONS)));

        $this->assertSame([], $unmapped,
            'Catalog screens the hub cannot link because the map does not name them: '.implode(', ', $unmapped));
    }
}
