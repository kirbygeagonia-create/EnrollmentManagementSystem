<?php

namespace Tests\Feature\Registrar;

use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\StudentType;
use App\Enums\UnitType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Curriculums;
use App\Models\Curriculumsubjects;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Models\Subjects;
use App\Support\EnrollmentReadiness;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Concern #32: the Registrar approves a study load, so it has to know the load is
 * one the student is entitled to take.
 *
 * The prerequisite rule was enforced only as a lock on the Evaluation desk's own
 * propose buttons. Nothing checked it at approval, and the Evaluation screen also
 * offers manual proposals and retakes, so an unmet prerequisite could reach the
 * Registrar's queue and be signed into an enrollment. These tests pin the predicate
 * the checklist and the approval policy now share.
 */
class PrerequisiteGateTest extends TestCase
{
    use DatabaseTransactions;

    private int $courseId;

    private int $termId;

    private int $evaluatorId;

    private int $yearLevel = 1;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite']);

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

        $year = Academicyears::create([
            'yearStart' => 2026,
            'yearEnd' => 2027,
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-05-31',
        ]);
        $term = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);

        // No gradescale rows: the predicate reads the passing ceiling through
        // Gradescale::passingCeiling(), whose documented fallback is 3.00. Writing
        // a scale here would test the fixture rather than the rule.
        Offices::create(['officeId' => 4, 'officeName' => 'Department Evaluation']);
        $this->evaluatorId = (int) Staffusers::factory()->create([
            'officeId' => 4,
            'role' => 'officeHead',
            'employeeNo' => 'EMP-PR-'.uniqid(),
            'username' => 'pr_evaluator_'.uniqid(),
            'email' => 'pr_evaluator_'.uniqid().'@example.com',
        ])->userId;

        $this->courseId = (int) $course->courseId;
        $this->termId = (int) $term->termId;
    }

    private function subject(string $code): Subjects
    {
        return Subjects::create([
            'subjectCode' => $code,
            'subjectName' => $code.' Subject',
            'lectureUnits' => 3,
            'labUnits' => 0,
            'subjectType' => 'lecture',
        ]);
    }

    /**
     * A curriculum whose Year 1 / 1st semester subject requires $prerequisite.
     *
     * @return array{curriculum: Curriculums, child: Subjects, prerequisite: Subjects}
     */
    private function curriculumWithPrerequisite(): array
    {
        $curriculum = Curriculums::create([
            'courseId' => $this->courseId,
            'majorId' => null,
            'effectiveYear' => 2026,
            'curriculumName' => 'BSCS 2026',
        ]);

        $prerequisite = $this->subject('PRE-'.uniqid());
        $child = $this->subject('CHD-'.uniqid());

        Curriculumsubjects::create([
            'curriculumId' => $curriculum->curriculumId,
            'subjectId' => $child->subjectId,
            'prerequisiteSubjectId' => $prerequisite->subjectId,
            'yearLevel' => $this->yearLevel,
            'semesterOffered' => '1st',
            'is_elective' => false,
        ]);

        return ['curriculum' => $curriculum, 'child' => $child, 'prerequisite' => $prerequisite];
    }

    private function enrollment(Subjects $confirmed, array $attributes = []): Enrollments
    {
        $student = Students::create([
            'schoolIdNumber' => 'PREREQ-'.uniqid(),
            'lastName' => 'Bound',
            'firstName' => 'Student',
            'middleName' => 'C',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 1,
            'yearsInInstitution' => 1,
            'email' => 'prereq_'.uniqid().'@example.com',
            'username' => 'prereq_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $enrollment = Enrollments::create(array_merge([
            'studentId' => $student->studentId,
            'courseId' => $this->courseId,
            'termId' => $this->termId,
            'yearLevel' => $this->yearLevel,
            'studentType' => StudentType::Continuing,
            'enrollmentType' => EnrollmentType::Old,
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Paid,
            'evaluatedBy' => $this->evaluatorId,
        ], $attributes));

        Enrolledsubjects::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'subjectId' => $confirmed->subjectId,
            'status' => EnrolledSubjectStatus::Confirmed,
            'attempt_number' => 1,
        ]);

        return $enrollment;
    }

    #[Test]
    public function a_confirmed_subject_behind_an_unmet_prerequisite_is_not_approvable(): void
    {
        $load = $this->curriculumWithPrerequisite();
        $enrollment = $this->enrollment($load['child'], ['curriculumId' => $load['curriculum']->curriculumId]);

        $this->assertFalse(EnrollmentReadiness::prerequisitesMet($enrollment));
    }

    #[Test]
    public function passing_the_prerequisite_earns_the_subject_back(): void
    {
        $load = $this->curriculumWithPrerequisite();
        $enrollment = $this->enrollment($load['child'], ['curriculumId' => $load['curriculum']->curriculumId]);

        $this->gradeFor($enrollment->studentId, $load['prerequisite'], 2.5);

        $this->assertTrue(EnrollmentReadiness::prerequisitesMet($enrollment->fresh()));
    }

    #[Test]
    public function a_failed_prerequisite_does_not_unlock_the_subject(): void
    {
        $load = $this->curriculumWithPrerequisite();
        $enrollment = $this->enrollment($load['child'], ['curriculumId' => $load['curriculum']->curriculumId]);

        // 5.0 is a fail on the PH scale, and the predicate reads it as one: the
        // student sits the subject without having earned it.
        $this->gradeFor($enrollment->studentId, $load['prerequisite'], 5.0);

        $this->assertFalse(EnrollmentReadiness::prerequisitesMet($enrollment->fresh()));
    }

    /**
     * A finished earlier term for the same student: the predicate looks across the
     * student's whole record, not just the enrollment being approved.
     */
    private function gradeFor(int $studentId, Subjects $subject, float $grade): void
    {
        $earlier = Enrollments::create([
            'studentId' => $studentId,
            'courseId' => $this->courseId,
            'termId' => $this->termId,
            'yearLevel' => $this->yearLevel,
            'studentType' => StudentType::Continuing,
            'enrollmentType' => EnrollmentType::Old,
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Enrolled,
            'evaluatedBy' => $this->evaluatorId,
        ]);

        Enrolledsubjects::create([
            'enrollmentId' => $earlier->enrollmentId,
            'subjectId' => $subject->subjectId,
            'status' => EnrolledSubjectStatus::Confirmed,
            'attempt_number' => 1,
            'grade' => $grade,
        ]);
    }

    #[Test]
    public function a_dropped_subject_cannot_hold_the_load_hostage(): void
    {
        $load = $this->curriculumWithPrerequisite();
        $enrollment = $this->enrollment($load['child'], ['curriculumId' => $load['curriculum']->curriculumId]);

        Enrolledsubjects::where('enrollmentId', $enrollment->enrollmentId)
            ->update(['status' => EnrolledSubjectStatus::Dropped->value]);

        $this->assertTrue(EnrollmentReadiness::prerequisitesMet($enrollment->fresh()));
    }
}
