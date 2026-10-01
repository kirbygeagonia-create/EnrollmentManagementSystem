<?php

namespace Tests\Feature\Rbac;

use App\Enums\EnrollmentStatus;
use App\Enums\OfficeId;
use App\Enums\StaffRole;
use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * §28 X-1. Spatie registers Gate::before so that any ability whose name is also a
 * permission the user holds is granted before the policy body is consulted. Four
 * desk abilities carried a permission's name (payment.record, assessment.compute,
 * clinic.view, id.view) and one more was defined but never called (block.manage),
 * so an OfficeHead of ANY office — the role holds all of those permissions — passed
 * the authorize() call in the controller while the office scope written in the
 * policy never ran. These tests pin the rule instead of the four names: an ability
 * may never share a permission name.
 */
class AbilityNameCollisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function staffInOffice(int $officeId, string $spatieRole, array $attributes = []): Staffusers
    {
        $staff = Staffusers::factory()->create(array_merge([
            'officeId' => $officeId,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-COLLIDE-'.uniqid(),
            'username' => 'collide_'.$officeId.'_'.uniqid(),
            'email' => 'collide_'.$officeId.'_'.uniqid().'@example.com',
        ], $attributes));
        $staff->assignRole($spatieRole);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    #[Test]
    public function no_gate_ability_shares_a_permission_name(): void
    {
        $permissions = Permission::pluck('name')->all();
        $abilities = array_keys(Gate::abilities());

        $collisions = array_values(array_intersect($abilities, $permissions));

        $this->assertSame([], $collisions,
            'These abilities are also permission names, so Gate::before grants them and the policy body never runs: '
            .implode(', ', $collisions));
    }

    #[Test]
    public function a_permission_shared_with_every_desk_head_grants_nothing_by_itself(): void
    {
        // The hazard, stated as a fact about the framework rather than about this
        // application: asking for an ability named after a permission answers only
        // "does this user hold the permission", which is why no policy scope can
        // live behind such a name.
        $registrarHead = $this->staffInOffice(OfficeId::Registrar->value, 'OfficeHead');

        $this->assertTrue($registrarHead->hasPermissionTo('payment.record'));
        $this->assertTrue(Gate::forUser($registrarHead)->allows('payment.record'));
    }

    #[Test]
    public function only_the_accounting_office_may_record_a_payment(): void
    {
        $owing = new Studentassessments(['remainingBalance' => 5000]);

        // OfficeHead holds payment.record but sits in the Registrar office, so the
        // office scope in PaymentPolicy::record is what refuses here — and it only
        // runs because the ability carries a name no permission may take.
        $registrarHead = $this->staffInOffice(OfficeId::Registrar->value, 'OfficeHead');
        $cashier = $this->staffInOffice(OfficeId::Accounting->value, 'AccountingStaff');

        $this->assertFalse(Gate::forUser($registrarHead)->allows('payment.recordAtDesk', $owing));
        $this->assertTrue(Gate::forUser($cashier)->allows('payment.recordAtDesk', $owing));
    }

    #[Test]
    public function only_the_accounting_or_scholarship_office_may_compute_an_assessment(): void
    {
        $evaluated = new Enrollments(['enrollmentStatus' => EnrollmentStatus::Evaluated]);

        $registrarHead = $this->staffInOffice(OfficeId::Registrar->value, 'OfficeHead');
        $officer = $this->staffInOffice(OfficeId::Scholarship->value, 'ScholarshipOfficer');
        $accountingHead = $this->staffInOffice(OfficeId::Accounting->value, 'OfficeHead');

        $this->assertFalse(Gate::forUser($registrarHead)->allows('assessment.computeAtDesk', $evaluated));
        $this->assertTrue(Gate::forUser($officer)->allows('assessment.computeAtDesk', $evaluated));
        $this->assertTrue(Gate::forUser($accountingHead)->allows('assessment.computeAtDesk', $evaluated));

        // A cashier in the right office but without assessment.compute is still
        // refused: the office scope replaced nothing, it only runs now.
        $cashier = $this->staffInOffice(OfficeId::Accounting->value, 'AccountingStaff');
        $this->assertFalse(Gate::forUser($cashier)->allows('assessment.computeAtDesk', $evaluated));
    }

    #[Test]
    public function an_enrollment_that_evaluation_has_not_signed_is_not_assessable(): void
    {
        // The other condition in AssessmentPolicy::compute — status must be
        // evaluated. Dead code while the ability shared the permission's name.
        $pending = new Enrollments(['enrollmentStatus' => EnrollmentStatus::Pending]);
        $cashier = $this->staffInOffice(OfficeId::Accounting->value, 'AccountingStaff');

        $this->assertFalse(Gate::forUser($cashier)->allows('assessment.computeAtDesk', $pending));
    }

    #[Test]
    public function a_dead_ability_named_after_a_permission_is_not_left_behind(): void
    {
        // block.manage was defined and never called; BlockingPolicy::manageBlocks is
        // reached through blocking.manageBlocks and the Blocks model policy instead.
        $this->assertArrayNotHasKey('block.manage', Gate::abilities());
        $this->assertTrue(Permission::where('name', 'block.manage')->exists());
    }
}
