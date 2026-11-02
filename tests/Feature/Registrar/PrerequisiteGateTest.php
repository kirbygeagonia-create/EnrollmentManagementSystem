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
use App\Models\Gradescale;
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

    /**
     * P09's cheap half: the queue resolves the passing ceiling and the curriculum catalogue once for the page and
     * threads both into the same predicate, instead of reading each one per row (`lane191` measured 5.0 repeated
     * statements per listed row, two of them these school-wide constants; the patch takes it to 3.0).
     *
     * The parameters are optional and default to the fresh read, so the policy and the record page keep calling
     * with no argument — which is exactly the shape `clearanceVerdict()` already uses for the clearance window.
     * The risk of a preload is never the speed, it is a SECOND DEFINITION of the rule: an in-memory resolution of
     * `curriculumFor()` that disagrees with the query would flip a gate on the queue while the very same record
     * still passes or fails correctly one screen over. So each direction of the gate is asserted to agree, not
     * merely asserted to be equal to itself, and the satisfied direction is built by actually passing the
     * prerequisite rather than by removing it.
     */
    #[Test]
    public function a_preloaded_gate_answer_is_the_fresh_answer_in_both_directions(): void
    {
        $load = $this->curriculumWithPrerequisite();
        $enrollment = $this->enrollment($load['child'], ['curriculumId' => $load['curriculum']->curriculumId]);

        $ceiling = Gradescale::passingCeiling();
        $catalogue = Curriculums::all();

        // Blocked: the child is confirmed, the prerequisite has neither been passed nor credited.
        $this->assertFalse(EnrollmentReadiness::prerequisitesMet($enrollment->fresh()), 'the fixture must start blocked');
        $this->assertFalse(
            EnrollmentReadiness::prerequisitesMet($enrollment->fresh(), $ceiling, $catalogue),
            'the preloaded answer refused a load the fresh reads also refuse — an agreement here proves nothing unless one direction fails'
        );

        // Satisfied: pass the prerequisite in an earlier record of the SAME student.
        $this->gradeFor($enrollment->studentId, $load['prerequisite'], 1.00);

        $this->assertTrue(EnrollmentReadiness::prerequisitesMet($enrollment->fresh()), 'the fresh reads see the passed prerequisite');
        $this->assertTrue(
            EnrollmentReadiness::prerequisitesMet($enrollment->fresh(), $ceiling, $catalogue),
            'the preloaded catalogue and ceiling resolve the same record to the same verdict'
        );
    }

    /**
     * The movement test for the same change: with the ceiling handed in, the scale is not queried at all.
     * Counted from the driver's own statements, because asserting the absence of a query by reading the source
     * is the shape of claim that stays green when someone re-inlines the call.
     */
    #[Test]
    public function handing_in_the_ceiling_and_the_catalogue_takes_both_out_of_the_per_row_loop(): void
    {
        $load = $this->curriculumWithPrerequisite();
        $first = $this->enrollment($load['child'], ['curriculumId' => $load['curriculum']->curriculumId]);
        $second = $this->enrollment($load['child'], ['curriculumId' => $load['curriculum']->curriculumId]);

        $scaleReads = 0;
        $catalogueReads = 0;
        DB::listen(function ($query) use (&$scaleReads, &$catalogueReads): void {
            // Driver-neutral on purpose: MySQL quotes identifiers with backticks and SQLite with double quotes,
            // so a pattern written for one of them silently counts zero on the other and the case would pass by
            // reading nothing.
            if (preg_match('/max\(["\x60]?maxGrade/i', $query->sql)) {
                $scaleReads++;
            }
            // Both quotes on both sides, and not a prefix: `from "curriculumsubjects"` begins with the same
            // eleven characters as `from "curriculums"`, so a loose pattern counts the prerequisite rows as
            // catalogue reads — measured at 4 where the truth was 2, which would have failed the preloaded
            // path's own zero on an implementation that does not query the catalogue at all.
            if (preg_match('/from\s+["\x60]curriculums["\x60]/i', $query->sql)) {
                $catalogueReads++;
            }
        });

        // Fresh path: one row, one of each. Two rows, two of each — the loop this batch exists to break.
        EnrollmentReadiness::prerequisitesMet($first->fresh());
        EnrollmentReadiness::prerequisitesMet($second->fresh());
        $this->assertSame(2, $scaleReads, 'the ceiling was read once per record on the fresh path');
        $this->assertSame(2, $catalogueReads, 'the curriculum was resolved once per record on the fresh path');

        // Resolve both first, THEN zero the counters: the resolution itself is a read, and charging it to the
        // preloaded path would make the case fail on a correct implementation (and pass on an inverted one).
        $ceiling = Gradescale::passingCeiling();
        $catalogue = Curriculums::all();
        $scaleReads = 0;
        $catalogueReads = 0;
        EnrollmentReadiness::prerequisitesMet($first->fresh(), $ceiling, $catalogue);
        EnrollmentReadiness::prerequisitesMet($second->fresh(), $ceiling, $catalogue);
        $this->assertSame(0, $scaleReads, 'a page that resolves the scale once must not re-query it per row');
        $this->assertSame(0, $catalogueReads, 'a page that preloads the catalogue must not re-query it per row');
    }
}
