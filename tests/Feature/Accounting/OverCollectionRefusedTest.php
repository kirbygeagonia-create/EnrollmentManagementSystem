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
use App\Services\WorkflowService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Ruled 2026-10-20: an OR records money the school keeps, so it may not state more than the account owes.
 *
 * Measured first, on a schema built in CI's order, by driving the real route through the HTTP kernel as the
 * Accounting desk: one POST of ₱19,000 against a ₱17,500 sheet was accepted (the only bound on `amount` was
 * `min:0.01`), the receipt wrote itself `paid`, and the account stored a balance of ₱0.00 — because
 * `Studentassessments::outstandingBalance()` answers `max(0, …)`. ₱1,500 sat in the drawer against nothing owed
 * and the standing record-state audit, then seventeen rules, read every one of them clean (§28.3 G-26).
 *
 * The screen that takes the money used to invite exactly this: its notice read "the backend accepts any amount,
 * so a cashier tendering more than the balance must see the change due". Change is now handed back at the counter
 * before the receipt is filed, and the refusal names both figures so the desk can see what it was told.
 */
class OverCollectionRefusedTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        // Lane 51's lesson: anything that dates a row from `now()` must not race the school's calendar.
        Carbon::setTestNow('2026-10-18 06:00:00');

        $this->seed(RbacSeeder::class);

        foreach ([1, 2, 3, 4, 5, 11, 22] as $officeId) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => 'Office '.$officeId]);
        }

        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $unit = Academicunits::create(['unitName' => 'College of Computer Studies', 'unitType' => 'college']);
        $year = Academicyears::create([
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-03-31',
        ]);
        $term = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);
        $course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        // The desk's own hand, built the way the working Accounting fixture builds it: the `role` column is the
        // StaffRole enum (`staff`, `officeHead`, …), which is a different thing from the Spatie role the drawer
        // reads. Naming the Spatie role in the column instead throws `ValueError: "AccountingStaff" is not a
        // valid backing value for enum App\Enums\StaffRole` on the first attribute read.
        $this->cashier = Staffusers::factory()->create([
            'officeId' => OfficeId::Accounting->value,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-OVR-'.uniqid(),
            'username' => 'ovr_cashier_'.uniqid(),
            'email' => 'ovr_cashier_'.uniqid().'@example.com',
        ]);
        $this->cashier->assignRole('AccountingStaff');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assessment = $this->owingAssessment($course, $term, 5000, 1000);
    }

    private Studentassessments $assessment;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * An assessed account owed ₱4,000: ₱5,000 assessed less a ₱1,000 grant.
     */
    private function owingAssessment(Courses $course, Academicterms $term, float $assessed, float $coverage): Studentassessments
    {
        $student = Students::create([
            'schoolIdNumber' => 'OVR-'.uniqid(),
            'lastName' => 'Overpayer',
            'firstName' => 'Student',
            'middleName' => 'N',
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
            'email' => 'ovr_student_'.uniqid().'@example.com',
            'username' => 'ovr_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $course->courseId,
            'termId' => $term->termId,
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
            'totalAssessedAmount' => $assessed,
            'totalScholarshipCoverage' => $coverage,
            'totalWaived' => 0,
            'remainingBalance' => $assessed - $coverage,
            'assessmentDate' => now()->toDateString(),
        ]);
    }

    private function take(float $amount, string $tag = 'OVR-OR')
    {
        return $this->actingAs($this->cashier)->post(route('accounting.payment.record', $this->assessment), [
            'orNumber' => $tag.'-'.uniqid(),
            'amount' => $amount,
            'paymentMode' => 'cash',
            'paymentDate' => now()->toDateString(),
        ]);
    }

    #[Test]
    public function a_receipt_above_what_the_account_owes_is_refused_and_no_cash_enters_the_drawer(): void
    {
        $response = $this->take(4500.00);

        $response->assertSessionHasErrors('amount');

        // The refusal has to say what the desk did wrong in the desk's own figures. A generic "failed validation"
        // would leave the cashier to guess at a ceiling they have never been told about.
        $message = session('errors')->first('amount');
        $this->assertStringContainsString('4,000.00', $message);
        $this->assertStringContainsString('may not exceed', $message);

        $this->assertSame(0, Payments::where('enrollmentId', $this->assessment->enrollmentId)->count());
        $this->assertSame(
            4000.0,
            (float) $this->assessment->fresh()->remainingBalance,
            'the balance the screen reads must be untouched by a receipt that was refused',
        );
        $this->assertSame(
            EnrollmentStatus::Assessed,
            $this->assessment->fresh()->enrollment->enrollmentStatus,
            'a refused receipt may not walk the enrollment into `paid` either',
        );
    }

    #[Test]
    public function the_balance_itself_is_still_a_valid_amount_to_take(): void
    {
        // "May not exceed" is strict. The ordinary demo payment — the whole outstanding balance, to the centavo —
        // has to stay collectable, or this guard would stop the drawer rather than the defect.
        $this->take(4000.00)->assertSessionHasNoErrors();

        $receipt = Payments::where('enrollmentId', $this->assessment->enrollmentId)->sole();
        $this->assertSame('paid', $receipt->paymentStatus->value);
        $this->assertSame(0.0, (float) $this->assessment->fresh()->remainingBalance);
    }

    #[Test]
    public function a_refusal_leaves_the_account_collectable_at_the_right_figure(): void
    {
        // The desk's real sequence: ₱4,500 was typed, the screen refused it, the cashier takes the ₱4,000 owed.
        // A guard that wedged the account after one bad keystroke would be its own defect.
        $this->take(4500.00, 'OVR-REJECT')->assertSessionHasErrors('amount');
        $this->take(4000.00, 'OVR-ACCEPT')->assertSessionHasNoErrors();

        $this->assertSame(1, Payments::where('enrollmentId', $this->assessment->enrollmentId)->count());
        $this->assertSame(
            4000.0,
            (float) Payments::held()->where('enrollmentId', $this->assessment->enrollmentId)->sum('amount'),
        );
    }

    #[Test]
    public function a_part_payment_below_the_balance_is_still_an_installment_not_a_refusal(): void
    {
        // Ruling 13 stands untouched: anything at or below the balance is collected and marked `partial`.
        $this->take(1500.00)->assertSessionHasNoErrors();

        $this->assertSame('partial', Payments::where('enrollmentId', $this->assessment->enrollmentId)->sole()->paymentStatus->value);
    }
}
