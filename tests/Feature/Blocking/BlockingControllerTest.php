<?php

namespace Tests\Feature\Blocking;

use App\Enums\EnrollmentStatus;
use App\Enums\UnitType;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowStepStatus;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Blocks;
use App\Models\Courses;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Rooms;
use App\Models\Schedulemeetings;
use App\Models\Schedules;
use App\Models\Staffusers;
use App\Models\Students;
use App\Models\Subjects;
use App\Models\Workflowsteps;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlockingControllerTest extends TestCase
{
    use DatabaseTransactions;

    private int $testCourseId;

    private int $testTermId;

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
    }

    private function seedReferenceData(): void
    {
        // Create offices
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

        // Create religion
        Religions::create([
            'religionId' => 1,
            'religionName' => 'Roman Catholic',
        ]);

        // Create academic unit
        $unit = Academicunits::create([
            'unitCode' => 'CCS',
            'unitName' => 'College of Computer Studies',
            'unitType' => UnitType::College,
        ]);

        // Create course
        $course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        // Create term
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

        // Create subjects
        Subjects::insert([
            ['subjectCode' => 'CS101', 'subjectName' => 'Introduction to Programming', 'lectureUnits' => 3, 'labUnits' => 0, 'subjectType' => 'lecture'],
            ['subjectCode' => 'CS102', 'subjectName' => 'Data Structures', 'lectureUnits' => 3, 'labUnits' => 0, 'subjectType' => 'lecture'],
        ]);

        // Create rooms
        Rooms::insert([
            ['roomName' => 'Room 101', 'capacity' => 40, 'building' => 'Main Building'],
            ['roomName' => 'Room 102', 'capacity' => 30, 'building' => 'Main Building'],
        ]);

        // Store for test use
        $this->testCourseId = $course->courseId;
        $this->testTermId = $term->termId;
    }

    /**
     * Create a staff user in the given office with OfficeHead role.
     */
    private function staffForOffice(int $officeId): Staffusers
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

        $staff->assignRole('OfficeHead');

        return $staff;
    }

    /**
     * Create a minimal student + enrollment for testing.
     */
    private function createEnrollment(?int $courseId = null, ?int $termId = null, string $studentType = 'firstYear'): Enrollments
    {
        $courseId = $courseId ?? $this->testCourseId;
        $termId = $termId ?? $this->testTermId;

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
            'courseId' => $courseId,
            'termId' => $termId,
            'admissionId' => null,
            'yearLevel' => 1,
            'studentType' => $studentType,
            'evaluatedBy' => Staffusers::where('officeId', 4)->value('userId') ?? 1,
            'enrollmentType' => 'new',
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Enrolled,
        ]);

        // Create enrolled subjects for the enrollment (matching the block's course subjects)
        $subjects = Subjects::where('subjectType', 'lecture')->limit(3)->get();
        foreach ($subjects as $subject) {
            Enrolledsubjects::create([
                'enrollmentId' => $enrollment->enrollmentId,
                'subjectId' => $subject->subjectId,
                'status' => 'confirmed',
            ]);
        }

        // Create a workflow with all steps before Blocking (office 5) completed,
        // so the Blocking step is the next pending step. Steps for firstYear:
        // [4 Dept Eval, 3 Assessment, 2 Accounting, 1 Registrar, 5 Blocking, 11 Clinic, 22 ID].
        $this->createWorkflowAtBlockingStep($enrollment);

        return $enrollment;
    }

    /**
     * Create a workflow for the enrollment with all steps before the Blocking
     * step (office 5) marked completed, so office 5 is the next pending step.
     */
    private function createWorkflowAtBlockingStep(Enrollments $enrollment): void
    {
        $workflow = Enrollmentworkflow::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'currentStep' => 4, // Registrar (step 4) completed; Blocking (step 5) is next
            'workflowStatus' => WorkflowStatus::InProgress,
        ]);

        // Steps in order: [4, 3, 2, 1, 5, 11, 22] (firstYear per WorkflowService)
        $steps = [
            [4, 1],  // Dept Eval — completed
            [3, 2],  // Assessment — completed
            [2, 3],  // Accounting — completed
            [1, 4],  // Registrar — completed
            [5, 5],  // Blocking — pending (next)
            [11, 6], // Clinic — pending
            [22, 7], // ID Office — pending
        ];

        $signer = Staffusers::where('officeId', 4)->first() ?? Staffusers::first();

        foreach ($steps as [$officeId, $order]) {
            Workflowsteps::create([
                'workflowId' => $workflow->workflowId,
                'officeId' => $officeId,
                'stepOrder' => $order,
                'stepStatus' => $order <= 4 ? WorkflowStepStatus::Completed : WorkflowStepStatus::Pending,
                'signedBy' => $order <= 4 ? $signer->userId : null,
                'signedDate' => $order <= 4 ? now() : null,
            ]);
        }
    }

    /**
     * Create a block with schedule for testing.
     */
    private function createBlockWithSchedule(?int $courseId = null, ?int $termId = null, int $maxStudents = 40): array
    {
        $courseId = $courseId ?? $this->testCourseId;
        $termId = $termId ?? $this->testTermId;

        $block = Blocks::create([
            'courseId' => $courseId,
            'termId' => $termId,
            'yearLevel' => 1,
            'blockName' => 'Test Block '.uniqid(),
            'maxStudents' => $maxStudents,
        ]);

        $subject = Subjects::firstOrFail();
        $instructor = Staffusers::where('officeId', '!=', 1)->firstOrFail();
        $room = Rooms::firstOrFail();

        $schedule = Schedules::create([
            'blockId' => $block->blockId,
            'subjectId' => $subject->subjectId,
            'instructorId' => $instructor->userId,
            'roomId' => $room->roomId,
        ]);

        Schedulemeetings::create([
            'scheduleId' => $schedule->scheduleId,
            'dayOfWeek' => 'Monday',
            'startTime' => '09:00:00',
            'endTime' => '10:00:00',
        ]);

        return ['block' => $block, 'schedule' => $schedule, 'room' => $room, 'instructor' => $instructor];
    }

    #[Test]
    public function test_conflict_prevents_schedule_creation(): void
    {
        $blockingStaff = $this->staffForOffice(5); // Academic Department
        $this->actingAs($blockingStaff);

        $fixture = $this->createBlockWithSchedule();
        $block = $fixture['block'];
        $scheduleA = $fixture['schedule'];
        $instructor = $fixture['instructor'];
        $room = $fixture['room'];

        // Try to create schedule B with same instructor, same time (Monday 9-10)
        $subjectB = Subjects::skip(1)->firstOrFail();

        $response = $this->post(route('blocking.schedules.store', $block), [
            'subjectId' => $subjectB->subjectId,
            'instructorId' => $instructor->userId,
            'roomId' => $room->roomId,
            'meetings' => [
                ['dayOfWeek' => 'Monday', 'startTime' => '09:00', 'endTime' => '10:00'],
            ],
        ]);

        // Should fail with validation errors (conflicts)
        $response->assertSessionHasErrors('conflicts');
        $errors = session('errors')->get('conflicts');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Instructor conflict', $errors[0]);

        // Schedule B should NOT be persisted
        $scheduleCount = Schedules::where('blockId', $block->blockId)->count();
        $this->assertEquals(1, $scheduleCount, 'Conflicting schedule should not be persisted');
    }

    #[Test]
    public function test_block_capacity_enforced_on_assign(): void
    {
        $blockingStaff = $this->staffForOffice(5);
        $this->actingAs($blockingStaff);

        // Create block with maxStudents = 1
        $fixture = $this->createBlockWithSchedule(maxStudents: 1);
        $block = $fixture['block'];
        $schedule = $fixture['schedule'];

        // Create two enrollments
        $enrollment1 = $this->createEnrollment();
        $enrollment2 = $this->createEnrollment();

        // Assign first student - should succeed
        $response1 = $this->post(route('blocking.assign', $block), [
            'enrollmentIds' => [$enrollment1->enrollmentId],
            'scheduleId' => $schedule->scheduleId,
        ]);
        $response1->assertSessionHasNoErrors();

        $enrollment1->refresh();
        $enrolledSubjects1 = $enrollment1->enrolledSubjects()->where('status', '!=', 'dropped')->first();
        $this->assertEquals($block->blockId, $enrolledSubjects1->blockId);
        $this->assertEquals($schedule->scheduleId, $enrolledSubjects1->scheduleId);

        // Assign second student - should fail with capacity error
        $response2 = $this->post(route('blocking.assign', $block), [
            'enrollmentIds' => [$enrollment2->enrollmentId],
            'scheduleId' => $schedule->scheduleId,
        ]);
        $response2->assertSessionHasErrors('capacity');
        $errors = session('errors')->get('capacity');
        $this->assertStringContainsString('Block capacity exceeded', $errors[0]);

        // Only first student should be enrolled
        $enrollment2->refresh();
        $enrolledSubjects2 = $enrollment2->enrolledSubjects()->where('status', '!=', 'dropped')->first();
        $this->assertNull($enrolledSubjects2->blockId);
        $this->assertNull($enrolledSubjects2->scheduleId);
    }

    #[Test]
    public function test_unassign_removes_block_from_student(): void
    {
        $blockingStaff = $this->staffForOffice(5);
        $this->actingAs($blockingStaff);

        $fixture = $this->createBlockWithSchedule();
        $block = $fixture['block'];
        $schedule = $fixture['schedule'];

        $enrollment = $this->createEnrollment();

        // Assign student
        $this->post(route('blocking.assign', $block), [
            'enrollmentIds' => [$enrollment->enrollmentId],
            'scheduleId' => $schedule->scheduleId,
        ])->assertSessionHasNoErrors();

        $enrollment->refresh();
        $enrolledSubject = $enrollment->enrolledSubjects()->where('status', '!=', 'dropped')->first();
        $this->assertEquals($block->blockId, $enrolledSubject->blockId);
        $this->assertEquals($schedule->scheduleId, $enrolledSubject->scheduleId);

        // Unassign student
        $response = $this->post(route('blocking.unassign', $block), [
            'enrollmentIds' => [$enrollment->enrollmentId],
        ]);
        $response->assertSessionHasNoErrors();

        $enrollment->refresh();
        $enrolledSubject = $enrollment->enrolledSubjects()->where('status', '!=', 'dropped')->first();
        $this->assertNull($enrolledSubject->blockId);
        $this->assertNull($enrolledSubject->scheduleId);
    }

    #[Test]
    public function test_schedule_can_be_deleted(): void
    {
        $blockingStaff = $this->staffForOffice(5);
        $this->actingAs($blockingStaff);

        $fixture = $this->createBlockWithSchedule();
        $block = $fixture['block'];
        $schedule = $fixture['schedule'];

        // Verify schedule exists
        $this->assertTrue(Schedules::where('scheduleId', $schedule->scheduleId)->exists());

        // Delete schedule
        $response = $this->delete(route('blocking.schedules.destroy', $schedule));
        $response->assertSessionHasNoErrors();

        // Schedule should be gone
        $this->assertFalse(Schedules::where('scheduleId', $schedule->scheduleId)->exists());
        $this->assertEquals(0, Schedulemeetings::where('scheduleId', $schedule->scheduleId)->count());
    }

    #[Test]
    public function test_schedule_update_detects_conflict(): void
    {
        $blockingStaff = $this->staffForOffice(5);
        $this->actingAs($blockingStaff);

        // Create block with two schedules: A (Mon 9-10) and B (Mon 11-12)
        $fixture = $this->createBlockWithSchedule();
        $block = $fixture['block'];
        $scheduleA = $fixture['schedule'];
        $instructor = $fixture['instructor'];
        $room = $fixture['room'];

        // Create schedule B with different time
        $subjectB = Subjects::skip(1)->firstOrFail();
        $scheduleB = Schedules::create([
            'blockId' => $block->blockId,
            'subjectId' => $subjectB->subjectId,
            'instructorId' => $instructor->userId,
            'roomId' => $room->roomId,
        ]);
        Schedulemeetings::create([
            'scheduleId' => $scheduleB->scheduleId,
            'dayOfWeek' => 'Monday',
            'startTime' => '11:00:00',
            'endTime' => '12:00:00',
        ]);

        // Try to update schedule B to conflict with schedule A (same instructor, Mon 9-10)
        $response = $this->patch(route('blocking.schedules.update', $scheduleB), [
            'instructorId' => $instructor->userId,
            'roomId' => $room->roomId,
            'meetings' => [
                ['dayOfWeek' => 'Monday', 'startTime' => '09:00', 'endTime' => '10:00'],
            ],
        ]);

        // Should fail with validation errors
        $response->assertSessionHasErrors('conflicts');
        $errors = session('errors')->get('conflicts');
        $this->assertStringContainsString('Instructor conflict', $errors[0]);

        // Schedule B should NOT be updated (still has original time)
        $scheduleB->refresh();
        $scheduleB->load('meetings');
        $this->assertEquals('11:00:00', $scheduleB->meetings->first()->startTime);
        $this->assertEquals('12:00:00', $scheduleB->meetings->first()->endTime);
    }

    #[Test]
    public function test_unauthorized_office_cannot_assign(): void
    {
        // Create staff user for office 4 (Department Evaluation) for evaluatedBy
        $evalStaff = $this->staffForOffice(4);

        // Staff from office 1 (Registrar), not office 5 (Academic Department)
        $unauthorizedStaff = $this->staffForOffice(1);

        // Debug: check if staff exists and has role
        $this->assertNotNull($unauthorizedStaff->userId);
        $this->assertTrue($unauthorizedStaff->hasRole('OfficeHead'));

        $this->actingAs($unauthorizedStaff);

        // Create minimal block and schedule for this test
        $block = Blocks::create([
            'courseId' => $this->testCourseId,
            'termId' => $this->testTermId,
            'yearLevel' => 1,
            'blockName' => 'Test Block '.uniqid(),
            'maxStudents' => 40,
        ]);

        $subject = Subjects::firstOrFail();
        $instructor = $evalStaff; // Use the office-4 staff as instructor (already created)
        $room = Rooms::firstOrFail();

        $schedule = Schedules::create([
            'blockId' => $block->blockId,
            'subjectId' => $subject->subjectId,
            'instructorId' => $instructor->userId,
            'roomId' => $room->roomId,
        ]);
        Schedulemeetings::create([
            'scheduleId' => $schedule->scheduleId,
            'dayOfWeek' => 'Monday',
            'startTime' => '09:00:00',
            'endTime' => '10:00:00',
        ]);

        $enrollment = $this->createEnrollment();

        $response = $this->post(route('blocking.assign', $block), [
            'enrollmentIds' => [$enrollment->enrollmentId],
            'scheduleId' => $schedule->scheduleId,
        ]);

        // Should be forbidden (403)
        $response->assertForbidden();
    }

    #[Test]
    public function test_room_capacity_warning_on_store_schedule(): void
    {
        $blockingStaff = $this->staffForOffice(5);
        $this->actingAs($blockingStaff);

        // Create a room with small capacity
        $smallRoom = Rooms::create([
            'roomName' => 'Small Room',
            'capacity' => 10,
            'building' => 'Test Building',
        ]);

        $block = Blocks::create([
            'courseId' => 1,
            'termId' => 1,
            'yearLevel' => 1,
            'blockName' => 'Test Block Large',
            'maxStudents' => 50, // Exceeds room capacity
        ]);

        $subject = Subjects::firstOrFail();
        $instructor = Staffusers::where('officeId', '!=', 1)->firstOrFail();

        $response = $this->post(route('blocking.schedules.store', $block), [
            'subjectId' => $subject->subjectId,
            'instructorId' => $instructor->userId,
            'roomId' => $smallRoom->roomId,
            'meetings' => [
                ['dayOfWeek' => 'Monday', 'startTime' => '09:00', 'endTime' => '10:00'],
            ],
        ]);

        // Should succeed but with warning
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('warning');
        $warning = session('warning');
        $this->assertStringContainsString('exceeds room capacity', $warning);
    }

    #[Test]
    public function test_room_capacity_enforced_on_assign(): void
    {
        $blockingStaff = $this->staffForOffice(5);
        $this->actingAs($blockingStaff);

        // Create a room with capacity 1
        $smallRoom = Rooms::create([
            'roomName' => 'Tiny Room',
            'capacity' => 1,
            'building' => 'Test Building',
        ]);

        $block = Blocks::create([
            'courseId' => 1,
            'termId' => 1,
            'yearLevel' => 1,
            'blockName' => 'Test Block',
            'maxStudents' => 40,
        ]);

        $subject = Subjects::firstOrFail();
        $instructor = Staffusers::where('officeId', '!=', 1)->firstOrFail();

        $schedule = Schedules::create([
            'blockId' => $block->blockId,
            'subjectId' => $subject->subjectId,
            'instructorId' => $instructor->userId,
            'roomId' => $smallRoom->roomId,
        ]);
        Schedulemeetings::create([
            'scheduleId' => $schedule->scheduleId,
            'dayOfWeek' => 'Monday',
            'startTime' => '09:00:00',
            'endTime' => '10:00:00',
        ]);

        // Create two enrollments
        $enrollment1 = $this->createEnrollment();
        $enrollment2 = $this->createEnrollment();

        // Assign first student - should succeed
        $this->post(route('blocking.assign', $block), [
            'enrollmentIds' => [$enrollment1->enrollmentId],
            'scheduleId' => $schedule->scheduleId,
        ])->assertSessionHasNoErrors();

        // Assign second student - should fail with room capacity error
        $response = $this->post(route('blocking.assign', $block), [
            'enrollmentIds' => [$enrollment2->enrollmentId],
            'scheduleId' => $schedule->scheduleId,
        ]);
        $response->assertSessionHasErrors('room_capacity');
        $errors = session('errors')->get('room_capacity');
        $this->assertStringContainsString('Room capacity exceeded', $errors[0]);
    }

    #[Test]
    public function test_schedule_delete_blocked_when_enrolled_students_exist(): void
    {
        $blockingStaff = $this->staffForOffice(5);
        $this->actingAs($blockingStaff);

        $fixture = $this->createBlockWithSchedule();
        $block = $fixture['block'];
        $schedule = $fixture['schedule'];

        $enrollment = $this->createEnrollment();

        // Assign student to schedule
        $this->post(route('blocking.assign', $block), [
            'enrollmentIds' => [$enrollment->enrollmentId],
            'scheduleId' => $schedule->scheduleId,
        ])->assertSessionHasNoErrors();

        // Try to delete schedule - should fail
        $response = $this->delete(route('blocking.schedules.destroy', $schedule));
        $response->assertSessionHasErrors('schedule');
        $errors = session('errors')->get('schedule');
        $this->assertStringContainsString('Cannot delete schedule', $errors[0]);

        // Schedule should still exist
        $this->assertTrue(Schedules::where('scheduleId', $schedule->scheduleId)->exists());
    }
}
