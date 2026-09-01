<?php

namespace Tests\Feature\Exam;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamStage;
use App\Enums\ExamType;
use App\Enums\UnitType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Admissions;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Examresults;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Dedicated Exam-module coverage (BR9 two-stage entrance exam, BR10 retention).
 *
 * Mirrors the Clinic controller test structure: isolated SQLite database,
 * seeded RBAC, and direct model fixtures. Exercises the permission-gated
 * recording endpoints (general / courseSpecific / retention), the BR9
 * general-pass prerequisite, admission auto-status updates, and the
 * exam.students JSON lookup.
 */
class ExamControllerTest extends TestCase
{
    use DatabaseTransactions;

    private int $entranceCourseId;

    private int $retentionCourseId;

    private int $plainCourseId;

    private int $termId;

    private int $evaluatorId;

    protected function setUp(): void
    {
        parent::setUp();

        // Use sqlite in-memory for fast isolated tests
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite']);
        $this->seedReferenceData();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RbacSeeder', '--database' => 'sqlite']);
        $this->createEvaluator();
    }

    private function seedReferenceData(): void
    {
        Offices::insert([
            ['officeId' => 1, 'officeName' => 'System Administration'],
            ['officeId' => 2, 'officeName' => 'Accounting Office'],
            ['officeId' => 3, 'officeName' => 'Assessment Office'],
            ['officeId' => 4, 'officeName' => 'Department Evaluation'],
            ['officeId' => 5, 'officeName' => 'Blocking and Scheduling'],
            ['officeId' => 6, 'officeName' => 'Admission Office'],
            ['officeId' => 7, 'officeName' => 'Guidance / Entrance Exam'],
            ['officeId' => 8, 'officeName' => 'Clearance Office'],
            ['officeId' => 11, 'officeName' => 'Clinic'],
            ['officeId' => 22, 'officeName' => 'ID Office'],
        ]);

        Religions::create(['religionId' => 1, 'religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitCode' => 'CCS',
            'unitName' => 'College of Computer Studies',
            'unitType' => UnitType::College,
        ]);

        $academicYear = Academicyears::create([
            'yearStart' => 2024,
            'yearEnd' => 2025,
            'yearLabel' => '2024-2025',
            'startDate' => '2024-06-01',
            'endDate' => '2025-05-31',
        ]);
        Academicterms::create([
            'academicYearId' => $academicYear->academicYearId,
            'semester' => '1st',
            'startDate' => '2024-06-01',
            'endDate' => '2024-10-31',
        ]);

        // Three courses covering every policy branch:
        //   entrance-only (BR9), entrance + retention (BR10), and neither.
        Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCRIM',
            'courseName' => 'BS Criminology (entrance exam required)',
            'requiresEntranceExam' => true,
            'requiresRetentionExam' => false,
        ]);
        Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSN',
            'courseName' => 'BS Nursing (entrance + retention required)',
            'requiresEntranceExam' => true,
            'requiresRetentionExam' => true,
        ]);
        Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSIT',
            'courseName' => 'BS Information Technology (no exams)',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);
    }

    /**
     * Create the Department Evaluation staff used as the enrollment evaluator
     * (evaluatedBy is NOT NULL on enrollments). Must run AFTER RbacSeeder so
     * the OfficeHead role exists.
     */
    private function createEvaluator(): void
    {
        $evaluator = Staffusers::factory()->make([
            'officeId' => 4,
            'role' => 'officeHead',
            'employeeNo' => 'EMP-EXAM-EVAL-'.uniqid(),
            'username' => 'exam_evaluator_'.uniqid(),
            'email' => 'exam_evaluator_'.uniqid().'@example.com',
        ]);
        unset($evaluator->remember_token);
        $evaluator->save();
        $evaluator->assignRole('OfficeHead');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->entranceCourseId = (int) Courses::where('courseCode', 'BSCRIM')->value('courseId');
        $this->retentionCourseId = (int) Courses::where('courseCode', 'BSN')->value('courseId');
        $this->plainCourseId = (int) Courses::where('courseCode', 'BSIT')->value('courseId');
        $this->termId = (int) Academicterms::value('termId');
        $this->evaluatorId = (int) $evaluator->userId;
    }

    /**
     * Create a staff user in the given office with the given Spatie role.
     * The `role` column value only matters for display; authorization is
     * driven purely by the Spatie role's permissions.
     */
    private function staffWithRole(string $spatieRole, int $officeId): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => $officeId,
            'role' => 'staff',
            'employeeNo' => 'EMP-EXAM-'.uniqid(),
            'username' => 'exam_'.strtolower($spatieRole).'_'.$officeId.'_'.uniqid(),
            'email' => 'exam_'.strtolower($spatieRole).'_'.$officeId.'_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();

        $staff->assignRole($spatieRole);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    private function createStudent(): Students
    {
        return Students::create([
            'schoolIdNumber' => 'EXAM-'.uniqid(),
            'lastName' => 'Examinee',
            'firstName' => 'Test',
            'middleName' => 'E',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'telephoneNumber' => null,
            'email' => 'exam_student_'.uniqid().'@example.com',
            'username' => 'exam_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);
    }

    private function createAdmission(Students $student, int $courseId, string $status = 'pending'): Admissions
    {
        return Admissions::create([
            'studentId' => $student->studentId,
            'courseId' => $courseId,
            'termId' => $this->termId,
            'applicantType' => 'firstYear',
            'admissionStatus' => $status,
        ]);
    }

    private function createEnrolledEnrollment(Students $student, int $courseId): Enrollments
    {
        return Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $courseId,
            'termId' => $this->termId,
            'admissionId' => null,
            'yearLevel' => 1,
            'studentType' => 'firstYear',
            'evaluatedBy' => $this->evaluatorId,
            'enrollmentType' => 'new',
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Enrolled,
        ]);
    }

    #[Test]
    public function exam_index_and_results_are_viewable_with_exam_view(): void
    {
        $guidance = $this->staffWithRole('GuidanceStaff', 7);

        $this->actingAs($guidance)->get(route('exam.index'))->assertOk();
        $this->actingAs($guidance)->get(route('exam.results'))->assertOk();
    }

    #[Test]
    public function exam_index_is_forbidden_without_exam_view(): void
    {
        // ClinicStaff holds clinic/dashboard/user permissions but NOT exam.view.
        $clinic = $this->staffWithRole('ClinicStaff', 11);

        $this->actingAs($clinic)->get(route('exam.index'))->assertForbidden();
    }

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $this->get(route('exam.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function staff_role_cannot_record_general_exam(): void
    {
        // Bare Staff holds exam.view only — no exam.record.general permission.
        $staff = $this->staffWithRole('Staff', 7);
        $student = $this->createStudent();

        $this->actingAs($staff)
            ->post(route('exam.general.record'), [
                'studentId' => $student->studentId,
                'courseId' => $this->entranceCourseId,
                'termId' => $this->termId,
                'examResult' => 'pass',
                'examDate' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertSame(0, Examresults::count());
    }

    #[Test]
    public function guidance_can_record_general_entrance_exam(): void
    {
        $guidance = $this->staffWithRole('GuidanceStaff', 7);
        $student = $this->createStudent();

        $this->actingAs($guidance)
            ->post(route('exam.general.record'), [
                'studentId' => $student->studentId,
                'courseId' => $this->entranceCourseId,
                'termId' => $this->termId,
                'examResult' => 'pass',
                'examDate' => now()->toDateString(),
            ])
            ->assertRedirect(route('exam.index'))
            ->assertSessionHas('success', 'General entrance exam recorded.');

        $this->assertDatabaseHas('examresults', [
            'studentId' => $student->studentId,
            'courseId' => $this->entranceCourseId,
            'termId' => $this->termId,
            'examStage' => ExamStage::Entrance->value,
            'examType' => ExamType::General->value,
            'examResult' => 'pass',
        ]);
    }

    #[Test]
    public function general_exam_is_rejected_for_course_without_entrance_requirement(): void
    {
        $guidance = $this->staffWithRole('GuidanceStaff', 7);
        $student = $this->createStudent();

        $this->actingAs($guidance)
            ->post(route('exam.general.record'), [
                'studentId' => $student->studentId,
                'courseId' => $this->plainCourseId,
                'termId' => $this->termId,
                'examResult' => 'pass',
                'examDate' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('courseId');

        $this->assertSame(0, Examresults::count());
    }

    #[Test]
    public function general_exam_failure_rejects_admission(): void
    {
        $guidance = $this->staffWithRole('GuidanceStaff', 7);
        $student = $this->createStudent();
        $admission = $this->createAdmission($student, $this->entranceCourseId);

        $this->actingAs($guidance)
            ->post(route('exam.general.record'), [
                'studentId' => $student->studentId,
                'courseId' => $this->entranceCourseId,
                'termId' => $this->termId,
                'examResult' => 'fail',
                'examDate' => now()->toDateString(),
            ])
            ->assertRedirect(route('exam.index'));

        $this->assertDatabaseHas('admissions', [
            'admissionId' => $admission->admissionId,
            'admissionStatus' => 'rejected',
        ]);
    }

    #[Test]
    public function general_exam_pass_leaves_admission_pending(): void
    {
        // BR9: the general stage alone must NOT decide the admission — the
        // course-specific stage does. A pass keeps the admission pending.
        $guidance = $this->staffWithRole('GuidanceStaff', 7);
        $student = $this->createStudent();
        $admission = $this->createAdmission($student, $this->entranceCourseId);

        $this->actingAs($guidance)
            ->post(route('exam.general.record'), [
                'studentId' => $student->studentId,
                'courseId' => $this->entranceCourseId,
                'termId' => $this->termId,
                'examResult' => 'pass',
                'examDate' => now()->toDateString(),
            ])
            ->assertRedirect(route('exam.index'));

        $this->assertDatabaseHas('admissions', [
            'admissionId' => $admission->admissionId,
            'admissionStatus' => 'pending',
        ]);
    }

    #[Test]
    public function course_specific_requires_general_pass_first(): void
    {
        $guidance = $this->staffWithRole('GuidanceStaff', 7);
        $student = $this->createStudent();
        $admission = $this->createAdmission($student, $this->entranceCourseId);

        // No general exam recorded yet — BR9 requires it to be PASSED first.
        $this->actingAs($guidance)
            ->post(route('exam.course-specific.record'), [
                'studentId' => $student->studentId,
                'courseId' => $this->entranceCourseId,
                'termId' => $this->termId,
                'examResult' => 'pass',
                'examDate' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('generalExam');

        $this->assertSame(0, Examresults::where('examType', ExamType::CourseSpecific->value)->count());
        $this->assertDatabaseHas('admissions', [
            'admissionId' => $admission->admissionId,
            'admissionStatus' => 'pending',
        ]);
    }

    #[Test]
    public function course_specific_pass_approves_admission(): void
    {
        $guidance = $this->staffWithRole('GuidanceStaff', 7);
        $student = $this->createStudent();
        $admission = $this->createAdmission($student, $this->entranceCourseId);

        // Stage 1: general pass
        $this->actingAs($guidance)
            ->post(route('exam.general.record'), [
                'studentId' => $student->studentId,
                'courseId' => $this->entranceCourseId,
                'termId' => $this->termId,
                'examResult' => 'pass',
                'examDate' => now()->toDateString(),
            ])
            ->assertRedirect(route('exam.index'));

        // Stage 2: course-specific pass → admission auto-approved
        $this->actingAs($guidance)
            ->post(route('exam.course-specific.record'), [
                'studentId' => $student->studentId,
                'courseId' => $this->entranceCourseId,
                'termId' => $this->termId,
                'examResult' => 'pass',
                'examDate' => now()->toDateString(),
            ])
            ->assertRedirect(route('exam.index'));

        $this->assertDatabaseHas('admissions', [
            'admissionId' => $admission->admissionId,
            'admissionStatus' => 'approved',
        ]);
        $this->assertDatabaseHas('examresults', [
            'studentId' => $student->studentId,
            'examStage' => ExamStage::Entrance->value,
            'examType' => ExamType::CourseSpecific->value,
            'examResult' => 'pass',
        ]);
    }

    #[Test]
    public function course_specific_failure_rejects_admission(): void
    {
        $guidance = $this->staffWithRole('GuidanceStaff', 7);
        $student = $this->createStudent();
        $admission = $this->createAdmission($student, $this->entranceCourseId);

        $this->actingAs($guidance)
            ->post(route('exam.general.record'), [
                'studentId' => $student->studentId,
                'courseId' => $this->entranceCourseId,
                'termId' => $this->termId,
                'examResult' => 'pass',
                'examDate' => now()->toDateString(),
            ]);

        $this->actingAs($guidance)
            ->post(route('exam.course-specific.record'), [
                'studentId' => $student->studentId,
                'courseId' => $this->entranceCourseId,
                'termId' => $this->termId,
                'examResult' => 'fail',
                'examDate' => now()->toDateString(),
            ])
            ->assertRedirect(route('exam.index'));

        $this->assertDatabaseHas('admissions', [
            'admissionId' => $admission->admissionId,
            'admissionStatus' => 'rejected',
        ]);
    }

    #[Test]
    public function retention_exam_requires_retention_course(): void
    {
        $guidance = $this->staffWithRole('GuidanceStaff', 7);
        $student = $this->createStudent();

        // Plain course — no retention exam requirement (BR10): ExamPolicy::record
        // denies the ability outright (403) before the controller even runs.
        $this->actingAs($guidance)
            ->post(route('exam.retention.record'), [
                'studentId' => $student->studentId,
                'courseId' => $this->plainCourseId,
                'termId' => $this->termId,
                'examResult' => 'pass',
                'examDate' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertSame(0, Examresults::where('examStage', ExamStage::Retention->value)->count());

        // Board course — retention exam accepted.
        $this->actingAs($guidance)
            ->post(route('exam.retention.record'), [
                'studentId' => $student->studentId,
                'courseId' => $this->retentionCourseId,
                'termId' => $this->termId,
                'examResult' => 'pass',
                'examDate' => now()->toDateString(),
            ])
            ->assertRedirect(route('exam.index'))
            ->assertSessionHas('success', 'Retention exam recorded.');

        $this->assertDatabaseHas('examresults', [
            'studentId' => $student->studentId,
            'courseId' => $this->retentionCourseId,
            'examStage' => ExamStage::Retention->value,
            'examType' => ExamType::CourseSpecific->value,
            'examResult' => 'pass',
        ]);
    }

    #[Test]
    public function students_endpoint_validates_and_returns_enrolled_students(): void
    {
        $guidance = $this->staffWithRole('GuidanceStaff', 7);

        // Missing params → empty list (not an error).
        $this->actingAs($guidance)
            ->getJson(route('exam.students'))
            ->assertOk()
            ->assertJson(['students' => []]);

        // Enrolled student in the course/term appears in the lookup.
        $student = $this->createStudent();
        $this->createEnrolledEnrollment($student, $this->entranceCourseId);

        $this->actingAs($guidance)
            ->getJson(route('exam.students', ['courseId' => $this->entranceCourseId, 'termId' => $this->termId]))
            ->assertOk()
            ->assertJsonFragment(['studentId' => $student->studentId]);
    }

    #[Test]
    public function students_endpoint_is_forbidden_without_record_permission(): void
    {
        // The lookup feeds the recording form, so it requires exam.record —
        // bare Staff (exam.view only) must be denied.
        $staff = $this->staffWithRole('Staff', 7);

        $this->actingAs($staff)
            ->getJson(route('exam.students', ['courseId' => $this->entranceCourseId, 'termId' => $this->termId]))
            ->assertForbidden();
    }

    #[Test]
    public function record_general_validates_required_fields(): void
    {
        $guidance = $this->staffWithRole('GuidanceStaff', 7);

        $this->actingAs($guidance)
            ->post(route('exam.general.record'), [])
            ->assertSessionHasErrors(['studentId', 'courseId', 'termId', 'examResult', 'examDate']);

        $this->assertSame(0, Examresults::count());
    }
}
