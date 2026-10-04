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
use App\Models\Enrollmentstatushistory;
use App\Models\Offices;
use App\Models\Payments;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Models\Students;
use App\Services\WorkflowService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Ruling 13 and ruling 14, at the same drawer.
 *
 * Ruling 13: an installment that leaves a balance is a part payment. `partial` existed in
 * the column but `record()` stamped every receipt `paid`, so a student who had paid ₱2,000
 * of a ₱4,000 account held a receipt claiming the account was settled.
 *
 * Ruling 14: a refund is money the school collected and then handed back. It is not a void
 * (a void cancels a receipt that should never have been filed), it is its own receipt state,
 * and it reopens the account — including one the Registrar has already approved, which is
 * why the state machine's `enrolled → assessed` edge is exercised here. The signatures
 * already given stay signed: this desk reverses a figure, not another office's act.
 *
 * These tests walk the real path: collect at the desk, then take the money back out.
 */
class PartPaymentAndRefundTest extends TestCase
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
            'employeeNo' => 'EMP-RFD-'.uniqid(),
            'username' => 'rfd_'.$officeId.'_'.uniqid(),
            'email' => 'rfd_'.$officeId.'_'.uniqid().'@example.com',
        ]);
        $staff->assignRole($spatieRole);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    /**
     * An assessed enrollment carrying a ₱5,000 assessment with ₱4,000 still owing.
     */
    private function createOwingAssessment(): Studentassessments
    {
        $student = Students::create([
            'schoolIdNumber' => 'RFD-'.uniqid(),
            'lastName' => 'Refund',
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
            'email' => 'rfd_student_'.uniqid().'@example.com',
            'username' => 'rfd_student_'.uniqid(),
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
                'orNumber' => 'RFD-OR-'.uniqid(),
                'amount' => $amount,
                'paymentMode' => 'cash',
                'paymentDate' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        return Payments::where('enrollmentId', $assessment->enrollmentId)->latest('paymentId')->firstOrFail();
    }

    private function refund(Payments $payment, string $reason = 'Over-collection returned to the student in cash.'): TestResponse
    {
        return $this->actingAs($this->cashier)
            ->post(route('accounting.payment.refund', $payment), ['refundReason' => $reason]);
    }

    #[Test]
    public function a_receipt_that_leaves_a_balance_is_written_as_a_part_payment(): void
    {
        $assessment = $this->createOwingAssessment();
        $payment = $this->collect($assessment, 1500);

        $this->assertSame(PaymentStatus::Partial, $payment->fresh()->paymentStatus);

        // The account still owes, and the installment counts against it: the balance is
        // ₱2,500, not the ₱4,000 the desk would report if the part payment were ignored.
        $assessment->refresh();
        $this->assertEquals(2500, (float) $assessment->remainingBalance);
        $this->assertEquals(2500, $assessment->outstandingBalance());
        $this->assertSame(EnrollmentStatus::Assessed, $assessment->enrollment->fresh()->enrollmentStatus);
    }

    #[Test]
    public function a_receipt_that_settles_the_account_is_recorded_as_paid(): void
    {
        $assessment = $this->createOwingAssessment();
        $payment = $this->collect($assessment, 4000);

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->paymentStatus);
        $this->assertEquals(0, (float) $assessment->fresh()->remainingBalance);
        $this->assertSame(EnrollmentStatus::Paid, $assessment->enrollment->fresh()->enrollmentStatus);
    }

    #[Test]
    public function both_installments_and_settled_receipts_count_as_collections_on_the_daily_report(): void
    {
        $assessment = $this->createOwingAssessment();
        $this->collect($assessment, 1000);
        $this->collect($assessment, 3000);

        // Before ruling 13 the second receipt was the only one a cashier could produce. A
        // report that read `paid` alone would have dropped the ₱1,000 installment out of
        // the drawer count while the money sat in it.
        $this->actingAs($this->cashier)
            ->get(route('accounting.daily-report'))
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/DailyReport')
                ->has('payments', 2)
                ->where('summary.totalCount', 2)
                ->where('summary.totalAmount', 4000)
                ->where('summary.refundedCount', 0)
                ->has('refunds', 0)
            );
    }

    #[Test]
    public function refunding_a_receipt_reopens_the_balance_and_keeps_the_receipt(): void
    {
        $assessment = $this->createOwingAssessment();
        $payment = $this->collect($assessment, 1500);
        $orNumber = $payment->orNumber;

        $this->refund($payment, 'Duplicate collection against OR '.$orNumber.', returned in cash.')
            ->assertRedirect()
            ->assertSessionHas('success');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Refunded, $payment->paymentStatus);
        $this->assertNotNull($payment->refundedAt);
        $this->assertSame($this->cashier->userId, $payment->refundedBy);
        $this->assertStringContainsString('Duplicate collection', (string) $payment->refundedReason);

        // An Official Receipt is a controlled document: it keeps its number and its
        // collection date, and becomes the record of the payout instead of being rewritten.
        $this->assertSame($orNumber, $payment->orNumber);
        $this->assertEquals(1500, (float) $payment->amount);

        $assessment->refresh();
        $this->assertEquals(4000, (float) $assessment->remainingBalance);
        $this->assertEquals(4000, $assessment->outstandingBalance());
    }

    #[Test]
    public function refunding_the_receipt_that_closed_the_account_returns_the_enrollment_to_owing(): void
    {
        $assessment = $this->createOwingAssessment();
        $enrollment = $assessment->enrollment;
        $payment = $this->collect($assessment, 4000);

        $this->assertSame(EnrollmentStatus::Paid, $enrollment->fresh()->enrollmentStatus);

        $this->refund($payment)->assertSessionHas('success');

        $assessment->refresh();
        $this->assertEquals(4000, (float) $assessment->remainingBalance);
        $this->assertSame(EnrollmentStatus::Assessed, $enrollment->fresh()->enrollmentStatus);

        // The reversal is in the enrollment's own history, with the reason the cashier gave.
        $history = Enrollmentstatushistory::where('enrollmentId', $enrollment->enrollmentId)
            ->latest('historyId')
            ->firstOrFail();
        $this->assertSame('paid', $history->fromStatus);
        $this->assertSame('assessed', $history->toStatus);
        $this->assertStringContainsString($payment->orNumber, (string) $history->remarks);
        $this->assertStringContainsString('refunded', (string) $history->remarks);
    }

    #[Test]
    public function a_refund_reaches_past_the_registrar_signature_without_un_signing_it(): void
    {
        $assessment = $this->createOwingAssessment();
        $enrollment = $assessment->enrollment;
        $payment = $this->collect($assessment, 4000);

        $enrollment->update(['enrollmentStatus' => EnrollmentStatus::Enrolled]);
        $accountingStep = $enrollment->fresh()->enrollmentworkflow
            ->workflowsteps()->where('officeId', OfficeId::Accounting->value)->first();

        $this->refund($payment, 'Scholarship revoked after the tuition was collected.')->assertSessionHas('success');

        // Ruling 14 does not stop at Accounting's own gate: an approved student who owes
        // again has to sit where the money is chaseable. What it does not do is erase the
        // signatures that were given — the same rule the void path follows.
        $this->assertSame(EnrollmentStatus::Assessed, $enrollment->fresh()->enrollmentStatus);
        $this->assertEquals(4000, (float) $assessment->fresh()->remainingBalance);
        $this->assertSame(WorkflowStepStatus::Completed, $accountingStep->fresh()->stepStatus);
    }

    #[Test]
    public function an_account_reopened_by_a_refund_can_be_collected_from_again(): void
    {
        $assessment = $this->createOwingAssessment();
        $payment = $this->collect($assessment, 4000);

        $this->refund($payment, 'Receipt cancelled at the student request and cash returned.');

        // The point of moving the record back to `assessed`: the desk can take the money
        // again, and the student earns the settled state a second time.
        $replacement = $this->collect($assessment, 4000);

        $this->assertNotSame($payment->paymentId, $replacement->paymentId);
        $this->assertSame(PaymentStatus::Paid, $replacement->paymentStatus);
        $this->assertSame(EnrollmentStatus::Paid, $assessment->enrollment->fresh()->enrollmentStatus);
    }

    #[Test]
    public function a_refund_has_to_say_why(): void
    {
        $assessment = $this->createOwingAssessment();
        $payment = $this->collect($assessment, 4000);

        $this->actingAs($this->cashier)
            ->post(route('accounting.payment.refund', $payment), ['refundReason' => 'typo'])
            ->assertSessionHasErrors('refundReason');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->paymentStatus);
        $this->assertEquals(0, (float) $assessment->fresh()->remainingBalance);
        $this->assertSame(EnrollmentStatus::Paid, $assessment->enrollment->fresh()->enrollmentStatus);
    }

    #[Test]
    public function money_that_was_never_held_or_already_returned_cannot_be_paid_out_again(): void
    {
        $assessment = $this->createOwingAssessment();
        $refunded = $this->collect($assessment, 1000);
        $voided = $this->collect($assessment, 500);

        $this->refund($refunded)->assertSessionHas('success');
        $this->actingAs($this->cashier)->post(route('accounting.payment.void', $voided))->assertRedirect();

        $balance = (float) $assessment->fresh()->remainingBalance;

        // PaymentPolicy::refund reads only `paid` and `partial` as money held. Refunding a
        // refunded receipt would pay the student twice; refunding a void would hand back
        // cash that was never kept.
        $this->assertSame(PaymentStatus::Pending, $voided->fresh()->paymentStatus);
        $this->actingAs($this->cashier)
            ->post(route('accounting.payment.refund', $refunded), ['refundReason' => 'Returned to the student in cash.'])
            ->assertForbidden();
        $this->actingAs($this->cashier)
            ->post(route('accounting.payment.refund', $voided), ['refundReason' => 'Returned to the student in cash.'])
            ->assertForbidden();

        // And a payout cannot be voided back into existence: `pending` means "this receipt
        // should never have been filed", which would erase the record that cash left the
        // drawer while leaving the balance it reopened behind.
        $this->actingAs($this->cashier)
            ->post(route('accounting.payment.void', $refunded))
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Refunded, $refunded->fresh()->paymentStatus);
        $this->assertEquals($balance, (float) $assessment->fresh()->remainingBalance);
    }

    #[Test]
    public function only_the_accounting_desk_may_write_a_refund(): void
    {
        $assessment = $this->createOwingAssessment();
        $payment = $this->collect($assessment, 4000);

        // AccountingStaff carries payment.refund; seated in the Scholarship office this
        // proves the office scope is the guard, not the permission.
        $elsewhere = $this->staffInOffice(OfficeId::Scholarship->value, 'AccountingStaff');
        $this->assertTrue($elsewhere->hasPermissionTo('payment.refund'));

        $this->actingAs($elsewhere)
            ->post(route('accounting.payment.refund', $payment), ['refundReason' => 'Returned to the student in cash.'])
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->paymentStatus);
        $this->assertEquals(0, (float) $assessment->fresh()->remainingBalance);
    }

    #[Test]
    public function a_refund_is_on_the_daily_report_as_cash_handed_back(): void
    {
        $assessment = $this->createOwingAssessment();
        $kept = $this->collect($assessment, 1000);
        $paidOut = $this->collect($assessment, 500);

        $this->refund($paidOut, 'Amount collected twice, second receipt returned in cash.')->assertSessionHas('success');

        $this->actingAs($this->cashier)
            ->get(route('accounting.daily-report'))
            ->assertInertia(fn ($page) => $page
                // Held money and money paid back are separate columns of the same sheet:
                // the ₱500 leaves the collections and appears against the day it went out.
                ->has('payments', 1)
                ->where('payments.0.paymentId', $kept->paymentId)
                ->where('summary.totalAmount', 1000)
                ->has('refunds', 1)
                ->where('refunds.0.paymentId', $paidOut->paymentId)
                ->where('summary.refundedAmount', 500)
                ->where('summary.refundedCount', 1)
                ->where('refunds.0.refundedByUser.userId', $this->cashier->userId)
            );
    }

    #[Test]
    public function an_account_closed_with_cash_carries_no_settlement_note(): void
    {
        $assessment = $this->createOwingAssessment();
        $this->collect($assessment, 4000);

        // Ruling 9's note is specifically for an account that owed nothing. This one was
        // closed by money in the drawer, so the sheet must not claim otherwise.
        $this->actingAs($this->cashier)
            ->get(route('accounting.show', $assessment))
            ->assertInertia(fn ($page) => $page->where('settlementWithoutReceipt', null));
    }

    #[Test]
    public function a_refund_does_not_disturb_the_receipt_still_holding_the_account_up(): void
    {
        $assessment = $this->createOwingAssessment();
        $installment = $this->collect($assessment, 2000);
        $settled = $this->collect($assessment, 2000);

        $this->assertSame(PaymentStatus::Partial, $installment->fresh()->paymentStatus);
        $this->assertSame(PaymentStatus::Paid, $settled->fresh()->paymentStatus);

        // Two receipts of ₱2,000 each closed the account. Refunding one moves the balance
        // by ₱2,000 and leaves the other exactly as it was filed — a refund is written
        // against one receipt, not against the account's whole collection history.
        $this->refund($installment, 'Partial refund against a settled account.')->assertSessionHas('success');

        $untouched = $settled->fresh();
        $this->assertSame(PaymentStatus::Paid, $untouched->paymentStatus);
        $this->assertNull($untouched->refundedAt);
        $this->assertNull($untouched->refundedReason);
        $this->assertEquals(2000, (float) $assessment->fresh()->remainingBalance);
    }
}
