<?php

namespace Tests\Feature\Accounting;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\PaymentMode;
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
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * "Bank Check" has been on the Accounting payment form from the start, and both
 * validators accepted `check` — but the backed enum and the `paymentMode` column
 * only allowed cash and online, so the moment a cashier chose check the insert
 * blew up and the desk showed a 500. These tests pin the whole path: the option
 * is offered, the row is stored, and it reads back as a check.
 */
class PaymentModeCheckTest extends TestCase
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

        $staff = Staffusers::factory()->create([
            'officeId' => OfficeId::Accounting->value,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-CHECK-'.uniqid(),
            'username' => 'check_'.uniqid(),
            'email' => 'check_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('AccountingStaff');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->cashier = $staff;
    }

    private function createAssessment(): Studentassessments
    {
        $student = Students::create([
            'schoolIdNumber' => 'CHECK-'.uniqid(),
            'lastName' => 'Checkpayer',
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
            'email' => 'check_student_'.uniqid().'@example.com',
            'username' => 'check_student_'.uniqid(),
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
            'totalScholarshipCoverage' => 0,
            'totalWaived' => 0,
            'remainingBalance' => 5000,
            'assessmentDate' => now()->toDateString(),
        ]);
    }

    #[Test]
    public function the_payment_offers_every_mode_the_desk_can_record(): void
    {
        $assessment = $this->createAssessment();

        $this->actingAs($this->cashier)
            ->get(route('accounting.show', $assessment))
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/Show')
                ->where('paymentModes', [
                    ['value' => 'cash', 'label' => 'Cash Payment'],
                    ['value' => 'check', 'label' => 'Bank Check'],
                    ['value' => 'online', 'label' => 'Online / G-Cash'],
                ])
            );
    }

    #[Test]
    public function check_is_a_case_of_the_payment_mode_enum(): void
    {
        $this->assertSame('check', PaymentMode::Check->value);
        $this->assertSame(PaymentMode::Check, PaymentMode::from('check'));
        $this->assertSame(['cash', 'check', 'online'], array_map(fn ($c) => $c->value, PaymentMode::cases()));
        $this->assertSame('Bank Check', PaymentMode::Check->label());
    }

    #[Test]
    public function an_unknown_mode_is_refused_by_validation_instead_of_reaching_the_column(): void
    {
        $assessment = $this->createAssessment();

        $this->actingAs($this->cashier)
            ->post(route('accounting.payment.record', $assessment), [
                'orNumber' => 'OR-BAD-'.uniqid(),
                'amount' => 5000,
                'paymentMode' => 'crypto',
                'paymentDate' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('paymentMode');

        $this->assertSame(0, Payments::where('enrollmentId', $assessment->enrollmentId)->count());
    }

    #[Test]
    public function a_check_payment_is_recorded_instead_of_crashing_the_desk(): void
    {
        $assessment = $this->createAssessment();

        // Before the column was widened this reached the database as an out-of-range
        // enum value and threw, so the cashier's click produced a 500.
        $this->actingAs($this->cashier)
            ->post(route('accounting.payment.record', $assessment), [
                'orNumber' => 'OR-CHK-'.uniqid(),
                'amount' => 5000,
                'paymentMode' => 'check',
                'paymentDate' => now()->toDateString(),
            ])
            ->assertRedirect(route('accounting.index'))
            ->assertSessionHas('success');

        $payment = Payments::where('enrollmentId', $assessment->enrollmentId)->sole();

        $this->assertSame(PaymentMode::Check, $payment->paymentMode);
        $this->assertSame('check', $payment->getRawOriginal('paymentMode'));
        $this->assertSame(EnrollmentStatus::Paid, $assessment->enrollment->fresh()->enrollmentStatus);
        $this->assertSame(0.0, (float) $assessment->fresh()->remainingBalance);
    }

    #[Test]
    public function the_database_column_accepts_check(): void
    {
        // Guards the migration itself: an SQLite enum is a CHECK constraint, so a
        // narrower column would reject the row here even if PHP accepted it.
        $assessment = $this->createAssessment();
        $orNumber = 'OR-RAW-'.uniqid();

        DB::table('payments')->insert([
            'enrollmentId' => $assessment->enrollmentId,
            'orNumber' => $orNumber,
            'amount' => 100,
            'paymentDate' => now(),
            'paymentMode' => 'check',
            'processedBy' => $this->cashier->userId,
            'paymentStatus' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, DB::table('payments')->where('paymentMode', 'check')->count());
        $this->assertDatabaseHas('payments', ['orNumber' => $orNumber, 'paymentMode' => 'check']);
    }

    #[Test]
    public function the_daily_report_counts_a_check_collection_separately(): void
    {
        $assessment = $this->createAssessment();

        $this->actingAs($this->cashier)
            ->post(route('accounting.payment.record', $assessment), [
                'orNumber' => 'OR-CHK-DAY-'.uniqid(),
                'amount' => 5000,
                'paymentMode' => 'check',
                'paymentDate' => now()->toDateString(),
            ]);

        $this->actingAs($this->cashier)
            ->get(route('accounting.daily-report'))
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/DailyReport')
                ->where('summary.byMode.check.count', 1)
                ->where('summary.byMode.check.amount', 5000)
            );
    }
}
