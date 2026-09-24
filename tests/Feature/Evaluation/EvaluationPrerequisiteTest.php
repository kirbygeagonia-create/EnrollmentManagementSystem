<?php

namespace Tests\Feature\Evaluation;

use App\Enums\EnrollmentStatus;
use App\Enums\UnitType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Curriculums;
use App\Models\Curriculumsubjects;
use App\Models\Enrollments;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Models\Subjects;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Refinement item 7: curriculum versioning + prerequisite auto-gate.
 * - An enrollment is pinned to the curriculum version it was evaluated
 *   against (curriculumId stamped on first proposal, reused afterwards).
 * - A subject whose curriculum prerequisite the student has neither passed
 *   nor credited cannot be proposed.
 */
class EvaluationPrerequisiteTest extends TestCase
{
    use DatabaseTransactions;

    private int $testCourseId;

    private int $testTermId;

    private Staffusers $evaluator;

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

        // Only after RbacSeeder has created the roles.
        $this->evaluator = $this->staffForOffice(4, 'DeptEvaluator');
    }

    private function seedReferenceData(): void
    {
        Offices::insert([
            ['officeId' => 1, 'officeName' => 'Registrar'],
            ['officeId' => 4, 'officeName' => 'Department Evaluation'],
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

    private function staffForOffice(int $officeId, string $role): Staffusers
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

    private function curriculumWithSubjects(int $effectiveYear, ?int $prerequisiteSubjectId = null): Curriculums
    {
        $curriculum = Curriculums::create([
            'courseId' => $this->testCourseId,
            'majorId' => null,
            'effectiveYear' => $effectiveYear,
            'curriculumName' => 'BSCS '.$effectiveYear,
        ]);

        $core = $this->subject('CORE-'.$effectiveYear);
        Curriculumsubjects::create([
            'curriculumId' => $curriculum->curriculumId,
            'subjectId' => $core->subjectId,
            'prerequisiteSubjectId' => $prerequisiteSubjectId,
            'yearLevel' => 1,
            'semesterOffered' => '1st',
            'is_elective' => false,
        ]);

        return $curriculum;
    }

    private function pendingEnrollment(Staffusers $evaluator): Enrollments
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

        return Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $this->testCourseId,
            'termId' => $this->testTermId,
            'admissionId' => null,
            'yearLevel' => 1,
            'studentType' => 'firstYear',
            'evaluatedBy' => $evaluator->userId,
            'enrollmentType' => 'new',
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Pending,
        ]);
    }

    #[Test]
    public function proposal_is_blocked_while_prerequisite_is_unmet(): void
    {
        $prereq = $this->subject('PREREQ-101');
        $curriculum = $this->curriculumWithSubjects(2024, $prereq->subjectId);
        $enrollment = $this->pendingEnrollment($this->evaluator);
        $coreSubjectId = $curriculum->curriculumsubjects()->value('subjectId');

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment), [
                'subjects' => [
                    ['subjectId' => $coreSubjectId, 'curriculumSubjectId' => null],
                    ['subjectId' => $prereq->subjectId, 'curriculumSubjectId' => null],
                ],
            ])
            ->assertSessionHasErrors('subjects');

        $this->assertEquals('pending', $enrollment->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function proposal_passes_when_prerequisite_was_passed_in_a_prior_enrollment(): void
    {
        $prereq = $this->subject('PREREQ-102');
        $curriculum = $this->curriculumWithSubjects(2024, $prereq->subjectId);
        $enrollment = $this->pendingEnrollment($this->evaluator);

        // Simulate the student passing the prerequisite in an earlier term.
        $prior = Enrollments::create([
            'studentId' => $enrollment->studentId,
            'courseId' => $this->testCourseId,
            'termId' => $this->testTermId,
            'admissionId' => null,
            'yearLevel' => 1,
            'studentType' => 'firstYear',
            'evaluatedBy' => $this->evaluator->userId,
            'enrollmentType' => 'old',
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Enrolled,
        ]);
        DB::table('enrolledsubjects')->insert([
            'enrollmentId' => $prior->enrollmentId,
            'subjectId' => $prereq->subjectId,
            'grade' => 1.75,
            'status' => 'confirmed',
            'attempt_number' => 1,
        ]);

        $coreSubjectId = $curriculum->curriculumsubjects()->value('subjectId');

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment), [
                'subjects' => [
                    ['subjectId' => $coreSubjectId, 'curriculumSubjectId' => null],
                    ['subjectId' => $prereq->subjectId, 'curriculumSubjectId' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        $fresh = $enrollment->fresh();
        $this->assertEquals('evaluated', $fresh->enrollmentStatus->value);
        // Item 7: the proposal pins the curriculum version it was evaluated against.
        $this->assertEquals($curriculum->curriculumId, $fresh->curriculumId);
    }

    #[Test]
    public function proposal_fails_when_prerequisite_was_failed_in_a_prior_enrollment(): void
    {
        $prereq = $this->subject('PREREQ-103');
        $curriculum = $this->curriculumWithSubjects(2024, $prereq->subjectId);
        $enrollment = $this->pendingEnrollment($this->evaluator);

        $prior = Enrollments::create([
            'studentId' => $enrollment->studentId,
            'courseId' => $this->testCourseId,
            'termId' => $this->testTermId,
            'admissionId' => null,
            'yearLevel' => 1,
            'studentType' => 'firstYear',
            'evaluatedBy' => $this->evaluator->userId,
            'enrollmentType' => 'old',
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Enrolled,
        ]);
        DB::table('enrolledsubjects')->insert([
            'enrollmentId' => $prior->enrollmentId,
            'subjectId' => $prereq->subjectId,
            'grade' => 5.00,
            'status' => 'confirmed',
            'attempt_number' => 1,
        ]);

        $coreSubjectId = $curriculum->curriculumsubjects()->value('subjectId');

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment), [
                'subjects' => [
                    ['subjectId' => $coreSubjectId, 'curriculumSubjectId' => null],
                    ['subjectId' => $prereq->subjectId, 'curriculumSubjectId' => null],
                ],
            ])
            ->assertSessionHasErrors('subjects');
    }

    #[Test]
    public function pinned_curriculum_survives_a_newer_version(): void
    {
        $older = $this->curriculumWithSubjects(2024);
        $newer = $this->curriculumWithSubjects(2025);
        $enrollment = $this->pendingEnrollment($this->evaluator);

        // Pin to the older version the student was admitted under.
        $enrollment->update(['curriculumId' => $older->curriculumId]);

        $this->actingAs($this->evaluator)
            ->get(route('evaluation.show', $enrollment))
            ->assertSessionHasNoErrors()
            ->assertInertia(fn ($page) => $page
                ->component('Evaluation/Show')
                ->where('curriculum.curriculumId', $older->curriculumId)
                ->where('curriculum.curriculumName', 'BSCS 2024'));

        $this->assertEquals($older->curriculumId, $enrollment->fresh()->curriculumId);
        $this->assertNotEquals($newer->curriculumId, $enrollment->fresh()->curriculumId);
    }
}
