<?php

namespace Tests\Feature\Registrar;

use App\Enums\ClearanceOverallStatus;
use App\Enums\ClearancePeriodStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\StaffRole;
use App\Enums\StudentType;
use App\Enums\WorkflowStepStatus;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Clearanceperiods;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Models\Studentclearances;
use App\Models\Students;
use App\Policies\RegistrarPolicy;
use App\Services\WorkflowService;
use App\Support\EnrollmentReadiness;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Ruling 4: the clearance prerequisite is the Registrar's single gate, and a missing
 * clearance window now blocks instead of passing vacuously.
 *
 * `checkClearance()` returned true when no open period existed, so the gate was quietest
 * exactly when the school had not opened clearance at all — a continuing student with no
 * slip from any office could be approved, and the fewer windows on file, the more
 * approvals got through. The gate reads one verdict from one method now, and the record
 * page names the window it is waiting on.
 *
 * A window extended past its end date counts, because an extension is the Registrar
 * keeping the window open (ruling 13) — and a closed window does not, even when the
 * student's slip inside it is perfectly cleared.
 */
class ClearanceWindowGateTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $registrar;

    private Students $student;

    private Courses $course;

    private Academicterms $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        foreach ([1, 2, 3, 4, 5, 6, 11, 22] as $officeId) {
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

        $this->student = Students::create([
            'schoolIdNumber' => 'WINDOW-'.uniqid(),
            'lastName' => 'Window',
            'firstName' => 'Student',
            'middleName' => 'W',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 2,
            'yearsInInstitution' => 1,
            'email' => 'window_'.uniqid().'@example.com',
            'username' => 'window_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->registrar = $this->staffInOffice(OfficeId::Registrar->value, 'RegistrarApprover');
    }

    private function staffInOffice(int $officeId, string $spatieRole): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => $officeId,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-WINDOW-'.uniqid(),
            'username' => 'window_'.$officeId.'_'.uniqid(),
            'email' => 'window_'.$officeId.'_'.uniqid().'@example.com',
        ]);
        $staff->assignRole($spatieRole);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function enrollment(array $overrides = []): Enrollments
    {
        return Enrollments::create(array_merge([
            'studentId' => $this->student->studentId,
            'courseId' => $this->course->courseId,
            'termId' => $this->term->termId,
            'yearLevel' => 2,
            'studentType' => StudentType::Continuing,
            'enrollmentType' => EnrollmentType::Old,
            'enrollmentStatus' => EnrollmentStatus::Pending,
            'evaluatedBy' => $this->registrar->userId,
        ], $overrides));
    }

    private function window(ClearancePeriodStatus $status): Clearanceperiods
    {
        return Clearanceperiods::create([
            'termId' => $this->term->termId,
            'clearanceStartDate' => '2026-09-01',
            'clearanceEndDate' => '2026-10-31',
            'periodStatus' => $status,
        ]);
    }

    /**
     * A slip the offices have cleared and the Registrar desk has taken in hand (BR34).
     */
    private function clearedSlip(Clearanceperiods $window): Studentclearances
    {
        return Studentclearances::create([
            'studentId' => $this->student->studentId,
            'clearancePeriodId' => $window->clearancePeriodId,
            'overallStatus' => ClearanceOverallStatus::Approved,
            'receivedBy' => $this->registrar->userId,
            'receivedDate' => now(),
        ]);
    }

    /**
     * Ruling 5: the department confirms the pass slip it was handed. The window and the
     * slip are not enough on their own, so the fixtures that expect a clear gate confirm.
     */
    private function confirmPassSlip(Enrollments $enrollment): Enrollments
    {
        $enrollment->update([
            'clearanceConfirmedBy' => $this->registrar->userId,
            'clearanceConfirmedAt' => now(),
        ]);

        return $enrollment->fresh();
    }

    /**
     * Everything except the clearance window: paid, assessed, and signed through the box
     * before the Registrar's, so a refusal can only be the gate under test.
     */
    private function approvableEnrollment(): Enrollments
    {
        $enrollment = $this->enrollment(['enrollmentStatus' => EnrollmentStatus::Paid]);

        Studentassessments::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'totalAssessedAmount' => 5000,
            'totalScholarshipCoverage' => 0,
            'totalWaived' => 5000,
            'remainingBalance' => 0,
            'assessmentDate' => now()->toDateString(),
        ]);

        $workflow = app(WorkflowService::class)->createWorkflow($enrollment);
        $registrarStep = $workflow->workflowsteps()->where('officeId', OfficeId::Registrar->value)->first();

        $workflow->workflowsteps()
            ->where('stepOrder', '<', $registrarStep->stepOrder)
            ->update([
                'stepStatus' => WorkflowStepStatus::Completed->value,
                'signedBy' => $this->registrar->userId,
                'signedDate' => now(),
            ]);

        return $enrollment->fresh();
    }

    #[Test]
    public function no_accepting_window_holds_a_continuing_student_at_the_gate(): void
    {
        $verdict = EnrollmentReadiness::clearanceVerdict($this->enrollment());

        $this->assertFalse($verdict['passed']);
        $this->assertStringContainsString('No clearance window is accepting slips', $verdict['reason']);
    }

    #[Test]
    public function a_closed_window_does_not_clear_the_gate_even_when_the_slip_inside_it_is_complete(): void
    {
        $window = $this->window(ClearancePeriodStatus::Open);
        $this->clearedSlip($window);
        $enrollment = $this->confirmPassSlip($this->enrollment());

        $this->assertTrue(EnrollmentReadiness::clearanceVerdict($enrollment)['passed']);

        // Closing the window is the Registrar stopping clearance: the slip that was
        // perfect a moment ago no longer answers for a student being enrolled now.
        $window->update(['periodStatus' => ClearancePeriodStatus::Closed]);

        $verdict = EnrollmentReadiness::clearanceVerdict($enrollment);

        $this->assertFalse($verdict['passed']);
        $this->assertStringContainsString('No clearance window is accepting slips', $verdict['reason']);
    }

    #[Test]
    public function an_extended_window_still_accepts_the_slip_it_was_issued_in(): void
    {
        $window = $this->window(ClearancePeriodStatus::Extended);
        $window->update(['clearanceEndDate' => '2026-12-20']);
        $this->clearedSlip($window);

        $verdict = EnrollmentReadiness::clearanceVerdict($this->confirmPassSlip($this->enrollment()));

        $this->assertTrue($verdict['passed'], 'An extension keeps the window open, so it must satisfy the gate.');
        $this->assertNull($verdict['reason']);
    }

    #[Test]
    public function a_student_who_owes_no_clearance_is_not_held_by_the_window(): void
    {
        foreach ([StudentType::FirstYear, StudentType::Transferee] as $type) {
            $verdict = EnrollmentReadiness::clearanceVerdict($this->enrollment(['studentType' => $type]));

            $this->assertTrue($verdict['passed'], $type->value.' students do not clear the offices.');
            $this->assertNull($verdict['reason']);
        }

        // A shifter does owe one — the same obligation as a continuing student.
        $this->assertFalse(
            EnrollmentReadiness::clearanceVerdict($this->enrollment(['studentType' => StudentType::Shifter]))['passed']
        );
    }

    #[Test]
    public function a_slip_the_offices_cleared_but_the_desk_never_received_does_not_pass(): void
    {
        $window = $this->window(ClearancePeriodStatus::Open);
        $slip = $this->clearedSlip($window);

        $slip->update(['receivedBy' => null, 'receivedDate' => null]);

        $this->assertStringContainsString(
            'never received at the Registrar desk',
            EnrollmentReadiness::clearanceVerdict($this->enrollment())['reason']
        );

        // Still with the offices is a different sentence, and the desk needs the right one.
        $slip->update([
            'overallStatus' => ClearanceOverallStatus::Pending,
            'receivedBy' => $this->registrar->userId,
            'receivedDate' => now(),
        ]);

        $this->assertStringContainsString(
            'still with the offices',
            EnrollmentReadiness::clearanceVerdict($this->enrollment())['reason']
        );
    }

    #[Test]
    public function a_student_with_no_slip_in_the_window_is_named_as_missing_one(): void
    {
        $this->window(ClearancePeriodStatus::Open);

        $this->assertStringContainsString(
            'no clearance slip in the window',
            EnrollmentReadiness::clearanceVerdict($this->enrollment())['reason']
        );
    }

    #[Test]
    public function the_registrar_cannot_approve_a_completed_record_while_no_window_accepts(): void
    {
        $enrollment = $this->approvableEnrollment();

        // Every other gate is clear; only the window is missing.
        $this->assertFalse(app(RegistrarPolicy::class)->validatePrerequisites($this->registrar, $enrollment));

        $this->actingAs($this->registrar)
            ->post(route('registrar.approve', $enrollment), ['academicStanding' => 'regular'])
            ->assertForbidden();

        $this->assertSame('paid', $enrollment->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function opening_the_window_is_enough_for_the_same_record_to_be_approved(): void
    {
        $enrollment = $this->approvableEnrollment();
        $this->clearedSlip($this->window(ClearancePeriodStatus::Open));
        $enrollment = $this->confirmPassSlip($enrollment);

        $this->actingAs($this->registrar)
            ->post(route('registrar.approve', $enrollment), ['academicStanding' => 'regular'])
            ->assertSessionHasNoErrors();

        $this->assertSame('enrolled', $enrollment->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function the_record_page_names_the_window_it_is_waiting_on(): void
    {
        $enrollment = $this->approvableEnrollment();

        $this->actingAs($this->registrar)
            ->get(route('registrar.show', $enrollment))
            ->assertInertia(fn ($page) => $page
                ->component('Registrar/Show')
                ->where('checklist.clearance_verified', false)
                ->where(
                    'blockingReasons.clearance_verified',
                    'No clearance window is accepting slips, so this student has no clearance the Registrar can read. Open or extend one in Clearance → Periods.'
                )
            );
    }
}
