<?php

namespace Tests\Feature\Accounting;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\StaffRole;
use App\Enums\StudentType;
use App\Enums\WorkflowStepStatus;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Offices;
use App\Models\Payments;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Models\Students;
use App\Policies\PaymentPolicy;
use App\Services\WorkflowService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * An enrollment whose fees are covered in full by a grant or a waiver owes nothing,
 * and the payment gate deliberately refuses a zero balance — so nothing could close
 * the account, and it stalled in `assessed` before Registrar. These tests pin the
 * settlement path that replaced that deadlock: no cash moves and no Official
 * Receipt is issued, but the Accounting signature is the one a payment would earn.
 */
class ZeroBalanceSettlementTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $cashier;

    private Academicterms $term;

    private Courses $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        // An enrollment workflow writes one step per office box, so those offices
        // have to exist before the steps can be created.
        foreach ([1, 2, 3, 4, 5, 11, 22] as $officeId) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => 'Office '.$officeId]);
        }

        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitName' => 'College of Computer Studies',
            'unitType' => 'college',
        ]);

        $year = Academicyears::create([
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-03-31',
        ]);

        $this->term = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);

        $this->course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        $this->cashier = $this->staffInOffice(OfficeId::Accounting->value, 'AccountingStaff');
    }

    private function staffInOffice(int $officeId, string $spatieRole): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => $officeId,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-SETTLE-'.uniqid(),
            'username' => 'settle_'.$officeId.'_'.uniqid(),
            'email' => 'settle_'.$officeId.'_'.uniqid().'@example.com',
        ]);
        $staff->assignRole($spatieRole);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    /**
     * A finalized enrollment whose assessed fees are covered in full.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createAssessment(array $overrides = []): Studentassessments
    {
        $student = Students::create([
            'schoolIdNumber' => 'SETTLE-'.uniqid(),
            'lastName' => 'Settle',
            'firstName' => 'Student',
            'middleName' => 'P',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'email' => 'settle_student_'.uniqid().'@example.com',
            'username' => 'settle_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $this->course->courseId,
            'termId' => $this->term->termId,
            'yearLevel' => 1,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Assessed,
            'evaluatedBy' => $this->cashier->userId,
            'enrolledDate' => now(),
            'formIssuedDate' => now()->toDateString(),
        ]);

        return Studentassessments::create(array_merge([
            'enrollmentId' => $enrollment->enrollmentId,
            'totalAssessedAmount' => 5000,
            'totalScholarshipCoverage' => 5000,
            'totalWaived' => 0,
            'remainingBalance' => 0,
            'assessmentDate' => now()->toDateString(),
        ], $overrides));
    }

    /**
     * Bring the workflow to the step this desk signs: steps must be completed in
     * order (BR13), and Accounting sits behind Guidance and Assessment.
     */
    private function advanceWorkflowToAccounting(Enrollments $enrollment): void
    {
        $workflow = app(WorkflowService::class)->createWorkflow($enrollment);

        $accountingStep = $workflow->workflowsteps()->where('officeId', OfficeId::Accounting->value)->first();

        $workflow->workflowsteps()
            ->where('stepOrder', '<', $accountingStep->stepOrder)
            ->update([
                'stepStatus' => WorkflowStepStatus::Completed->value,
                'signedBy' => $this->cashier->userId,
                'signedDate' => now(),
            ]);
    }

    #[Test]
    public function a_fully_covered_account_is_invisible_until_the_desk_switches_queue(): void
    {
        $assessment = $this->createAssessment();

        $this->actingAs($this->cashier)
            ->get(route('accounting.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/Index')
                ->has('assessments.data', 0)
                ->where('queueCounts.due', 0)
                ->where('queueCounts.no_balance', 1)
            );

        $this->actingAs($this->cashier)
            ->get(route('accounting.index', ['queue' => 'no_balance']))
            ->assertInertia(fn ($page) => $page
                ->has('assessments.data', 1)
                ->where('assessments.data.0.assessmentId', $assessment->assessmentId)
            );
    }

    #[Test]
    public function the_desk_can_settle_an_account_that_owes_nothing(): void
    {
        $assessment = $this->createAssessment();
        $enrollment = $assessment->enrollment;
        $this->advanceWorkflowToAccounting($enrollment);

        $this->actingAs($this->cashier)
            ->post(route('accounting.settle', $assessment))
            ->assertRedirect(route('accounting.index'))
            ->assertSessionHas('success');

        $this->assertSame(EnrollmentStatus::Paid, $enrollment->fresh()->enrollmentStatus);

        // Nothing was collected, so no receipt may exist: an OR number is a
        // controlled document and settling must not consume one.
        $this->assertSame(0, Payments::where('enrollmentId', $enrollment->enrollmentId)->count());

        $step = $enrollment->fresh()->enrollmentworkflow
            ->workflowsteps()->where('officeId', OfficeId::Accounting->value)->first();
        $this->assertSame(WorkflowStepStatus::Completed, $step->stepStatus);
        $this->assertSame($this->cashier->userId, $step->signedBy);
    }

    #[Test]
    public function settling_refuses_an_account_that_still_owes(): void
    {
        $assessment = $this->createAssessment([
            'totalScholarshipCoverage' => 1000,
            'remainingBalance' => 4000,
        ]);

        $this->actingAs($this->cashier)
            ->post(route('accounting.settle', $assessment))
            ->assertForbidden();

        $this->assertSame(EnrollmentStatus::Assessed, $assessment->fresh()->enrollment->enrollmentStatus);
        $this->assertSame(0, Payments::where('enrollmentId', $assessment->enrollmentId)->count());
    }

    #[Test]
    public function the_record_and_settle_policies_are_opposites(): void
    {
        $covered = $this->createAssessment();
        $owing = $this->createAssessment(['totalScholarshipCoverage' => 1000, 'remainingBalance' => 4000]);

        // Collecting needs a balance; closing needs none. Neither desk action may
        // cover the other's case, or a zero-balance account could be "paid" with
        // cash that was never tendered.
        //
        // Asserted on the policy so the two are checked against the same pair of
        // assessments in one place. `payment.recordAtDesk` no longer shares a
        // permission name (the collision X-1 worked around here used to make the
        // policy body unreachable through the gate), and both desk actions are
        // exercised over HTTP below.
        $policy = new PaymentPolicy;

        $this->assertFalse($policy->record($this->cashier, $covered));
        $this->assertTrue($policy->settle($this->cashier, $covered));

        $this->assertTrue($policy->record($this->cashier, $owing));
        $this->assertFalse($policy->settle($this->cashier, $owing));
    }

    #[Test]
    public function only_the_accounting_desk_may_settle(): void
    {
        $assessment = $this->createAssessment();

        // OfficeHead holds payment.record, so the permission alone cannot be the
        // guard — the office scope is what keeps this a cashier-desk action.
        $registrar = $this->staffInOffice(OfficeId::Registrar->value, 'OfficeHead');

        $this->actingAs($registrar)
            ->post(route('accounting.settle', $assessment))
            ->assertForbidden();

        $this->assertSame(EnrollmentStatus::Assessed, $assessment->fresh()->enrollment->enrollmentStatus);
    }

    #[Test]
    public function only_the_accounting_desk_may_record_a_payment(): void
    {
        $owing = $this->createAssessment([
            'totalScholarshipCoverage' => 1000,
            'remainingBalance' => 4000,
        ]);

        // §28 X-1: this requester holds `payment.record`, so while the ability shared
        // that name Gate::before granted the call and PaymentPolicy::record's office
        // scope never ran — the request went on into validation. The renamed ability
        // is what makes the office boundary real.
        $registrar = $this->staffInOffice(OfficeId::Registrar->value, 'OfficeHead');

        $this->assertTrue($registrar->hasPermissionTo('payment.record'));

        $this->actingAs($registrar)
            ->post(route('accounting.payment.record', $owing), [
                'orNumber' => 'X1-OR-'.uniqid(),
                'amount' => 4000,
                'paymentMode' => 'cash',
                'paymentDate' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertSame(EnrollmentStatus::Assessed, $owing->fresh()->enrollment->enrollmentStatus);
        $this->assertSame(0, Payments::where('enrollmentId', $owing->enrollmentId)->count());
    }

    #[Test]
    public function the_settlement_action_is_offered_only_to_staff_who_may_use_it(): void
    {
        $assessment = $this->createAssessment();

        $this->actingAs($this->cashier)
            ->get(route('accounting.show', $assessment))
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/Show')
                ->where('can.settleNoBalance', true)
                ->where('outstandingBalance', 0)
            );

        $registrar = $this->staffInOffice(OfficeId::Registrar->value, 'OfficeHead');

        $this->actingAs($registrar)
            ->get(route('accounting.show', $assessment))
            ->assertInertia(fn ($page) => $page->where('can.settleNoBalance', false));
    }
}
