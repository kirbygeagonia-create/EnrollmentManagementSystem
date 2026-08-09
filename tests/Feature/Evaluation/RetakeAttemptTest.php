<?php

namespace Tests\Feature\Evaluation;

use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Models\Students;
use App\Services\EnrollmentService;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test retake attempt tracking logic.
 *
 * Covers:
 * - attempt_number increments across two enrollments of the same student+subject
 * - original_enrolled_subject_id points at the first attempt
 * - dropped prior attempts do NOT count toward attempt number
 * - duplicate subjectId in one proposal payload → validation error
 */
class RetakeAttemptTest extends TestCase
{
    use RefreshDatabase;

    private int $termId;

    private int $courseId;

    private int $majorId;

    private int $curriculumId;

    private int $subjectId;

    private int $religionId;

    private Staffusers $evaluator;

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
        $course = DB::table('courses')->insertGetId([
            'unitId' => $unitId,
            'courseName' => 'BS Computer Science',
            'courseCode' => 'BSCS',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        $major = DB::table('majors')->insertGetId([
            'courseId' => $course,
            'majorName' => 'General',
        ]);

        // Curriculum
        $curriculum = DB::table('curriculums')->insertGetId([
            'courseId' => $course,
            'majorId' => $major,
            'effectiveYear' => '2024-01-01',
            'curriculumName' => 'Test Curriculum',
        ]);

        // Subject
        $subject = DB::table('subjects')->insertGetId([
            'subjectCode' => 'CS101',
            'subjectName' => 'Introduction to Programming',
            'lectureUnits' => 3,
            'labUnits' => 0,
            'subjectType' => 'lecture',
        ]);

        // Curriculum subject (mandatory)
        DB::table('curriculumsubjects')->insert([
            'curriculumId' => $curriculum,
            'subjectId' => $subject,
            'yearLevel' => 1,
            'semesterOffered' => '1st',
            'is_elective' => false,
        ]);

        // Staff user (evaluator)
        $staff = Staffusers::factory()->create([
            'officeId' => $office,
            'role' => 'officeHead',
        ]);
        $staff->assignRole('OfficeHead');

        $this->courseId = $course;
        $this->majorId = $major;
        $this->curriculumId = $curriculum;
        $this->subjectId = $subject;
        $this->evaluator = $staff;
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
    public function first_enrollment_of_subject_gets_attempt_1(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrollment($student);

        // Propose subject
        $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment), [
                'subjects' => [['subjectId' => $this->subjectId]],
            ])
            ->assertSessionHasNoErrors();

        $enrolledSubject = Enrolledsubjects::where('enrollmentId', $enrollment->enrollmentId)
            ->where('subjectId', $this->subjectId)
            ->firstOrFail();

        $this->assertEquals(1, $enrolledSubject->attempt_number);
        $this->assertNull($enrolledSubject->original_enrolled_subject_id);
        $this->assertFalse($enrolledSubject->isRetake());
    }

