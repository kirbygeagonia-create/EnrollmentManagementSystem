<?php

namespace Tests\Feature;

use App\Enums\AcademicStanding;
use App\Enums\AdmissionStatus;
use App\Enums\ApplicantType;
use App\Enums\ClearanceOverallStatus;
use App\Enums\ClearancePeriodStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamType;
use App\Enums\StudentType;
use App\Enums\SubmissionStatus;
use App\Enums\UnitType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Admissionrequirements;
use App\Models\Admissions;
use App\Models\Clearanceperiods;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Examresults;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentclearances;
use App\Models\Studentrequirementsubmissions;
use App\Models\Students;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * U-1/U-2: every desk queue shows the student type and academic standing.
 *
 * Desks whose rows ARE enrollments already carry both columns; the Exam and
 * Clearance queues are keyed by (student, term) with no enrollmentId, so they
 * read the standing through Enrollments::standingMapFor(). These tests pin that
 * lookup down to the rendered Inertia props, and pin the honest null: an
 * applicant with no enrollment must not be shown a standing.
 */
class QueueStandingColumnsTest extends TestCase
{
    use DatabaseTransactions;

    private int $courseId;

    private int $termId;

    private int $evaluatorId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite']);

        Offices::insert([
            ['officeId' => 1, 'officeName' => 'Office of the Registrar'],
            ['officeId' => 4, 'officeName' => 'Department Evaluation'],
            ['officeId' => 7, 'officeName' => 'Guidance / Entrance Exam'],
        ]);
        Religions::create(['religionId' => 1, 'religionName' => 'Roman Catholic']);
        $unit = Academicunits::create([
            'unitCode' => 'CCS',
            'unitName' => 'College of Computer Studies',
            'unitType' => UnitType::College,
        ]);
        $year = Academicyears::create([
            'yearStart' => 2024,
            'yearEnd' => 2025,
            'yearLabel' => '2024-2025',
            'startDate' => '2024-06-01',
            'endDate' => '2025-05-31',
        ]);
        Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2024-06-01',
            'endDate' => '2024-10-31',
        ]);
        Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSIT',
            'courseName' => 'BS Information Technology',
            'requiresEntranceExam' => true,
            'requiresRetentionExam' => false,
        ]);

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RbacSeeder', '--database' => 'sqlite']);

        $this->courseId = (int) Courses::value('courseId');
        $this->termId = (int) Academicterms::value('termId');
        $this->evaluatorId = (int) $this->staffWithRole('DeptEvaluator', 4)->userId;
    }

    private function staffWithRole(string $spatieRole, int $officeId): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => $officeId,
            'role' => 'staff',
            'employeeNo' => 'EMP-Q-'.uniqid(),
            'username' => 'q_'.strtolower($spatieRole).'_'.uniqid(),
            'email' => 'q_'.strtolower($spatieRole).'_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();
        $staff->assignRole($spatieRole);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    private function student(string $surname): Students
    {
        return Students::create([
            'schoolIdNumber' => 'Q-'.uniqid(),
            'lastName' => $surname,
            'firstName' => 'Queue',
            'middleName' => 'Column',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'telephoneNumber' => '02-1234567',
            'email' => 'queue.'.$surname.'@example.com',
            'username' => 'queue.'.strtolower($surname).'.'.uniqid(),
            'passwordHash' => bcrypt('password'),
            'status' => 'active',
        ]);
    }

    private function enrollment(Students $student, AcademicStanding $standing = AcademicStanding::Regular, StudentType $type = StudentType::FirstYear): Enrollments
    {
        return Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $this->courseId,
            'termId' => $this->termId,
            'yearLevel' => 2,
            'studentType' => $type,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => $standing,
            'enrollmentStatus' => EnrollmentStatus::Enrolled,
            'evaluatedBy' => $this->evaluatorId,
        ]);
    }

    /** @return array<string, mixed> */
    private function firstRow(string $prop, string $route, Staffusers $actor): array
    {
        $page = $this->actingAs($actor)->get(route($route))->assertOk()->assertViewHas('page')['page'];

        return $page['props'][$prop]['data'][0] ?? [];
    }

    #[Test]
    public function the_standing_map_is_keyed_by_student_and_term(): void
    {
        $student = $this->student('Keyed');
        $enrollment = $this->enrollment($student, AcademicStanding::Irregular, StudentType::Continuing);

        $map = Enrollments::standingMapFor([[$student->studentId, $this->termId]]);

        $match = $map[$student->studentId.'-'.$this->termId] ?? null;
        $this->assertNotNull($match);
        $this->assertSame($enrollment->enrollmentId, $match->enrollmentId);
        $this->assertSame(StudentType::Continuing, $match->studentType);
        $this->assertSame(AcademicStanding::Irregular, $match->academicStanding);
    }

    #[Test]
    public function the_standing_map_keeps_the_newest_enrollment_for_a_pair(): void
    {
        $student = $this->student('Newest');
        $this->enrollment($student, AcademicStanding::Irregular);
        $this->enrollment($student, AcademicStanding::Regular);

        $map = Enrollments::standingMapFor([[$student->studentId, $this->termId]]);

        $this->assertSame(AcademicStanding::Regular, $map[$student->studentId.'-'.$this->termId]->academicStanding);
    }

    #[Test]
    public function pairs_without_an_enrollment_are_left_out_of_the_map(): void
    {
        $student = $this->student('Unmatched');

        $this->assertEmpty(Enrollments::standingMapFor([[$student->studentId, $this->termId]]));
        $this->assertEmpty(Enrollments::standingMapFor([[$student->studentId, null]]));
    }

    #[Test]
    public function the_exam_queue_carries_the_standing_of_the_matching_enrollment(): void
    {
        $student = $this->student('ExamPass');
        $this->enrollment($student, AcademicStanding::Irregular, StudentType::Shifter);
        Examresults::create([
            'studentId' => $student->studentId,
            'courseId' => $this->courseId,
            'termId' => $this->termId,
            'examStage' => ExamStage::Entrance,
            'examType' => ExamType::General,
            'examResult' => ExamResult::Pass,
            'examDate' => now()->toDateString(),
        ]);

        $row = $this->firstRow('exams', 'exam.index', $this->staffWithRole('GuidanceStaff', 7));

        $this->assertSame('irregular', $row['academicStanding']);
        $this->assertSame('shifter', $row['studentType']);
    }

    #[Test]
    public function an_exam_row_with_no_enrollment_shows_no_standing(): void
    {
        $student = $this->student('ExamNoEnrollment');
        Examresults::create([
            'studentId' => $student->studentId,
            'courseId' => $this->courseId,
            'termId' => $this->termId,
            'examStage' => ExamStage::Entrance,
            'examType' => ExamType::General,
            'examResult' => ExamResult::Fail,
            'examDate' => now()->toDateString(),
        ]);

        $row = $this->firstRow('exams', 'exam.index', $this->staffWithRole('GuidanceStaff', 7));

        $this->assertSame('fail', $row['examResult']);
        $this->assertNull($row['academicStanding']);
        $this->assertNull($row['studentType']);
    }

    #[Test]
    public function the_clearance_queue_reads_standing_through_the_period_term(): void
    {
        $student = $this->student('Cleared');
        $this->enrollment($student, AcademicStanding::Regular, StudentType::Continuing);
        $period = Clearanceperiods::create([
            'termId' => $this->termId,
            'clearanceStartDate' => now()->toDateString(),
            'clearanceEndDate' => now()->addWeeks(2)->toDateString(),
            'periodStatus' => ClearancePeriodStatus::Open,
        ]);
        Studentclearances::create([
            'studentId' => $student->studentId,
            'clearancePeriodId' => $period->clearancePeriodId,
            'overallStatus' => ClearanceOverallStatus::Pending,
        ]);

        $row = $this->firstRow('clearances', 'clearance.index', $this->staffWithRole('RegistrarDesk', 1));

        $this->assertSame('regular', $row['academicStanding']);
        $this->assertSame('continuing', $row['studentType']);
    }

    #[Test]
    public function enrollment_backed_queues_expose_standing_and_type(): void
    {
        $student = $this->student('Registrar');
        $this->enrollment($student, AcademicStanding::Irregular, StudentType::Transferee);

        // Registrar's queue filters to assessed/paid enrollments, so move it there.
        Enrollments::query()->update(['enrollmentStatus' => EnrollmentStatus::Paid->value]);

        $row = $this->firstRow('enrollments', 'registrar.index', $this->staffWithRole('RegistrarApprover', 1));

        $this->assertSame('irregular', $row['academicStanding']);
        $this->assertSame('transferee', $row['studentType']);
    }

    #[Test]
    public function the_registrar_queue_names_the_gates_a_record_has_not_cleared(): void
    {
        $student = $this->student('Readiness');
        $enrollment = $this->enrollment($student, AcademicStanding::Irregular, StudentType::Transferee);
        Enrollments::query()->update(['enrollmentStatus' => EnrollmentStatus::Paid->value]);

        // Concerns #28/#32: an admission document the student submitted but the
        // Admission desk never verified is now a gate of its own, named here.
        $admission = Admissions::create([
            'studentId' => $student->studentId,
            'courseId' => $this->courseId,
            'termId' => $this->termId,
            'applicantType' => ApplicantType::Transferee,
            'admissionStatus' => AdmissionStatus::Approved,
        ]);
        $requirement = Admissionrequirements::create([
            'requirementName' => 'Good Moral Certificate',
            'appliesTo' => 'transferee',
            'isRequired' => true,
        ]);
        Studentrequirementsubmissions::create([
            'admissionId' => $admission->admissionId,
            'requirementId' => $requirement->requirementId,
            'submissionStatus' => SubmissionStatus::Submitted,
            'submittedDate' => now(),
            'remarks' => '',
        ]);
        $enrollment->update(['admissionId' => $admission->admissionId]);

        // The desk used to open every row to find out whether it could be
        // approved. The queue now carries the same gates the approval
        // enforces, and says which are still outstanding.
        $page = $this->actingAs($this->staffWithRole('RegistrarApprover', 1))
            ->get(route('registrar.index'))
            ->assertOk()
            ->assertViewHas('page')['page'];

        $readiness = $page['props']['readiness'][$enrollment->enrollmentId];

        $this->assertSame(7, $readiness['total']);
        $this->assertContains('assessment_completed', $readiness['waiting']);
        $this->assertContains('registrarApprovalPending', $readiness['waiting']);
        $this->assertContains('documents_verified', $readiness['waiting']);
        $this->assertNotContains('prerequisites_met', $readiness['waiting'], 'With no curriculum pinned to the record there is no prerequisite the load can breach.');
        $this->assertNotContains('evaluation_signed', $readiness['waiting']);
        $this->assertSame($readiness['total'] - count($readiness['waiting']), $readiness['met']);
    }
}
