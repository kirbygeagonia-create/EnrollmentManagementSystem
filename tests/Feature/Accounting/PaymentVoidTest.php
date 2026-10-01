<?php

namespace Tests\Feature\Accounting;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\PaymentStatus;
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
use App\Services\WorkflowService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The cashier desk's correction action. §15 states the rule the controller
 * implements — a void re-opens the balance, and a paid enrollment that falls back
 * to a positive balance returns to `assessed` so the Registrar gate closes again —
 * and §15 also states the oddity new code has to respect: PaymentStatus has no
 * `voided` case, so a voided receipt is stored as `pending`. That is the only value
 * nothing else ever writes, and PaymentPolicy::void reads it as "already voided".
 *
 * These tests walk the real path: collect at the desk, then void what was collected.
 */
class PaymentVoidTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $cashier;

    private Academicterms $term;

    private Courses $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

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
            'employeeNo' => 'EMP-VOID-'.uniqid(),
            'username' => 'void_'.$officeId.'_'.uniqid(),
            'email' => 'void_'.$officeId.'_'.uniqid().'@example.com',
        ]);
        $staff->assignRole($spatieRole);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    /**
     * An assessed enrollment with a ₱5,000 assessment and ₱4,000 still owing.
     */
    private function createOwingAssessment(): Studentassessments
    {
        $student = Students::create([
            'schoolIdNumber' => 'VOID-'.uniqid(),
            'lastName' => 'Void',
            'firstName' => 'Student',
            'middleName' => 'Q',
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
            'email' => 'void_student_'.uniqid().'@example.com',
            'username' => 'void_student_'.uniqid(),
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

        $workflow = app(WorkflowService::class)->createWorkflow($enrollment);
        $accountingStep = $workflow->workflowsteps()->where('officeId', OfficeId::Accounting->value)->first();
        $workflow->workflowsteps()
            ->where('stepOrder', '<', $accountingStep->stepOrder)
            ->update([
                'stepStatus' => WorkflowStepStatus::Completed->value,
                'signedBy' => $this->cashier->userId,
                'signedDate' => now(),
            ]);

        return Studentassessments::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'totalAssessedAmount' => 5000,
            'totalScholarshipCoverage' => 1000,
            'totalWaived' => 0,
            'remainingBalance' => 4000,
            'assessmentDate' => now()->toDateString(),
        ]);
    }

    /**
     * Collect cash at the desk the way the screen does it.
     */
    private function collect(Studentassessments $assessment, float $amount): Payments
    {
        $this->actingAs($this->cashier)
            ->post(route('accounting.payment.record', $assessment), [
                'orNumber' => 'VOID-OR-'.uniqid(),
                'amount' => $amount,
                'paymentMode' => 'cash',
                'paymentDate' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        return Payments::where('enrollmentId', $assessment->enrollmentId)->latest('paymentId')->firstOrFail();
    }

    #[Test]
    public function voiding_the_receipt_that_closed_the_account_reopens_the_balance_and_the_enrollment(): void
    {
        $assessment = $this->createOwingAssessment();
        $enrollment = $assessment->enrollment;

        $payment = $this->collect($assessment, 4000);

        // The desk's own bookkeeping first: the account closed and the enrollment
        // moved to paid, so there is something real to take back.
        $this->assertSame(EnrollmentStatus::Paid, $enrollment->fresh()->enrollmentStatus);
        $this->assertEquals(0, (float) $assessment->fresh()->remainingBalance);

        $this->actingAs($this->cashier)
            ->post(route('accounting.payment.void', $payment))
            ->assertRedirect()
            ->assertSessionHas('success');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending, $payment->paymentStatus);

        $assessment->refresh();
        $this->assertEquals(4000, (float) $assessment->remainingBalance);
        $this->assertEquals(4000, $assessment->outstandingBalance());

        // §15: a paid enrollment that owes again falls back to assessed
        $this->assertSame(EnrollmentStatus::Assessed, $enrollment->fresh()->enrollmentStatus);
        $this->assertDatabaseHas('enrollmentstatushistory', [
            'enrollmentId' => $enrollment->enrollmentId,
            'fromStatus' => 'paid',
            'toStatus' => 'assessed',
        ]);
    }

    #[Test]
    public function a_voided_receipt_keeps_its_row_and_its_or_number(): void
    {
        $assessment = $this->createOwingAssessment();
        $payment = $this->collect($assessment, 1500);
        $orNumber = $payment->orNumber;

        $this->actingAs($this->cashier)->post(route('accounting.payment.void', $payment));

        // An Official Receipt is a controlled document: the row stays for the audit
        // trail and its number stays consumed, so the cashier cannot re-issue it and
        // cannot post a second receipt under it.
        $this->assertDatabaseHas('payments', [
            'paymentId' => $payment->paymentId,
            'orNumber' => $orNumber,
            'processedBy' => $this->cashier->userId,
            'paymentStatus' => 'pending',
        ]);

        $this->actingAs($this->cashier)
            ->post(route('accounting.payment.record', $assessment), [
                'orNumber' => $orNumber,
                'amount' => 100,
                'paymentMode' => 'cash',
                'paymentDate' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('orNumber');
    }

    #[Test]
    public function a_voided_receipt_drops_out_of_the_daily_collection_report(): void
    {
        $assessment = $this->createOwingAssessment();

        $kept = $this->collect($assessment, 1000);
        $voided = $this->collect($assessment, 2000);

        $this->actingAs($this->cashier)
            ->get(route('accounting.daily-report'))
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/DailyReport')
                ->has('payments', 2)
                ->where('summary.totalCount', 2)
                ->where('summary.totalAmount', 3000)
            );

        $this->actingAs($this->cashier)->post(route('accounting.payment.void', $voided));

        $this->actingAs($this->cashier)
            ->get(route('accounting.daily-report'))
            ->assertInertia(fn ($page) => $page
                ->has('payments', 1)
                ->where('payments.0.paymentId', $kept->paymentId)
                ->where('summary.totalCount', 1)
                ->where('summary.totalAmount', 1000)
            );
    }

    #[Test]
    public function an_already_voided_receipt_cannot_be_voided_again(): void
    {
        $assessment = $this->createOwingAssessment();
        $payment = $this->collect($assessment, 4000);

        $this->actingAs($this->cashier)
            ->post(route('accounting.payment.void', $payment))
            ->assertRedirect();

        // PaymentPolicy::void reads `pending` as "already voided", so the second
        // click is refused rather than re-running the balance and status math.
        $this->actingAs($this->cashier)
            ->post(route('accounting.payment.void', $payment))
            ->assertForbidden();

        $this->assertEquals(4000, (float) $assessment->fresh()->remainingBalance);
        $this->assertSame(EnrollmentStatus::Assessed, $assessment->fresh()->enrollment->enrollmentStatus);
    }

    #[Test]
    public function only_the_accounting_desk_may_void(): void
    {
        $assessment = $this->createOwingAssessment();
        $payment = $this->collect($assessment, 4000);

        // AccountingStaff carries payment.void; seated in the Scholarship office it
        // proves the office scope is the guard, not the permission.
        $elsewhere = $this->staffInOffice(OfficeId::Scholarship->value, 'AccountingStaff');
        $this->assertTrue($elsewhere->hasPermissionTo('payment.void'));

        $this->actingAs($elsewhere)
            ->post(route('accounting.payment.void', $payment))
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->paymentStatus);
        $this->assertEquals(0, (float) $assessment->fresh()->remainingBalance);
    }

    #[Test]
    public function a_void_that_reopens_the_balance_does_not_undo_a_signature_or_an_enrollment(): void
    {
        $assessment = $this->createOwingAssessment();
        $enrollment = $assessment->enrollment;

        $payment = $this->collect($assessment, 4000);

        // The student has already been approved and enrolled by Registrar.
        $enrollment->update(['enrollmentStatus' => EnrollmentStatus::Enrolled]);
        $accountingStep = $enrollment->fresh()->enrollmentworkflow
            ->workflowsteps()->where('officeId', OfficeId::Accounting->value)->first();

        $this->actingAs($this->cashier)
            ->post(route('accounting.payment.void', $payment))
            ->assertSessionHas('success');

        // The balance re-opens and the receipt is void, but the state machine only
        // pulls back `paid`, so an already enrolled student keeps their standing and
        // their signed box. §15 describes the fallback for paid enrollments and is
        // silent on this case — pinned here rather than changed, because unwinding a
        // completed workflow is a Registrar decision.
        $this->assertEquals(4000, (float) $assessment->fresh()->remainingBalance);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->paymentStatus);
        $this->assertSame(EnrollmentStatus::Enrolled, $enrollment->fresh()->enrollmentStatus);
        $this->assertSame(WorkflowStepStatus::Completed, $accountingStep->fresh()->stepStatus);
    }
}