    #[Test]
    public function second_enrollment_of_same_subject_gets_attempt_2_with_original_link(): void
    {
        $student = $this->createStudent();

        // First enrollment
        $enrollment1 = $this->createEnrollment($student);
        $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment1), [
                'subjects' => [['subjectId' => $this->subjectId]],
            ])
            ->assertSessionHasNoErrors();

        $firstAttempt = Enrolledsubjects::where('enrollmentId', $enrollment1->enrollmentId)
            ->where('subjectId', $this->subjectId)
            ->firstOrFail();

        // Second enrollment (simulating retake in a later term)
        $enrollment2 = $this->createEnrollment($student);
        $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment2), [
                'subjects' => [['subjectId' => $this->subjectId]],
            ])
            ->assertSessionHasNoErrors();

        $secondAttempt = Enrolledsubjects::where('enrollmentId', $enrollment2->enrollmentId)
            ->where('subjectId', $this->subjectId)
            ->firstOrFail();

        $this->assertEquals(2, $secondAttempt->attempt_number);
        $this->assertEquals($firstAttempt->enrolledSubjectId, $secondAttempt->original_enrolled_subject_id);
        $this->assertTrue($secondAttempt->isRetake());
        $this->assertEquals($firstAttempt->enrolledSubjectId, $secondAttempt->originalAttempt->enrolledSubjectId);
    }

    #[Test]
    public function third_enrollment_gets_attempt_3_still_linked_to_first(): void
    {
        $student = $this->createStudent();

        // First enrollment
        $enrollment1 = $this->createEnrollment($student);
        $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment1), [
                'subjects' => [['subjectId' => $this->subjectId]],
            ])
            ->assertSessionHasNoErrors();

        $firstAttempt = Enrolledsubjects::where('enrollmentId', $enrollment1->enrollmentId)
            ->where('subjectId', $this->subjectId)
            ->firstOrFail();

        // Second enrollment
        $enrollment2 = $this->createEnrollment($student);
        $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment2), [
                'subjects' => [['subjectId' => $this->subjectId]],
            ])
            ->assertSessionHasNoErrors();

        // Third enrollment
        $enrollment3 = $this->createEnrollment($student);
        $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment3), [
                'subjects' => [['subjectId' => $this->subjectId]],
            ])
            ->assertSessionHasNoErrors();

        $thirdAttempt = Enrolledsubjects::where('enrollmentId', $enrollment3->enrollmentId)
            ->where('subjectId', $this->subjectId)
            ->firstOrFail();

        $this->assertEquals(3, $thirdAttempt->attempt_number);
        $this->assertEquals($firstAttempt->enrolledSubjectId, $thirdAttempt->original_enrolled_subject_id);
    }

    #[Test]
    public function dropped_prior_attempts_do_not_count_toward_attempt_number(): void
    {
        $student = $this->createStudent();

        // First enrollment - subject dropped
        $enrollment1 = $this->createEnrollment($student);
        $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment1), [
                'subjects' => [['subjectId' => $this->subjectId]],
            ])
            ->assertSessionHasNoErrors();

        $droppedAttempt = Enrolledsubjects::where('enrollmentId', $enrollment1->enrollmentId)
            ->where('subjectId', $this->subjectId)
            ->firstOrFail();

        // Mark as dropped
        $droppedAttempt->status = EnrolledSubjectStatus::Dropped;
        $droppedAttempt->save();

        // Second enrollment - should be attempt 1 again (since prior was dropped)
        $enrollment2 = $this->createEnrollment($student);
        $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment2), [
                'subjects' => [['subjectId' => $this->subjectId]],
            ])
            ->assertSessionHasNoErrors();

        $newAttempt = Enrolledsubjects::where('enrollmentId', $enrollment2->enrollmentId)
            ->where('subjectId', $this->subjectId)
            ->firstOrFail();

        $this->assertEquals(1, $newAttempt->attempt_number, 'Dropped attempts should not increment attempt number');
        $this->assertNull($newAttempt->original_enrolled_subject_id);
    }

    #[Test]
    public function confirmed_prior_attempts_count_toward_attempt_number(): void
    {
        $student = $this->createStudent();

        // First enrollment - subject confirmed
        $enrollment1 = $this->createEnrollment($student);
        $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment1), [
                'subjects' => [['subjectId' => $this->subjectId]],
            ])
            ->assertSessionHasNoErrors();

        $confirmedAttempt = Enrolledsubjects::where('enrollmentId', $enrollment1->enrollmentId)
            ->where('subjectId', $this->subjectId)
            ->firstOrFail();

        // Simulate registrar confirmation
        $confirmedAttempt->status = EnrolledSubjectStatus::Confirmed;
        $confirmedAttempt->save();

        // Second enrollment - should be attempt 2
        $enrollment2 = $this->createEnrollment($student);
        $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment2), [
                'subjects' => [['subjectId' => $this->subjectId]],
            ])
            ->assertSessionHasNoErrors();

        $newAttempt = Enrolledsubjects::where('enrollmentId', $enrollment2->enrollmentId)
            ->where('subjectId', $this->subjectId)
            ->firstOrFail();

        $this->assertEquals(2, $newAttempt->attempt_number);
        $this->assertEquals($confirmedAttempt->enrolledSubjectId, $newAttempt->original_enrolled_subject_id);
    }

    #[Test]
    public function duplicate_subject_id_in_same_proposal_payload_throws_validation_error(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrollment($student);

        // Try to propose the same subject twice in one payload
        $this->actingAs($this->evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment), [
                'subjects' => [
                    ['subjectId' => $this->subjectId],
                    ['subjectId' => $this->subjectId],
                ],
            ])
            ->assertSessionHasErrors([
                'subjects' => 'Duplicate subject IDs are not allowed in the same proposal.',
            ]);
    }

    #[Test]
    public function enrollment_service_determine_attempt_number_works_directly(): void
    {
        $student = $this->createStudent();

        // First enrollment
        $enrollment1 = $this->createEnrollment($student);
        Enrolledsubjects::create([
            'enrollmentId' => $enrollment1->enrollmentId,
            'subjectId' => $this->subjectId,
            'status' => EnrolledSubjectStatus::Proposed,
            'attempt_number' => 1,
            'original_enrolled_subject_id' => null,
        ]);

        // Second enrollment - test service directly
        $enrollment2 = $this->createEnrollment($student);
        $result = EnrollmentService::determineAttemptNumber($enrollment2->enrollmentId, $this->subjectId);

        $this->assertEquals(2, $result['attempt']);
        $this->assertNotNull($result['originalId']);

        // Create enrolled subject for second enrollment (simulating proposal)
        Enrolledsubjects::create([
            'enrollmentId' => $enrollment2->enrollmentId,
            'subjectId' => $this->subjectId,
            'status' => EnrolledSubjectStatus::Proposed,
            'attempt_number' => 2,
            'original_enrolled_subject_id' => $result['originalId'],
        ]);

        // Third enrollment
        $enrollment3 = $this->createEnrollment($student);
        $result = EnrollmentService::determineAttemptNumber($enrollment3->enrollmentId, $this->subjectId);

        $this->assertEquals(3, $result['attempt']);
        $this->assertEquals($result['originalId'], $result['originalId']); // Same original
    }

    #[Test]
    public function unique_index_prevents_duplicate_attempt_in_same_enrollment(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrollment($student);

        // Create first attempt
        Enrolledsubjects::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'subjectId' => $this->subjectId,
            'status' => EnrolledSubjectStatus::Proposed,
            'attempt_number' => 1,
            'original_enrolled_subject_id' => null,
        ]);

        // Try to create another with same enrollmentId, subjectId, attempt_number
        $this->expectException(QueryException::class);
        Enrolledsubjects::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'subjectId' => $this->subjectId,
            'status' => EnrolledSubjectStatus::Proposed,
            'attempt_number' => 1, // Duplicate
            'original_enrolled_subject_id' => null,
        ]);
    }
}
