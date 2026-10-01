<?php

namespace Tests\Feature\Evaluation;

use App\Enums\AcademicStanding;
use App\Enums\AdmissionStatus;
use App\Enums\ApplicantType;
use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\StaffRole;
use App\Enums\StudentType;
use App\Enums\WorkflowStepStatus;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Admissions;
use App\Models\Courses;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Gradescale;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Models\Students;
use App\Models\Subjects;
use App\Services\AcademicStandingService;
use App\Services\WorkflowService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Item 16: academic standing is an academic judgement, not an admission default.
 *
 * The Admission desk used to stamp every new enrollment `regular`, which made the
 * label printed on the enrollment form, subject load and certificate a value
 * nobody had decided. These tests pin the ownership chain that replaced it:
 * admission leaves it undecided, Evaluation derives it from the grades on file
 * and records its own call, and the Registrar's approval is what makes it final.
 */
class AcademicStandingOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $evaluator;

    private Staffusers $registrar;

    private Staffusers $admissionOfficer;

    private Students $student;

    private Courses $course;

    private Academicterms $currentTerm;

    private Academicterms $priorTerm;

    private Subjects $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        // An enrollment workflow writes one step per office box, and staffusers
        // points at an office, so those offices have to exist first.
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

        $priorYear = Academicyears::create([
            'yearLabel' => '2025-2026',
            'startDate' => '2025-06-01',
            'endDate' => '2026-03-31',
        ]);

        $this->currentTerm = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);

        $this->priorTerm = Academicterms::create([
            'academicYearId' => $priorYear->academicYearId,
            'semester' => '2nd',
            'startDate' => '2025-12-01',
            'endDate' => '2026-05-31',
        ]);

        $this->course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        $this->subject = Subjects::create([
            'subjectCode' => 'CS101',
            'subjectName' => 'Introduction to Programming',
            'lectureUnits' => 3,
            'labUnits' => 1,
            'subjectType' => 'lecture',
        ]);

        $this->student = $this->makeStudent('Standing');

        // Department Evaluation shares office 4 with Guidance in this schema —
        // that is the box the evaluation step signs.
        $this->evaluator = $this->staffInOffice(OfficeId::Guidance->value, 'DeptEvaluator');
        $this->registrar = $this->staffInOffice(OfficeId::Registrar->value, 'RegistrarApprover');
        $this->admissionOfficer = $this->staffInOffice(OfficeId::Admission->value, 'Dean');
    }

    private function staffInOffice(int $officeId, string $spatieRole): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => $officeId,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-STANDING-'.uniqid(),
            'username' => 'standing_'.$officeId.'_'.uniqid(),
            'email' => 'standing_'.$officeId.'_'.uniqid().'@example.com',
        ]);
        $staff->assignRole($spatieRole);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    private function makeStudent(string $surname): Students
    {
        return Students::create([
            'schoolIdNumber' => 'STANDING-'.uniqid(),
            'lastName' => $surname,
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
            'email' => 'standing_'.uniqid().'@example.com',
            'username' => 'standing_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeEnrollment(array $overrides = []): Enrollments
    {
        return Enrollments::create(array_merge([
            'studentId' => $this->student->studentId,
            'courseId' => $this->course->courseId,
            'termId' => $this->currentTerm->termId,
            'yearLevel' => 2,
            'studentType' => StudentType::Continuing,
            'enrollmentType' => EnrollmentType::Old,
            'enrollmentStatus' => EnrollmentStatus::Pending,
            'evaluatedBy' => $this->evaluator->userId,
        ], $overrides));
    }

    /**
     * A completed subject from an earlier term, graded on the 1.00–5.00 scale.
     */
    private function gradePriorSubject(float $grade, int $attempt = 1): Enrolledsubjects
    {
        $prior = $this->makeEnrollment([
            'termId' => $this->priorTerm->termId,
            'yearLevel' => 1,
            'enrollmentStatus' => EnrollmentStatus::Enrolled,
            'academicStanding' => AcademicStanding::Regular,
        ]);

        return Enrolledsubjects::create([
            'enrollmentId' => $prior->enrollmentId,
            'subjectId' => $this->subject->subjectId,
            'grade' => $grade,
            'status' => EnrolledSubjectStatus::Confirmed,
            'attempt_number' => $attempt,
        ]);
    }

    /**
     * A settled account and a workflow whose next pending step is the Registrar's:
     * the prerequisites this desk gates on, without cash actually moving.
     */
    private function makeApprovable(Enrollments $enrollment): Enrollments
    {
        Studentassessments::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'totalAssessedAmount' => 5000,
            'totalScholarshipCoverage' => 0,
            'totalWaived' => 5000,
            'remainingBalance' => 0,
            'assessmentDate' => now()->toDateString(),
        ]);

        $enrollment->update(['enrollmentStatus' => EnrollmentStatus::Paid]);

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

    // ---------------------------------------------------------------- Admission

    #[Test]
    public function admission_approval_creates_the_enrollment_without_deciding_the_standing(): void
    {
        $admission = Admissions::create([
            'studentId' => $this->student->studentId,
            'courseId' => $this->course->courseId,
            'termId' => $this->currentTerm->termId,
            'applicantType' => ApplicantType::FirstYear,
            'admissionStatus' => AdmissionStatus::Pending,
        ]);

        $this->actingAs($this->admissionOfficer)
            ->post(route('admission.approve', $admission))
            ->assertSessionHasNoErrors();

        $enrollment = Enrollments::where('admissionId', $admission->admissionId)->sole();

        $this->assertNull($enrollment->academicStanding);
        $this->assertEquals(EnrollmentStatus::Pending, $enrollment->enrollmentStatus);
    }

    // ---------------------------------------------------------------- Derivation

    #[Test]
    public function a_failed_subject_in_a_previous_term_derives_irregular_standing(): void
    {
        $this->gradePriorSubject(4.50);
        $enrollment = $this->makeEnrollment();

        $report = app(AcademicStandingService::class)->derive($enrollment);

        $this->assertTrue($report['canDerive']);
        $this->assertEquals('irregular', $report['derived']);
        $this->assertCount(1, $report['failedSubjects']);
        $this->assertEquals('CS101', $report['failedSubjects'][0]['subjectCode']);
        $this->assertStringContainsString('2025-2026', $report['failedSubjects'][0]['termLabel']);
    }

    #[Test]
    public function a_clean_record_derives_regular_standing(): void
    {
        $this->gradePriorSubject(1.75);
        $this->gradePriorSubject(3.00);
        $enrollment = $this->makeEnrollment();

        $report = app(AcademicStandingService::class)->derive($enrollment);

        // 3.00 is the lowest passing grade of the scale, so it does not make the
        // student irregular — the boundary belongs on the passing side.
        $this->assertEquals('regular', $report['derived']);
        $this->assertSame([], $report['failedSubjects']);
        $this->assertEquals(2, $report['gradedSubjectCount']);
    }

    #[Test]
    public function the_configured_grade_scale_overrides_the_assumed_passing_line(): void
    {
        Gradescale::create([
            'minGrade' => 1.00,
            'maxGrade' => 2.50,
            'isPassing' => true,
            'description' => 'PROVISIONAL — passing band',
        ]);
        Gradescale::create([
            'minGrade' => 2.51,
            'maxGrade' => 5.00,
            'isPassing' => false,
            'description' => 'PROVISIONAL — failing band',
        ]);

        $this->assertEquals(2.50, Gradescale::passingCeiling());

        // 2.75 passes under the assumed 3.00 default but fails this school's own
        // scale, so the configured scale has to win.
        $this->gradePriorSubject(2.75);
        $report = app(AcademicStandingService::class)->derive($this->makeEnrollment());

        $this->assertEquals('irregular', $report['derived']);
        $this->assertTrue($report['hasGradeScale']);
    }

    #[Test]
    public function a_student_with_no_grades_on_file_cannot_be_derived(): void
    {
        $enrollment = $this->makeEnrollment();

        $report = app(AcademicStandingService::class)->derive($enrollment);

        $this->assertFalse($report['canDerive']);
        $this->assertNull($report['derived']);
        $this->assertStringContainsString('No grades', $report['headline']);
    }

    #[Test]
    public function grades_from_the_term_being_evaluated_are_not_evidence(): void
    {
        $current = $this->makeEnrollment([
            'enrollmentStatus' => EnrollmentStatus::Enrolled,
            'academicStanding' => AcademicStanding::Regular,
        ]);
        Enrolledsubjects::create([
            'enrollmentId' => $current->enrollmentId,
            'subjectId' => $this->subject->subjectId,
            'grade' => 5.00,
            'status' => EnrolledSubjectStatus::Confirmed,
            'attempt_number' => 1,
        ]);

        // A fresh enrollment for the same student must not read this term's own
        // failing grade as the record it was judged on.
        $report = app(AcademicStandingService::class)->derive($this->makeEnrollment());

        $this->assertFalse($report['canDerive']);
        $this->assertEquals(0, $report['gradedSubjectCount']);
    }

    // ------------------------------------------------------- Evaluation decides

    #[Test]
    public function the_evaluation_page_shows_the_evidence_behind_the_recommendation(): void
    {
        $this->gradePriorSubject(4.00);
        $enrollment = $this->makeEnrollment();

        $this->actingAs($this->evaluator)
            ->get(route('evaluation.show', $enrollment))
            ->assertInertia(fn ($page) => $page
                ->where('standingReport.derived', 'irregular')
                ->has('standingReport.failedSubjects', 1)
                ->where('enrollment.academicStanding', null)
                ->where('can.decideStanding', true)
            );
    }

    #[Test]
    public function the_evaluator_records_the_standing_the_department_decided(): void
    {
        $this->gradePriorSubject(4.00);
        $enrollment = $this->makeEnrollment();

        $this->actingAs($this->evaluator)
            ->put(route('evaluation.standing.decide', $enrollment), [
                'academicStanding' => 'irregular',
                'yearLevel' => $enrollment->yearLevel,
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals(AcademicStanding::Irregular, $enrollment->fresh()->academicStanding);
    }

    #[Test]
    public function the_evaluator_may_decide_against_the_derivation(): void
    {
        // The department holds documents the database does not; an override is
        // allowed, it just has to be stated.
        $this->gradePriorSubject(4.00);
        $enrollment = $this->makeEnrollment();

        $this->actingAs($this->evaluator)
            ->put(route('evaluation.standing.decide', $enrollment), [
                'academicStanding' => 'regular',
                'yearLevel' => $enrollment->yearLevel,
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals(AcademicStanding::Regular, $enrollment->fresh()->academicStanding);
    }

    #[Test]
    public function a_standing_must_be_one_of_the_two_official_labels(): void
    {
        $enrollment = $this->makeEnrollment();

        $this->actingAs($this->evaluator)
            ->put(route('evaluation.standing.decide', $enrollment), ['academicStanding' => 'honors'])
            ->assertSessionHasErrors('academicStanding');

        $this->assertNull($enrollment->fresh()->academicStanding);
    }

    #[Test]
    public function nothing_becomes_regular_just_because_nobody_chose(): void
    {
        $enrollment = $this->makeEnrollment();

        $this->actingAs($this->evaluator)
            ->put(route('evaluation.standing.decide', $enrollment), [])
            ->assertSessionHasErrors('academicStanding');

        $this->assertNull($enrollment->fresh()->academicStanding);
    }

    #[Test]
    public function another_desk_cannot_record_the_standing(): void
    {
        $enrollment = $this->makeEnrollment();
        $otherDesk = $this->staffInOffice(OfficeId::Accounting->value, 'AccountingStaff');

        $this->actingAs($otherDesk)
            ->put(route('evaluation.standing.decide', $enrollment), ['academicStanding' => 'irregular'])
            ->assertForbidden();

        $this->assertNull($enrollment->fresh()->academicStanding);
    }

    // ------------------------------------------------------- Year level (G-2)

    #[Test]
    public function the_department_records_the_year_level_a_transferee_is_placed_at(): void
    {
        // Intake writes 1 for every enrollment and nothing else has ever corrected
        // it, so a student the department places in third year stays a first-year
        // in the column the curriculum lookup and the section search both read.
        $enrollment = $this->makeEnrollment([
            'studentType' => StudentType::Transferee,
            'yearLevel' => 1,
        ]);

        $this->actingAs($this->evaluator)
            ->put(route('evaluation.standing.decide', $enrollment), [
                'academicStanding' => 'irregular',
                'yearLevel' => 3,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Placement set to year level 3 (was 1)'));

        $this->assertSame(3, (int) $enrollment->fresh()->yearLevel);
    }

    #[Test]
    public function a_placement_outside_the_programs_years_is_refused_and_changes_nothing(): void
    {
        $enrollment = $this->makeEnrollment(['yearLevel' => 2]);

        $this->actingAs($this->evaluator)
            ->put(route('evaluation.standing.decide', $enrollment), [
                'academicStanding' => 'regular',
                'yearLevel' => 9,
            ])
            ->assertSessionHasErrors('yearLevel');

        $this->assertSame(2, (int) $enrollment->fresh()->yearLevel);
        $this->assertNull($enrollment->fresh()->academicStanding);
    }

    // --------------------------------------------------------- Registrar finalizes

    #[Test]
    public function registrar_approval_writes_its_own_standing_over_the_departments(): void
    {
        $this->gradePriorSubject(4.25);
        // The department called this student regular; the records say otherwise.
        $enrollment = $this->makeApprovable($this->makeEnrollment([
            'academicStanding' => AcademicStanding::Regular,
        ]));

        $this->actingAs($this->registrar)
            ->post(route('registrar.approve', $enrollment), ['academicStanding' => 'irregular'])
            ->assertSessionHasNoErrors();

        $enrollment->refresh();
        $this->assertEquals(EnrollmentStatus::Enrolled, $enrollment->enrollmentStatus);
        $this->assertEquals(AcademicStanding::Irregular, $enrollment->academicStanding);
    }

    #[Test]
    public function approval_cannot_be_granted_while_the_standing_is_unstated(): void
    {
        $enrollment = $this->makeApprovable($this->makeEnrollment());

        $this->actingAs($this->registrar)
            ->post(route('registrar.approve', $enrollment), [])
            ->assertSessionHasErrors('academicStanding');

        $enrollment->refresh();
        $this->assertEquals(EnrollmentStatus::Paid, $enrollment->enrollmentStatus);
        $this->assertNull($enrollment->academicStanding);
    }

    // ---------------------------------------------------------------- Documents

    #[Test]
    public function the_subject_load_document_says_undecided_instead_of_inventing_one(): void
    {
        $enrollment = $this->makeEnrollment([
            'enrollmentStatus' => EnrollmentStatus::Enrolled,
            'academicStanding' => null,
            'enrolledDate' => now()->toDateString(),
            'registrarProcessedBy' => $this->registrar->userId,
        ]);
        Enrolledsubjects::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'subjectId' => $this->subject->subjectId,
            'status' => EnrolledSubjectStatus::Confirmed,
            'attempt_number' => 1,
        ]);

        $html = view('prints.subject-load', [
            'enrollment' => $enrollment->load([
                'student', 'course', 'major', 'term.academicYear',
                'enrolledSubjects.subject', 'registrarProcessedByUser',
            ]),
        ])->render();

        $this->assertStringContainsString('Not yet decided', $html);
    }
}
