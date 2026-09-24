<?php

namespace Tests\Feature;

use App\Enums\AcademicStanding;
use App\Enums\AdmissionStatus;
use App\Enums\ClearanceOverallStatus;
use App\Enums\ClearancePeriodStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\OfficeId;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\StaffRole;
use App\Enums\StaffStatus;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Admissions;
use App\Models\Auditlogs;
use App\Models\Clearanceperiods;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Examresults;
use App\Models\Offices;
use App\Models\Payments;
use App\Models\Religions;
use App\Models\Roles;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Models\Studentclearances;
use App\Models\Students;
use App\Policies\AdmissionPolicy;
use App\Policies\ClearancePolicy;
use App\Services\EnrollmentStateMachine;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HiddenBugsRemediationTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $adminUser;

    private Courses $boardCourse;

    private Students $student;

    private Academicterms $term;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        Religions::create(['religionId' => 1, 'religionName' => 'Roman Catholic']);
        Academicunits::create(['unitId' => 1, 'unitName' => 'College of Computing', 'unitType' => 'college']);
        Academicyears::create(['academicYearId' => 1, 'yearLabel' => '2026-2027', 'startDate' => '2026-06-01', 'endDate' => '2027-03-31']);
        Offices::create(['officeId' => OfficeId::Accounting->value, 'officeName' => 'Accounting Office', 'officeCode' => 'ACCT']);
        Offices::create(['officeId' => OfficeId::Registrar->value, 'officeName' => 'Registrar Office', 'officeCode' => 'REG']);

        $this->adminUser = Staffusers::factory()->create([
            'role' => StaffRole::Admin,
            'officeId' => OfficeId::Registrar->value,
        ]);
        // Item 3 write-boundary: the Admin role is read-everywhere now, so the
        // acting account for these mutation-flow tests needs the SysAdmin
        // super-role — the same pairing the production RbacSeeder gives
        // admin accounts (staff8 = SysAdmin + Admin).
        $this->adminUser->assignRole(['SysAdmin', 'Admin']);

        $this->boardCourse = Courses::create([
            'courseId' => 10,
            'unitId' => 1,
            'courseName' => 'BS Criminology',
            'courseCode' => 'BSCRIM',
            'requiresEntranceExam' => true,
            'requiresRetentionExam' => false,
        ]);

        $this->term = Academicterms::create([
            'termId' => 1,
            'academicYearId' => 1,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);

        $this->student = Students::create([
            'schoolIdNumber' => '2026-9999',
            'lastName' => 'Dela Cruz',
            'firstName' => 'Juan',
            'middleName' => 'P',
            'suffix' => '',
            'gender' => 'male',
            'birthdate' => '2002-05-15',
            'birthplace' => 'Cotabato',
            'citizenship' => 'Filipino',
            'religionId' => 1,
            'civilStatus' => 'single',
            'contactNumber' => '09123456789',
            'email' => 'juan@example.com',
            'username' => 'juandelacruz',
            'passwordHash' => bcrypt('password'),
            'status' => 'active',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
        ]);
    }

    #[Test]
    public function fix1_admission_policy_allows_approval_with_general_exam_only(): void
    {
        $admission = Admissions::create([
            'studentId' => $this->student->studentId,
            'courseId' => $this->boardCourse->courseId,
            'termId' => $this->term->termId,
            'applicantType' => 'firstYear',
            'admissionStatus' => AdmissionStatus::Pending,
        ]);

        Examresults::create([
            'studentId' => $this->student->studentId,
            'courseId' => $this->boardCourse->courseId,
            'termId' => $this->term->termId,
            'examStage' => ExamStage::Entrance,
            'examType' => 'general',
            'examResult' => ExamResult::Pass,
            'examDate' => now(),
        ]);

        $policy = new AdmissionPolicy;
        $this->assertTrue($policy->approve($this->adminUser, $admission));
    }

    #[Test]
    public function fix3_clearance_policy_allows_lost_slip_replacement_for_pending_clearance(): void
    {
        $period = Clearanceperiods::create([
            'clearancePeriodId' => 1,
            'termId' => $this->term->termId,
            'clearanceStartDate' => now()->subDay(),
            'clearanceEndDate' => now()->addMonth(),
            'periodStatus' => ClearancePeriodStatus::Open,
        ]);

        Studentclearances::create([
            'studentId' => $this->student->studentId,
            'clearancePeriodId' => $period->clearancePeriodId,
            'overallStatus' => ClearanceOverallStatus::Pending,
        ]);

        $accountingStaff = Staffusers::factory()->create([
            'role' => StaffRole::OfficeHead,
            'officeId' => OfficeId::Accounting->value,
        ]);
        $accountingStaff->givePermissionTo('clearance.slip.replace');

        $policy = new ClearancePolicy;
        $this->assertTrue($policy->replaceLostSlip($accountingStaff, $this->student, $period));
    }

    #[Test]
    public function fix4_state_machine_permits_paid_to_assessed_reversal(): void
    {
        $stateMachine = new EnrollmentStateMachine;

        $enrollment = Enrollments::create([
            'studentId' => $this->student->studentId,
            'courseId' => $this->boardCourse->courseId,
            'termId' => $this->term->termId,
            'yearLevel' => 1,
            'studentType' => 'firstYear',
            'enrollmentType' => 'new',
            'academicStanding' => AcademicStanding::Regular,
            'enrollmentStatus' => EnrollmentStatus::Paid,
            'evaluatedBy' => $this->adminUser->userId,
        ]);

        $this->assertTrue($stateMachine->canTransition($enrollment, EnrollmentStatus::Assessed));

        $stateMachine->transition($enrollment, EnrollmentStatus::Assessed, $this->adminUser, 'Payment voided reversal');
        $this->assertEquals(EnrollmentStatus::Assessed, $enrollment->fresh()->enrollmentStatus);
    }

    #[Test]
    public function fix5_staff_user_with_foreign_keys_is_deactivated_instead_of_crashing(): void
    {
        $staff = Staffusers::factory()->create(['status' => StaffStatus::Active]);

        Auditlogs::create([
            'userId' => $staff->userId,
            'action' => 'login',
            'entityTable' => 'staffusers',
            'entityId' => $staff->userId,
            'ipAddress' => '127.0.0.1',
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('admin.users.destroy', $staff->userId));

        $response->assertRedirect();
        $this->assertDatabaseHas('staffusers', [
            'userId' => $staff->userId,
            'status' => 'inactive',
        ]);
    }

    #[Test]
    public function fix6_assign_roles_creates_audit_log_entry(): void
    {
        $staff = Staffusers::factory()->create();
        $role = Roles::first();

        $response = $this->actingAs($this->adminUser)->post(route('admin.users.roles.assign', $staff->userId), [
            'roleIds' => [$role->id],
        ]);

        $response->assertRedirect();

        $log = Auditlogs::where('entityTable', 'staffusers')
            ->where('entityId', $staff->userId)
            ->where('action', 'assigned')
            ->first();

        $this->assertNotNull($log, 'Audit log should be recorded for role assignment');
    }

    #[Test]
    public function fix7_registrar_checklist_approves_student_with_downpayment(): void
    {
        $enrollment = Enrollments::create([
            'studentId' => $this->student->studentId,
            'courseId' => $this->boardCourse->courseId,
            'termId' => $this->term->termId,
            'yearLevel' => 1,
            'studentType' => 'firstYear',
            'enrollmentType' => 'new',
            'academicStanding' => AcademicStanding::Regular,
            'enrollmentStatus' => EnrollmentStatus::Assessed,
            'evaluatedBy' => $this->adminUser->userId,
        ]);

        Studentassessments::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'assessmentDate' => now(),
            'totalAssessedAmount' => 10000,
            'totalScholarshipCoverage' => 0,
            'totalWaived' => 0,
            'remainingBalance' => 5000, // Partial downpayment made, balance remaining
        ]);

        Payments::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'orNumber' => 'OR-TEST-001',
            'amount' => 5000,
            'paymentDate' => now(),
            'paymentMode' => PaymentMode::Cash,
            'paymentStatus' => PaymentStatus::Paid,
            'processedBy' => $this->adminUser->userId,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('registrar.show', $enrollment->enrollmentId));
        $response->assertStatus(200);

        $checklist = $response->viewData('page')['props']['checklist'];
        $this->assertTrue($checklist['payment_completed'], 'Installment downpayment must pass payment_completed');
    }
}
