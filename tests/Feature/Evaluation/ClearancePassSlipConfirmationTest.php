<?php

namespace Tests\Feature\Evaluation;

use App\Enums\ClearanceOverallStatus;
use App\Enums\ClearancePeriodStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\StaffRole;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Clearanceperiods;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentclearances;
use App\Models\Students;
use App\Support\EnrollmentReadiness;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Ruling 5: the clearance pass slip is confirmed at Department Evaluation.
 *
 * The department that takes a returning student is the department the student hands the
 * paper to, so that is where "this student was cleared" is asserted. The confirmation is
 * not implied by the database rows behind it — a slip can be approved by every office and
 * never have been carried into the department — and without it the Registrar would approve
 * on a clearance nobody at the front of the pipeline had acknowledged.
 *
 * What the ruling also fixes is the shape of the absence: it does NOT stop the enrollment
 * from being issued (that is pinned in ReturningEnrollmentIssuingTest), it keeps the
 * clearance-passed indicator absent through the later phases, and it stops the approval.
 */
class ClearancePassSlipConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $evaluator;

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
            'schoolIdNumber' => 'PASS-'.uniqid(),
            'lastName' => 'Passer',
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
            'semestersCompleted' => 2,
            'yearsInInstitution' => 1,
            'email' => 'pass_'.uniqid().'@example.com',
            'username' => 'pass_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->evaluator = $this->staffInOffice(OfficeId::Guidance->value, 'DeptEvaluator');
    }

    private function staffInOffice(int $officeId, string $spatieRole): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => $officeId,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-PASS-'.uniqid(),
            'username' => 'pass_'.$officeId.'_'.uniqid(),
            'email' => 'pass_'.$officeId.'_'.uniqid().'@example.com',
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
            'evaluatedBy' => $this->evaluator->userId,
        ], $overrides));
    }

    private function windowWithClearedSlip(): Clearanceperiods
    {
        $window = Clearanceperiods::create([
            'termId' => $this->term->termId,
            'clearanceStartDate' => '2026-09-01',
            'clearanceEndDate' => '2026-10-31',
            'periodStatus' => ClearancePeriodStatus::Open,
        ]);

        Studentclearances::create([
            'studentId' => $this->student->studentId,
            'clearancePeriodId' => $window->clearancePeriodId,
            'overallStatus' => ClearanceOverallStatus::Approved,
        ]);

        return $window;
    }

    #[Test]
    public function the_department_that_saw_the_slip_leaves_its_name_and_the_time_on_the_record(): void
    {
        $this->windowWithClearedSlip();

        // The full chain the Registrar reads: the offices approved, the desk took the
        // paper in hand (BR34), and the department confirmed it.
        Studentclearances::sole()->update([
            'receivedBy' => $this->evaluator->userId,
            'receivedDate' => now(),
        ]);

        $enrollment = $this->enrollment();

        $this->actingAs($this->evaluator)
            ->from(route('evaluation.show', $enrollment))
            ->post(route('evaluation.clearance.confirm', $enrollment), ['confirmed' => true])
            ->assertSessionHasNoErrors();

        $enrollment->refresh();
        $this->assertSame($this->evaluator->userId, (int) $enrollment->clearanceConfirmedBy);
        $this->assertNotNull($enrollment->clearanceConfirmedAt);

        $verdict = EnrollmentReadiness::clearanceVerdict($enrollment);

        $this->assertTrue($verdict['passed'], 'Cleared slip + desk receipt + the department\'s confirmation is a clearance.');
    }

    #[Test]
    public function nothing_can_be_confirmed_when_the_student_has_no_slip_in_the_accepting_window(): void
    {
        $enrollment = $this->enrollment();

        // No window at all.
        $this->actingAs($this->evaluator)
            ->post(route('evaluation.clearance.confirm', $enrollment), ['confirmed' => true])
            ->assertSessionHasErrors('clearanceConfirmed');
        $this->assertStringContainsString(
            'No clearance window is accepting slips',
            session('errors')->first('clearanceConfirmed')
        );

        // A window the student has never drawn a slip in.
        Clearanceperiods::create([
            'termId' => $this->term->termId,
            'clearanceStartDate' => '2026-09-01',
            'clearanceEndDate' => '2026-10-31',
            'periodStatus' => ClearancePeriodStatus::Open,
        ]);

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.clearance.confirm', $enrollment), ['confirmed' => true])
            ->assertSessionHasErrors('clearanceConfirmed');
        $this->assertStringContainsString(
            'has no clearance slip',
            session('errors')->first('clearanceConfirmed')
        );

        // A confirmation that was refused leaves nothing behind.
        $this->assertNull($enrollment->fresh()->clearanceConfirmedBy);
    }

    #[Test]
    public function a_slip_the_offices_have_not_approved_cannot_be_confirmed_as_a_pass(): void
    {
        $this->windowWithClearedSlip();
        Studentclearances::sole()->update(['overallStatus' => ClearanceOverallStatus::Pending]);

        $enrollment = $this->enrollment();

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.clearance.confirm', $enrollment), ['confirmed' => true])
            ->assertSessionHasErrors('clearanceConfirmed');

        $this->assertStringContainsString(
            'reads pending, not approved',
            session('errors')->first('clearanceConfirmed')
        );
        $this->assertNull($enrollment->fresh()->clearanceConfirmedBy);
    }

    #[Test]
    public function a_confirmation_can_be_withdrawn_and_the_record_is_held_again(): void
    {
        $this->windowWithClearedSlip();
        $enrollment = $this->enrollment();
        $confirm = route('evaluation.clearance.confirm', $enrollment);

        // Received at the desk, so the only thing between this record and approval is
        // the department's word that it saw the slip.
        Studentclearances::sole()->update([
            'receivedBy' => $this->evaluator->userId,
            'receivedDate' => now(),
        ]);

        $this->actingAs($this->evaluator)->post($confirm, ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertTrue(EnrollmentReadiness::clearanceVerdict($enrollment->fresh())['passed']);

        // A mistaken confirmation lifts a block, so the same desk has to be able to put
        // the block back. Withdrawing is not deleting: both acts stay in the audit log.
        $this->actingAs($this->evaluator)->post($confirm, ['confirmed' => false])->assertSessionHasNoErrors();

        $enrollment->refresh();
        $this->assertNull($enrollment->clearanceConfirmedBy);
        $this->assertNull($enrollment->clearanceConfirmedAt);

        $this->assertStringContainsString(
            'has not confirmed',
            EnrollmentReadiness::clearanceVerdict($enrollment)['reason']
        );
    }

    #[Test]
    public function the_registrar_is_told_the_confirmation_is_what_is_missing(): void
    {
        $this->windowWithClearedSlip();

        // Cleared and received, but never confirmed at the department.
        Studentclearances::sole()->update([
            'receivedBy' => $this->evaluator->userId,
            'receivedDate' => now(),
        ]);

        $enrollment = $this->enrollment();
        $verdict = EnrollmentReadiness::clearanceVerdict($enrollment);

        $this->assertFalse($verdict['passed']);
        $this->assertStringContainsString("has not confirmed this student's clearance pass slip", $verdict['reason']);

        $enrollment->update([
            'clearanceConfirmedBy' => $this->evaluator->userId,
            'clearanceConfirmedAt' => now(),
        ]);

        $this->assertTrue(EnrollmentReadiness::clearanceVerdict($enrollment->fresh())['passed']);
    }

    #[Test]
    public function a_desk_without_the_right_cannot_confirm_and_a_record_that_owes_no_clearance_cannot_be_confirmed(): void
    {
        $this->windowWithClearedSlip();

        $plainStaff = $this->staffInOffice(OfficeId::Guidance->value, 'Staff');
        $this->assertFalse($plainStaff->hasPermissionTo('evaluation.clearance.confirm'));

        $this->actingAs($plainStaff)
            ->post(route('evaluation.clearance.confirm', $this->enrollment()), ['confirmed' => true])
            ->assertForbidden();

        // A first-year owes no clearance, so there is nothing for this control to confirm —
        // and a confirmation written on their record would assert a slip that never existed.
        $this->actingAs($this->evaluator)
            ->post(route('evaluation.clearance.confirm', $this->enrollment(['studentType' => StudentType::FirstYear])), ['confirmed' => true])
            ->assertForbidden();

        // Once the Registrar has approved, or a drop has closed the record, the answer here
        // must not be rewritable.
        foreach ([EnrollmentStatus::Enrolled, EnrollmentStatus::Dropped] as $settled) {
            $this->actingAs($this->evaluator)
                ->post(route('evaluation.clearance.confirm', $this->enrollment(['enrollmentStatus' => $settled])), ['confirmed' => true])
                ->assertForbidden();
        }

        $this->assertSame(0, Enrollments::whereNotNull('clearanceConfirmedBy')->count());
    }

    #[Test]
    public function the_evaluation_screen_offers_the_control_only_to_the_desks_that_may_use_it(): void
    {
        $this->windowWithClearedSlip();
        $enrollment = $this->enrollment();

        $this->actingAs($this->evaluator)
            ->get(route('evaluation.show', $enrollment))
            ->assertInertia(fn ($page) => $page
                ->where('can.confirmClearance', true)
                ->where('enrollment.clearanceConfirmedBy', null)
            );

        $this->actingAs($this->staffInOffice(OfficeId::Guidance->value, 'Staff'))
            ->get(route('evaluation.show', $enrollment))
            ->assertInertia(fn ($page) => $page->where('can.confirmClearance', false));
    }
}
