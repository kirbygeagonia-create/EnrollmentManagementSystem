<?php

namespace Tests\Feature\Evaluation;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Models\Curriculumsubjects;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Models\Students;
use App\Models\Subjects;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test elective group min/max validation and mandatory subject completeness.
 *
 * Covers:
 * - Elective group min/max enforcement (fewer than min → error, more than max → error, in-range → passes)
 * - Mandatory subject completeness (missing a mandatory curriculum subject → error)
 */
class ElectiveValidationTest extends TestCase
{
    use RefreshDatabase;

    private int $termId;

    private int $courseId;

    private int $majorId;

    private int $curriculumId;

    private int $religionId;

    private Staffusers $evaluator;

    private array $subjectIds = [];

    private array $electiveSubjectIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        // Seed minimal reference data for SQLite testing
        $this->seedReferenceData();
    }

    private function seedReferenceData(): void
    {
        // Academic year & term
        $year = DB::table('academicyears')->insertGetId([
            'yearLabel' => '2024-2025',
            'startDate' => '2024-08-01',
            'endDate' => '2025-05-31',
        ]);

        $this->termId = DB::table('academicterms')->insertGetId([
            'academicYearId' => $year,
            'semester' => '1st',
            'startDate' => '2024-08-01',
            'endDate' => '2024-12-31',
        ]);

        // Religion (required for students)
        $this->religionId = DB::table('religions')->insertGetId([
            'religionName' => 'Roman Catholic',
        ]);

        // Academic unit (required for courses)
        $unitId = DB::table('academicunits')->insertGetId([
            'unitName' => 'College of Computing',
            'unitType' => 'college',
        ]);

        // Office for staff
        $office = DB::table('offices')->insertGetId([
            'officeName' => 'Evaluation Office',
        ]);

        // Course & Major
        $this->courseId = DB::table('courses')->insertGetId([
            'unitId' => $unitId,
            'courseName' => 'BS Computer Science',
            'courseCode' => 'BSCS',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        $this->majorId = DB::table('majors')->insertGetId([
            'courseId' => $this->courseId,
            'majorName' => 'General',
        ]);

        // Curriculum
        $this->curriculumId = DB::table('curriculums')->insertGetId([
            'courseId' => $this->courseId,
            'majorId' => $this->majorId,
            'effectiveYear' => '2024-01-01',
            'curriculumName' => 'Test Curriculum',
        ]);

        // Create mandatory subjects (3)
        for ($i = 1; $i <= 3; $i++) {
            $subjectId = DB::table('subjects')->insertGetId([
                'subjectCode' => "CS{$i}01",
                'subjectName' => "Mandatory Subject {$i}",
                'lectureUnits' => 3,
                'labUnits' => 0,
                'subjectType' => 'lecture',
            ]);
            $this->subjectIds[] = $subjectId;

            DB::table('curriculumsubjects')->insert([
                'curriculumId' => $this->curriculumId,
                'subjectId' => $subjectId,
                'yearLevel' => 1,
                'semesterOffered' => '1st',
                'is_elective' => false,
            ]);
        }

        // Create elective group "ELECTIVE_A" with min=1, max=2 (3 subjects available)
        for ($i = 1; $i <= 3; $i++) {
            $subjectId = DB::table('subjects')->insertGetId([
                'subjectCode' => "ELEC{$i}A",
                'subjectName' => "Elective A Option {$i}",
                'lectureUnits' => 3,
                'labUnits' => 0,
                'subjectType' => 'lecture',
            ]);
            $this->electiveSubjectIds[] = $subjectId;

            DB::table('curriculumsubjects')->insert([
                'curriculumId' => $this->curriculumId,
                'subjectId' => $subjectId,
                'yearLevel' => 1,
                'semesterOffered' => '1st',
                'is_elective' => true,
                'elective_group' => 'ELECTIVE_A',
                'elective_min_choices' => 1,
                'elective_max_choices' => 2,
            ]);
        }

        // Create elective group "ELECTIVE_B" with min=2, max=3 (3 subjects available)
        for ($i = 1; $i <= 3; $i++) {
            $subjectId = DB::table('subjects')->insertGetId([
                'subjectCode' => "ELEC{$i}B",
                'subjectName' => "Elective B Option {$i}",
                'lectureUnits' => 3,
                'labUnits' => 0,
                'subjectType' => 'lecture',
            ]);
            $this->electiveSubjectIds[] = $subjectId;

            DB::table('curriculumsubjects')->insert([
                'curriculumId' => $this->curriculumId,
                'subjectId' => $subjectId,
                'yearLevel' => 1,
                'semesterOffered' => '1st',
                'is_elective' => true,
                'elective_group' => 'ELECTIVE_B',
                'elective_min_choices' => 2,
                'elective_max_choices' => 3,
            ]);
        }

        // Staff user (evaluator)
        $this->evaluator = Staffusers::factory()->create([
            'officeId' => $office,
            'role' => 'officeHead',
        ]);
        $this->evaluator->assignRole('OfficeHead');
    }

    private function createStudent(array $overrides = []): Students
    {
        return Students::create(array_merge([
            'schoolIdNumber' => 'TEST-'.uniqid(),
            'lastName' => 'Test',
            'firstName' => 'Student',
            'middleName' => 'M',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => $this->religionId,
            'contactNumber' => '09171234567',
            'email' => 'test_'.uniqid().'@example.com',
            'username' => 'test_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
        ], $overrides));
    }

    private function createEnrollment(Students $student, string $studentType = 'firstYear', int $yearLevel = 1): Enrollments
    {
        return Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $this->courseId,
            'majorId' => $this->majorId,
            'termId' => $this->termId,
            'admissionId' => null,
            'yearLevel' => $yearLevel,
            'studentType' => $studentType,
            'enrollmentType' => in_array($studentType, ['firstYear', 'transferee']) ? EnrollmentType::New : EnrollmentType::Old,
            'academicStanding' => 'regular',
            'evaluatedBy' => $this->evaluator->userId,
            'enrollmentStatus' => EnrollmentStatus::Pending,
        ]);
    }

    #[Test]
    public function elective_group_below_min_choices_throws_error(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrollment($student);

        // Propose only mandatory subjects + 0 from ELECTIVE_A (min=1)
        $subjectIds = array_merge($this->subjectIds, []); // No electives from group A

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment), [
                'subjects' => array_map(fn ($id) => ['subjectId' => $id], $subjectIds),
            ])
            ->assertSessionHasErrors([
                'subjects' => "Elective group 'ELECTIVE_A' requires between 1 and 2 subjects. 0 selected.",
            ]);
    }

    #[Test]
    public function elective_group_above_max_choices_throws_error(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrollment($student);

        // Propose mandatory subjects + 3 from ELECTIVE_A (max=2)
        $subjectIds = array_merge($this->subjectIds, $this->electiveSubjectIds); // All 3 from group A (first 3 are group A)

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment), [
                'subjects' => array_map(fn ($id) => ['subjectId' => $id], $subjectIds),
            ])
            ->assertSessionHasErrors([
                'subjects' => "Elective group 'ELECTIVE_A' requires between 1 and 2 subjects. 3 selected.",
            ]);
    }

    #[Test]
    public function elective_group_within_min_max_passes(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrollment($student);

        // Propose mandatory subjects + 1 from ELECTIVE_A (min=1, max=2) + 2 from ELECTIVE_B (min=2, max=3)
        $selectedElectivesA = array_slice($this->electiveSubjectIds, 0, 1); // First 1 from group A
        $selectedElectivesB = array_slice($this->electiveSubjectIds, 3, 2); // Next 2 from group B (indices 3,4)
        $subjectIds = array_merge($this->subjectIds, $selectedElectivesA, $selectedElectivesB);

        $response = $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment), [
                'subjects' => array_map(fn ($id) => ['subjectId' => $id], $subjectIds),
            ]);

        $response->assertSessionHasNoErrors();

        // Verify subjects were created
        $enrolledCount = Enrolledsubjects::where('enrollmentId', $enrollment->enrollmentId)->count();
        $this->assertEquals(count($subjectIds), $enrolledCount);
    }

    #[Test]
    public function elective_group_exactly_at_min_passes(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrollment($student);

        // Propose mandatory subjects + exactly 1 from ELECTIVE_A (min=1) + exactly 2 from ELECTIVE_B (min=2)
        $selectedElectivesA = array_slice($this->electiveSubjectIds, 0, 1);
        $selectedElectivesB = array_slice($this->electiveSubjectIds, 3, 2);
        $subjectIds = array_merge($this->subjectIds, $selectedElectivesA, $selectedElectivesB);

        $response = $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment), [
                'subjects' => array_map(fn ($id) => ['subjectId' => $id], $subjectIds),
            ]);

        $response->assertSessionHasNoErrors();
    }

    #[Test]
    public function elective_group_exactly_at_max_passes(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrollment($student);

        // Propose mandatory subjects + exactly 2 from ELECTIVE_A (max=2) + exactly 3 from ELECTIVE_B (max=3)
        $selectedElectivesA = array_slice($this->electiveSubjectIds, 0, 2);
        $selectedElectivesB = array_slice($this->electiveSubjectIds, 3, 3);
        $subjectIds = array_merge($this->subjectIds, $selectedElectivesA, $selectedElectivesB);

        $response = $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment), [
                'subjects' => array_map(fn ($id) => ['subjectId' => $id], $subjectIds),
            ]);

        $response->assertSessionHasNoErrors();
    }

    #[Test]
    public function missing_mandatory_subject_throws_error(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrollment($student);

        // Propose only 2 of 3 mandatory subjects + valid electives (satisfy all group minimums)
        $selectedElectivesA = array_slice($this->electiveSubjectIds, 0, 1); // 1 from group A (min=1)
        $selectedElectivesB = array_slice($this->electiveSubjectIds, 3, 2); // 2 from group B (min=2)
        $subjectIds = array_merge(
            array_slice($this->subjectIds, 0, 2), // Only 2 mandatory (missing the 3rd)
            $selectedElectivesA,
            $selectedElectivesB
        );

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment), [
                'subjects' => array_map(fn ($id) => ['subjectId' => $id], $subjectIds),
            ])
            ->assertSessionHasErrors('subjects');

        $errors = session('errors')->get('subjects');
        $this->assertStringContainsString('The following mandatory subjects are required but not in the proposal', $errors[0]);
        $this->assertStringContainsString((string) $this->subjectIds[2], $errors[0]);
    }

    #[Test]
    public function all_mandatory_subjects_proposed_passes(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrollment($student);

        // Propose all 3 mandatory subjects + valid electives
        $selectedElectivesA = array_slice($this->electiveSubjectIds, 0, 1);
        $selectedElectivesB = array_slice($this->electiveSubjectIds, 3, 2);
        $subjectIds = array_merge($this->subjectIds, $selectedElectivesA, $selectedElectivesB);

        $response = $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment), [
                'subjects' => array_map(fn ($id) => ['subjectId' => $id], $subjectIds),
            ]);

        $response->assertSessionHasNoErrors();
    }

    #[Test]
    public function elective_without_group_is_not_validated(): void
    {
        // Add an elective without a group
        $subjectId = DB::table('subjects')->insertGetId([
            'subjectCode' => 'ELEC_NO_GROUP',
            'subjectName' => 'Elective No Group',
            'lectureUnits' => 3,
            'labUnits' => 0,
            'subjectType' => 'lecture',
        ]);

        DB::table('curriculumsubjects')->insert([
            'curriculumId' => $this->curriculumId,
            'subjectId' => $subjectId,
            'yearLevel' => 1,
            'semesterOffered' => '1st',
            'is_elective' => true,
            'elective_group' => null,
            'elective_min_choices' => null,
            'elective_max_choices' => null,
        ]);

        $student = $this->createStudent();
        $enrollment = $this->createEnrollment($student);

        // Propose mandatory subjects + 1 from ELECTIVE_A (min=1) + 2 from ELECTIVE_B (min=2) + the ungrouped elective
        $selectedElectivesA = array_slice($this->electiveSubjectIds, 0, 1);
        $selectedElectivesB = array_slice($this->electiveSubjectIds, 3, 2);
        $subjectIds = array_merge($this->subjectIds, $selectedElectivesA, $selectedElectivesB, [$subjectId]);

        $response = $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment), [
                'subjects' => array_map(fn ($id) => ['subjectId' => $id], $subjectIds),
            ]);

        $response->assertSessionHasNoErrors();
    }

    #[Test]
    public function curriculum_subject_scopes_work(): void
    {
        $electives = Curriculumsubjects::electives()
            ->where('curriculumId', $this->curriculumId)
            ->where('yearLevel', 1)
            ->where('semesterOffered', '1st')
            ->get();

        $mandatory = Curriculumsubjects::mandatory()
            ->where('curriculumId', $this->curriculumId)
            ->where('yearLevel', 1)
            ->where('semesterOffered', '1st')
            ->get();

        $this->assertEquals(6, $electives->count()); // 3 from group A + 3 from group B
        $this->assertEquals(3, $mandatory->count());

        foreach ($electives as $e) {
            $this->assertTrue($e->isElective());
        }

        foreach ($mandatory as $m) {
            $this->assertFalse($m->isElective());
        }
    }

    #[Test]
    public function elective_group_validation_uses_correct_year_and_semester(): void
    {
        // Add curriculum subjects for year 2, same semester
        $subjectId = DB::table('subjects')->insertGetId([
            'subjectCode' => 'CS201',
            'subjectName' => 'Year 2 Subject',
            'lectureUnits' => 3,
            'labUnits' => 0,
            'subjectType' => 'lecture',
        ]);

        DB::table('curriculumsubjects')->insert([
            'curriculumId' => $this->curriculumId,
            'subjectId' => $subjectId,
            'yearLevel' => 2,
            'semesterOffered' => '1st',
            'is_elective' => false,
        ]);

        $student = $this->createStudent();
        $enrollment = $this->createEnrollment($student, 'firstYear', 1); // Year 1 enrollment

        // Propose year 1 mandatory subjects + valid electives for year 1 (should pass, year 2 subject not required)
        $selectedElectivesA = array_slice($this->electiveSubjectIds, 0, 1);
        $selectedElectivesB = array_slice($this->electiveSubjectIds, 3, 2);
        $subjectIds = array_merge($this->subjectIds, $selectedElectivesA, $selectedElectivesB);

        $response = $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment), [
                'subjects' => array_map(fn ($id) => ['subjectId' => $id], $subjectIds),
            ]);

        $response->assertSessionHasNoErrors();

        // Now test year 2 enrollment
        $enrollment2 = $this->createEnrollment($student, 'continuing', 2);

        // Propose only year 1 subjects (should fail - missing year 2 mandatory)
        $response = $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment2), [
                'subjects' => array_map(fn ($id) => ['subjectId' => $id], $this->subjectIds),
            ]);

        $response->assertSessionHasErrors('subjects');
        $errors = session('errors')->get('subjects');
        $this->assertStringContainsString('The following mandatory subjects are required but not in the proposal', $errors[0]);
        $this->assertStringContainsString((string) $subjectId, $errors[0]);
    }
}
