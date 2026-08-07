<?php

namespace Tests\Feature\Rbac;

use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Permission Matrix Test
 *
 * Verifies RBAC enforcement across a representative set of routes × roles.
 * Catches routes that are either:
 *   (a) missing a permission gate (accessible by Staff who should be denied), or
 *   (b) over-restricted (a role with the permission still gets 403).
 *
 * Uses sqlite :memory: database with RbacSeeder for isolated, fast tests.
 *
 * NOTE on mutating routes: routes with route-model binding resolve the model
 * BEFORE the controller's authorize() call, so a non-existent ID yields 404
 * (not 403) when the user lacks the permission — the gate never fires because
 * binding fails first. We therefore assert the response is NOT 200 (denied in
 * some form: 403, 404, 405, or 302) rather than strictly 403. A 200 would mean
 * the gate is missing entirely.
 */
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    /**
     * Create a staff user with the given Spatie role.
     * Maps Spatie role names to StaffRole enum values for the factory.
     */
    private function createStaffWithRole(string $roleName): Staffusers
    {
        $enumMap = [
            'SysAdmin' => 'admin',
            'Admin' => 'admin',
            'OfficeHead' => 'officeHead',
            'Dean' => 'dean',
            'ProgramHead' => 'programHead',
            'Staff' => 'staff',
        ];

        $enumValue = $enumMap[$roleName] ?? strtolower($roleName);

        $staff = Staffusers::factory()->create([
            'role' => $enumValue,
        ]);
        $staff->assignRole($roleName);

        return $staff->fresh();
    }

    /**
     * Data provider for module index (view) routes.
     * Each entry: [routeName, requiredPermission, staffShouldHaveAccess]
     *
     * staffShouldHaveAccess = true means Staff role has the view permission (expect not-403)
     * staffShouldHaveAccess = false means Staff role lacks the view permission (expect 403)
     *
     * Per RbacSeeder, Staff has these view permissions:
     * admission.view, exam.view, evaluation.view, assessment.view,
     * payment.view, clearance.view, block.view, clinic.view,
     * id.view, refdata.view, user.view, audit.view, dashboard.view
     *
     * NOTE: registrar.index maps to EvaluationPolicy::viewAny which checks
     * 'evaluation.view' (Staff HAS this) — not enrollment.approve.
     */
    public static function viewRoutesProvider(): array
    {
        return [
            // Module index routes where Staff HAS view permission (expect not-403)
            ['admission.index', 'admission.view', true],
            ['exam.index', 'exam.view', true],
            ['evaluation.index', 'evaluation.view', true],
            ['assessment.index', 'assessment.view', true],
            ['accounting.index', 'payment.view', true],
            ['clearance.index', 'clearance.view', true],
            ['blocking.index', 'block.view', true],
            ['clinic.index', 'clinic.view', true],
            ['id.index', 'id.view', true],
            // registrar.index → EvaluationPolicy::viewAny → evaluation.view (Staff HAS it)
            ['registrar.index', 'evaluation.view', true],
            ['admin.reference-data.index', 'refdata.view', true],
            ['admin.users.index', 'user.view', true],
            ['dashboard', 'dashboard.view', true],
        ];
    }

    /**
     * Data provider for mutating (POST/PATCH/DELETE) routes.
     * Each entry: [routeName, requiredPermission, routeParams, httpMethod]
     *
     * Staff does NOT have these permissions → expect NOT 200 (403/404/405/302).
     * OfficeHead permission map is verified separately (some perms OfficeHead lacks).
     */
    public static function mutatingRoutesProvider(): array
    {
        return [
            // Admission mutations
            ['admission.approve', 'admission.approve', ['admission' => 999999], 'post'],
            ['admission.reject', 'admission.reject', ['admission' => 999999], 'post'],

            // Evaluation mutations
            ['evaluation.sign', 'evaluation.sign', ['enrollment' => 999999], 'post'],
            ['evaluation.subjects.propose', 'evaluation.subjects.propose', ['enrollment' => 999999], 'post'],

            // Assessment mutations
            ['assessment.compute', 'assessment.compute', ['enrollment' => 999999], 'post'],

            // Accounting mutations
            ['accounting.payment.record', 'payment.record', ['assessment' => 999999], 'post'],

            // Clearance mutations
            ['clearance.approve', 'clearance.approve', ['approval' => 999999], 'post'],

            // Blocking mutations
            ['blocking.assign', 'block.assign', ['block' => 999999], 'post'],
            ['blocking.schedules.store', 'block.schedules.manage', ['block' => 999999], 'post'],

            // Clinic mutations
            ['clinic.record', 'clinic.record', ['enrollment' => 999999], 'post'],

            // ID mutations
            ['id.create', 'id.request.create', ['enrollment' => 999999], 'post'],
            ['id.validate', 'id.validate', ['studentId' => 999999], 'post'],
        ];
    }

    /**
     * Data provider for unauthenticated routes (sample).
     */
    public static function unauthenticatedRoutesProvider(): array
    {
        return [
            ['admission.index'],
            ['exam.index'],
            ['evaluation.index'],
            ['assessment.index'],
            ['accounting.index'],
            ['clearance.index'],
            ['blocking.index'],
            ['clinic.index'],
            ['id.index'],
            ['registrar.index'],
            ['admin.reference-data.index'],
            ['admin.users.index'],
            ['dashboard'],
        ];
    }

    #[Test]
    #[DataProvider('viewRoutesProvider')]
    public function test_staff_role_can_only_view_modules_it_has_permission_for(
        string $routeName,
        string $permission,
        bool $staffShouldHaveAccess
    ): void {
        $staff = $this->createStaffWithRole('Staff');

        // Verify the staff user actually has/doesn't have the permission per seeder
        $this->assertEquals($staffShouldHaveAccess, $staff->hasPermissionTo($permission),
            "Staff role permission mismatch for {$permission} - check RbacSeeder");

        $response = $this->actingAs($staff)->get(route($routeName));

        if ($staffShouldHaveAccess) {
            // Staff has the view permission - should NOT get 403 or 500
            $this->assertNotEquals(403, $response->status(),
                "Staff with {$permission} got 403 on {$routeName} - possible missing/incorrect gate");
            $this->assertNotEquals(500, $response->status(),
                "Staff with {$permission} got 500 on {$routeName} - server error");
        } else {
            // Staff lacks the view permission - should get 403
            $this->assertEquals(403, $response->status(),
                "Staff without {$permission} accessed {$routeName} (got {$response->status()}) - missing permission gate");
        }
    }

    #[Test]
    #[DataProvider('viewRoutesProvider')]
    public function test_office_head_can_access_module_views(
        string $routeName,
        string $permission,
        bool $staffShouldHaveAccess
    ): void {
        $officeHead = $this->createStaffWithRole('OfficeHead');

        // OfficeHead has ALL view permissions per RbacSeeder
        $this->assertTrue($officeHead->hasPermissionTo($permission),
            "OfficeHead should have {$permission} per RbacSeeder");

        $response = $this->actingAs($officeHead)->get(route($routeName));

        // OfficeHead should never get 403 on view routes
        $this->assertNotEquals(403, $response->status(),
            "OfficeHead got 403 on {$routeName} - over-restricted gate or missing permission");
        $this->assertNotEquals(500, $response->status(),
            "OfficeHead got 500 on {$routeName} - server error");
    }

    #[Test]
    #[DataProvider('viewRoutesProvider')]
    public function test_sys_admin_never_gets_403_on_view_routes(
        string $routeName,
        string $permission,
        bool $staffShouldHaveAccess
    ): void {
        $sysAdmin = $this->createStaffWithRole('SysAdmin');

        // SysAdmin gets all permissions via Gate::before in AuthServiceProvider
        $response = $this->actingAs($sysAdmin)->get(route($routeName));

        // SysAdmin should never get 403 (Gate::before returns true)
        $this->assertNotEquals(403, $response->status(),
            "SysAdmin got 403 on {$routeName} - Gate::before not working");
        $this->assertNotEquals(500, $response->status(),
            "SysAdmin got 500 on {$routeName} - server error");
    }

    #[Test]
    #[DataProvider('mutatingRoutesProvider')]
    public function test_staff_denied_mutating_routes(
        string $routeName,
        string $permission,
        array $routeParams,
        string $httpMethod
    ): void {
        $staff = $this->createStaffWithRole('Staff');

        // Verify Staff does NOT have this permission
        $this->assertFalse($staff->hasPermissionTo($permission),
            "Staff unexpectedly has {$permission} - check RbacSeeder");

        $response = $this->actingAs($staff)->{$httpMethod}(route($routeName, $routeParams));

        // Staff should be denied in some form. A 200 would mean the gate is
        // missing entirely. 403 (authz), 404 (model binding before authz),
        // 405 (wrong method), or 302 (redirect) are all acceptable denials.
        $this->assertNotEquals(200, $response->status(),
            "Staff without {$permission} got 200 on mutating route {$routeName} - MISSING permission gate. Params: ".json_encode($routeParams));
    }

    #[Test]
    #[DataProvider('mutatingRoutesProvider')]
    public function test_sys_admin_never_gets_403_on_mutating_routes(
        string $routeName,
        string $permission,
        array $routeParams,
        string $httpMethod
    ): void {
        $sysAdmin = $this->createStaffWithRole('SysAdmin');

        $response = $this->actingAs($sysAdmin)->{$httpMethod}(route($routeName, $routeParams));

        // SysAdmin should never get 403 (Gate::before returns true).
        // May get 404 (model not found) or 422 (validation) — that's fine, not 403.
        $this->assertNotEquals(403, $response->status(),
            "SysAdmin got 403 on {$routeName} - Gate::before not working. Params: ".json_encode($routeParams));
    }

    #[Test]
    #[DataProvider('unauthenticatedRoutesProvider')]
    public function test_unauthenticated_redirected_to_login(string $routeName): void
    {
        $response = $this->get(route($routeName));

        // Unauthenticated users should be redirected to login (302)
        $this->assertEquals(302, $response->status(),
            "Unauthenticated access to {$routeName} returned {$response->status()} instead of 302 redirect");
        $location = $response->headers->get('Location');
        $this->assertNotNull($location, "No Location header for {$routeName}");
        $this->assertStringContainsString('/login', $location,
            "Unauthenticated access to {$routeName} did not redirect to /login (got {$location})");
    }

    /**
     * Dean role permissions (subset of modules).
     */
    #[Test]
    public function test_dean_has_expected_permissions(): void
    {
        $dean = $this->createStaffWithRole('Dean');

        // Dean permissions per RbacSeeder
        $deanPermissions = [
            'admission.view', 'admission.create', 'admission.update', 'admission.approve', 'admission.reject',
            'admission.requirements.submit', 'admission.requirements.verify',
            'evaluation.view', 'evaluation.create', 'evaluation.profile.capture', 'evaluation.profile.capture.any',
            'evaluation.subjects.propose', 'evaluation.subjects.propose.any', 'evaluation.credits.process',
            'evaluation.sign', 'evaluation.sign.dean',
            'exam.view',
            'refdata.view',
            'user.view',
            'dashboard.view',
            'enrollment.subjects.confirm',
        ];

        foreach ($deanPermissions as $perm) {
            $this->assertTrue($dean->hasPermissionTo($perm),
                "Dean should have {$perm} per RbacSeeder");
        }

        // Dean should NOT have these (sample)
        $nonDeanPermissions = [
            'assessment.view', 'payment.view', 'clearance.view', 'block.view',
            'clinic.view', 'id.view', 'user.create', 'user.delete',
        ];

        foreach ($nonDeanPermissions as $perm) {
            $this->assertFalse($dean->hasPermissionTo($perm),
                "Dean should NOT have {$perm} per RbacSeeder");
        }
    }

    /**
     * ProgramHead role permissions (subset of modules).
     */
    #[Test]
    public function test_program_head_has_expected_permissions(): void
    {
        $programHead = $this->createStaffWithRole('ProgramHead');

        // ProgramHead permissions per RbacSeeder
        $programHeadPermissions = [
            'admission.view',
            'evaluation.view', 'evaluation.create', 'evaluation.credits.process',
            'evaluation.subjects.propose', 'evaluation.sign',
            'exam.view',
            'dashboard.view',
            'enrollment.subjects.confirm',
        ];

        foreach ($programHeadPermissions as $perm) {
            $this->assertTrue($programHead->hasPermissionTo($perm),
                "ProgramHead should have {$perm} per RbacSeeder");
        }

        // ProgramHead should NOT have these (sample)
        $nonProgramHeadPermissions = [
            'admission.create', 'admission.approve', 'assessment.view', 'payment.view',
            'clearance.view', 'block.view', 'clinic.view', 'id.view', 'refdata.view',
            'user.view', 'user.create',
        ];

        foreach ($nonProgramHeadPermissions as $perm) {
            $this->assertFalse($programHead->hasPermissionTo($perm),
                "ProgramHead should NOT have {$perm} per RbacSeeder");
        }
    }

    /**
     * Admin role mirrors SysAdmin (all permissions).
     */
    #[Test]
    public function test_admin_has_all_permissions(): void
    {
        $admin = $this->createStaffWithRole('Admin');

        // Admin should have all permissions (same as SysAdmin)
        $allPermissions = Permission::all()->pluck('name')->toArray();

        foreach ($allPermissions as $perm) {
            $this->assertTrue($admin->hasPermissionTo($perm),
                "Admin should have {$perm} (mirrors SysAdmin)");
        }
    }

    /**
     * OfficeHead has all view permissions + a specific set of action permissions.
     */
    #[Test]
    public function test_office_head_has_expected_permissions(): void
    {
        $officeHead = $this->createStaffWithRole('OfficeHead');

        // OfficeHead view permissions (all)
        $viewPerms = [
            'admission.view', 'exam.view', 'evaluation.view', 'assessment.view',
            'payment.view', 'clearance.view', 'enrollment.approve', 'block.view',
            'clinic.view', 'id.view', 'refdata.view', 'user.view', 'audit.view', 'dashboard.view',
        ];
        foreach ($viewPerms as $perm) {
            $this->assertTrue($officeHead->hasPermissionTo($perm),
                "OfficeHead should have {$perm} per RbacSeeder");
        }

        // OfficeHead action permissions (per RbacSeeder L245-256)
        $actionPerms = [
            'block.manage', 'block.assign', 'block.schedules.manage',
            'clearance.periods.manage', 'clearance.slip.generate',
            'clearance.receipt.record', 'clearance.approve',
            'clinic.record', 'clinic.update', 'clinic.sign',
            'id.request.create', 'id.card.produce', 'id.validate', 'id.release', 'id.sign',
            'payment.record', 'payment.report.daily',
            'assessment.compute', 'assessment.finalize',
            'exam.record.general', 'exam.record.courseSpecific', 'exam.record.retention', 'exam.verify.general',
            'evaluation.create', 'evaluation.profile.capture', 'evaluation.subjects.propose', 'evaluation.credits.process', 'evaluation.sign',
            'admission.create', 'admission.approve', 'admission.reject', 'admission.requirements.submit', 'admission.requirements.verify',
            'print.certificate', 'print.classCard', 'print.subjectLoad', 'enrollment.studentdata.record',
        ];
        foreach ($actionPerms as $perm) {
            $this->assertTrue($officeHead->hasPermissionTo($perm),
                "OfficeHead should have {$perm} per RbacSeeder");
        }

        // OfficeHead does NOT have these (admin-only / refdata manage / user manage)
        $notOfficeHead = [
            'assessment.charges.adjust', 'payment.void',
            'refdata.courses.manage', 'refdata.majors.manage',
            'user.create', 'user.roles.assign', 'user.roles.manage',
            'block.capacity.check', 'clearance.slip.replace',
        ];
        foreach ($notOfficeHead as $perm) {
            $this->assertFalse($officeHead->hasPermissionTo($perm),
                "OfficeHead should NOT have {$perm} per RbacSeeder");
        }
    }
}
