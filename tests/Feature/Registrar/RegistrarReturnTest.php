<?php

namespace Tests\Feature\Registrar;

use App\Enums\EnrollmentStatus;
use App\Enums\UnitType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Models\Students;
use App\Policies\EvaluationPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Item 8: Registrar hold state — a paid enrollment can be returned to
 * Department Evaluation, but only with a recorded reason, and only by the
 * Registrar office. The returned record must re-open for re-proposal.
 */
class RegistrarReturnTest extends TestCase
{
    use DatabaseTransactions;

    private int $testCourseId;

    private int $testTermId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite']);
        $this->seedReferenceData();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RbacSeeder', '--database' => 'sqlite']);
    }

    private function seedReferenceData(): void
    {
        Offices::insert([
            ['officeId' => 1, 'officeName' => 'Registrar'],
            ['officeId' => 4, 'officeName' => 'Department Evaluation'],
            ['officeId' => 6, 'officeName' => 'Admission Office'],
        ]);

        Religions::create(['religionId' => 1, 'religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitCode' => 'CCS',
            'unitName' => 'College of Computer Studies',
            'unitType' => UnitType::College,
        ]);

        $course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        $academicYear = Academicyears::create([
            'yearStart' => 2024,
            'yearEnd' => 2025,
            'yearLabel' => '2024-2025',
            'startDate' => '2024-06-01',
            'endDate' => '2025-05-31',
        ]);
        $term = Academicterms::create([
            'academicYearId' => $academicYear->academicYearId,
            'semester' => '1st',
            'startDate' => '2024-06-01',
            'endDate' => '2024-10-31',
        ]);

        $this->testCourseId = $course->courseId;
        $this->testTermId = $term->termId;
    }

    private function staffForOffice(int $officeId, string $role = 'OfficeHead'): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => $officeId,
            'role' => 'officeHead',
            'employeeNo' => 'EMP-TEST-'.uniqid(),
            'username' => 'test_office'.$officeId.'_'.uniqid(),
            'email' => 'test_office'.$officeId.'_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();

        $staff->assignRole($role);

        return $staff;
    }

    /**
     * Create a PAID first-year enrollment with a settled assessment so the
     * registrar.approve gate (which the return action reuses) passes.
     */
    private function paidEnrollment(?Staffusers $evaluator = null, string $status = 'paid'): Enrollments
    {
        $student = Students::create([
            'schoolIdNumber' => 'TEST-'.uniqid(),
            'lastName' => 'Test',
            'firstName' => 'Student',
            'middleName' => 'T',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'telephoneNumber' => null,
            'email' => 'test_'.uniqid().'@example.com',
            'username' => 'test_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $this->testCourseId,
            'termId' => $this->testTermId,
            'admissionId' => null,
            'yearLevel' => 1,
            'studentType' => 'firstYear',
            'evaluatedBy' => $evaluator?->userId ?? 1,
            'enrollmentType' => 'new',
            'academicStanding' => 'regular',
            'enrollmentStatus' => $status,
        ]);

        Studentassessments::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'totalAssessedAmount' => 1000,
            'totalScholarshipCoverage' => 0,
            'totalWaived' => 0,
            'remainingBalance' => 0,
            'assessmentDate' => now(),
        ]);

        return $enrollment;
    }

    #[Test]
    public function registrar_can_return_a_paid_enrollment_with_a_reason(): void
    {
        $registrar = $this->staffForOffice(1);
        $enrollment = $this->paidEnrollment();

        $response = $this->actingAs($registrar)->post(route('registrar.return', $enrollment), [
            'returnReason' => 'Subject load conflicts with the advising plan.',
        ]);

        $response->assertRedirect(route('registrar.index'));
        $response->assertSessionHasNoErrors();

        $fresh = $enrollment->fresh();
        $this->assertEquals('returnedToEvaluation', $fresh->enrollmentStatus->value);
        $this->assertEquals('Subject load conflicts with the advising plan.', $fresh->returnReason);

        $this->assertDatabaseHas('enrollmentstatushistory', [
            'enrollmentId' => $enrollment->enrollmentId,
            'fromStatus' => 'paid',
            'toStatus' => 'returnedToEvaluation',
        ]);
    }

    #[Test]
    public function return_requires_a_reason(): void
    {
        $registrar = $this->staffForOffice(1);
        $enrollment = $this->paidEnrollment();

        $this->actingAs($registrar)
            ->post(route('registrar.return', $enrollment), ['returnReason' => ''])
            ->assertSessionHasErrors('returnReason');

        $this->assertEquals('paid', $enrollment->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function return_is_rejected_when_enrollment_is_not_paid(): void
    {
        $registrar = $this->staffForOffice(1);
        $enrollment = $this->paidEnrollment(status: 'assessed');

        $this->actingAs($registrar)
            ->post(route('registrar.return', $enrollment), [
                'returnReason' => 'Attempting to return a non-paid enrollment.',
            ])
            ->assertSessionHasErrors('validation');

        $this->assertEquals('assessed', $enrollment->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function non_registrar_offices_cannot_return_enrollments(): void
    {
        $admissionStaff = $this->staffForOffice(6);
        $enrollment = $this->paidEnrollment();

        $this->actingAs($admissionStaff)
            ->post(route('registrar.return', $enrollment), [
                'returnReason' => 'Not this office’s call to make.',
            ])
            ->assertForbidden();

        $this->assertEquals('paid', $enrollment->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function returned_enrollment_reopens_for_department_re_proposal(): void
    {
        $evaluator = $this->staffForOffice(4, 'DeptEvaluator');
        $enrollment = $this->paidEnrollment($evaluator, 'returnedToEvaluation');

        $policy = new EvaluationPolicy;

        $this->assertTrue($policy->proposeSubjects($evaluator, $enrollment));

        // ...and stays closed for the statuses that must not be re-proposed.
        $enrollment->enrollmentStatus = EnrollmentStatus::Assessed;
        $this->assertFalse($policy->proposeSubjects($evaluator, $enrollment));
    }
}
