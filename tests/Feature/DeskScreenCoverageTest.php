<?php

namespace Tests\Feature;

use App\Models\Idrequests;
use App\Models\Staffusers;
use App\Models\Studentclearances;
use App\Models\Students;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Audit lane 3b (2026-10-09) named 18 route actions no test file referenced by name or URI.
 * Six of them are screens a desk opens during the walkthrough, and each one was a route that
 * had only ever been exercised through a redirect or a policy test — never asked of the router
 * as itself. The owner ruled to close these six; the reference-catalog GETs and the two desk
 * PDF downloads are recorded as remaining.
 *
 * The queue-counts case is the one that also carries the scoping rule: the payload must contain
 * a queue exactly when the desk route behind it would open for the same account.
 */
class DeskScreenCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function staffWithRole(string $role, int $officeId = 1): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => $officeId,
            'employeeNo' => 'EMP-DESK-'.uniqid(),
            'username' => 'desk_'.strtolower($role).'_'.uniqid(),
            'email' => 'desk_'.strtolower($role).'_'.uniqid().'@example.com',
        ]);
        $staff->assignRole($role);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    private function createStudent(): Students
    {
        return Students::create([
            'schoolIdNumber' => 'DESK-'.uniqid(),
            'lastName' => 'Delos Santos',
            'firstName' => 'Maria',
            'middleName' => 'C',
            'suffix' => 'N/A',
            'gender' => 'female',
            'birthdate' => '2005-03-04',
            'birthplace' => 'Calamba, Laguna',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'contactNumber' => '09170000000',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'email' => 'desk_student_'.uniqid().'@example.com',
            'username' => 'desk_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);
    }

    #[Test]
    public function the_queue_payload_carries_a_queue_only_where_the_desk_would_open(): void
    {
        $idOfficer = $this->staffWithRole('IdOfficer', 22);
        $admission = $this->staffWithRole('AdmissionOfficer', 6);

        $this->assertFalse($idOfficer->can('viewAny', Studentclearances::class));
        $this->assertTrue($idOfficer->can('viewAny', Idrequests::class));

        $asId = $this->actingAs($idOfficer)->getJson(route('dashboard.queue-counts'))->json('queueCounts');
        $this->assertArrayHasKey('id', $asId, 'The ID officer’s own queue must be counted for them.');
        $this->assertArrayNotHasKey('clearance', $asId, 'A queue whose page 403s must not be counted for this account.');
        $this->assertArrayNotHasKey('admission', $asId);

        $asAdmission = $this->actingAs($admission)->getJson(route('dashboard.queue-counts'))->json('queueCounts');
        $this->assertArrayHasKey('admission', $asAdmission);
        $this->assertArrayNotHasKey('id', $asAdmission);
    }

    #[Test]
    public function the_dashboard_page_shares_the_same_queue_keys_the_json_answers(): void
    {
        $idOfficer = $this->staffWithRole('IdOfficer', 22);

        $counts = $this->actingAs($idOfficer)->getJson(route('dashboard.queue-counts'))->json('queueCounts');

        // Asserted as content, not only as equality with the page: two halves of the same bug
        // agree with each other, and an assertion that only checks they agree proves nothing.
        $this->assertArrayHasKey('id', $counts);
        $this->assertArrayNotHasKey('clearance', $counts);

        $this->actingAs($idOfficer)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('queueKeys', array_keys($counts ?? [])));
    }

    #[Test]
    public function the_entrance_exam_screen_opens_for_the_office_that_scores_it(): void
    {
        $guidance = $this->staffWithRole('GuidanceStaff', 4);
        $idOfficer = $this->staffWithRole('IdOfficer', 22);

        $this->assertTrue($guidance->hasPermissionTo('exam.record.general'));

        $this->actingAs($guidance)
            ->get(route('exam.create', ['stage' => 'entrance', 'type' => 'general']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Exam/Create'));

        $this->actingAs($idOfficer)
            ->get(route('exam.create', ['stage' => 'entrance', 'type' => 'general']))
            ->assertForbidden();
    }

    #[Test]
    public function an_unknown_exam_stage_on_that_screen_is_a_404_not_a_500(): void
    {
        $guidance = $this->staffWithRole('GuidanceStaff', 4);

        // abort_unless() runs before any model is touched, so a typo in the query string answers
        // with "not found" instead of an enum conversion error.
        $this->actingAs($guidance)
            ->get(route('exam.create', ['stage' => 'graduation']))
            ->assertNotFound();
    }

    #[Test]
    public function the_student_360_opens_only_for_an_account_holding_students_view(): void
    {
        $student = $this->createStudent();
        $admin = $this->staffWithRole('SysAdmin');
        $bare = $this->staffWithRole('Staff', 5);

        $this->assertFalse($bare->hasPermissionTo('students.view'));

        $this->actingAs($admin)
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Students/Show')
                ->where('student.studentId', $student->studentId));

        $this->actingAs($bare)
            ->get(route('students.show', $student))
            ->assertForbidden();
    }

    #[Test]
    public function the_quick_search_answers_json_for_a_directory_holder_and_403_for_one_without(): void
    {
        $student = $this->createStudent();
        $admin = $this->staffWithRole('SysAdmin');
        $bare = $this->staffWithRole('Staff', 5);

        $hits = $this->actingAs($admin)
            ->getJson(route('students.quick-search', ['query' => $student->lastName]))
            ->assertOk()
            ->json('results');

        $this->assertNotEmpty($hits, 'A surname typed into the launcher must find the row it matches.');

        $this->actingAs($bare)
            ->getJson(route('students.quick-search', ['query' => $student->lastName]))
            ->assertForbidden();
    }

    #[Test]
    public function the_permission_catalog_screen_is_an_admin_screen(): void
    {
        $admin = $this->staffWithRole('SysAdmin');
        $registrar = $this->staffWithRole('RegistrarDesk');

        $this->actingAs($admin)
            ->get(route('admin.users.permissions'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/UserManagement/Permissions')
                ->has('permissions'));

        $this->actingAs($registrar)
            ->get(route('admin.users.permissions'))
            ->assertForbidden();
    }

    #[Test]
    public function the_settings_screen_opens_for_the_admin_and_refuses_the_desk(): void
    {
        $admin = $this->staffWithRole('SysAdmin');
        $registrar = $this->staffWithRole('RegistrarDesk');

        $this->actingAs($admin)
            ->get(route('admin.users.settings'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/UserManagement/Settings')
                ->has('settings'));

        $this->actingAs($registrar)
            ->get(route('admin.users.settings'))
            ->assertForbidden();
    }
}
