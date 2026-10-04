<?php

namespace Tests\Feature\Blocking;

use App\Enums\EnrollmentStatus;
use App\Enums\StaffRole;
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
     * Create a staff user in the given office; defaults to the desk's OfficeHead.
     */
    private function staffForOffice(int $officeId, StaffRole $role = StaffRole::OfficeHead): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => $officeId,
            'role' => $role,
            'employeeNo' => 'EMP-TEST-'.uniqid(),
            'username' => 'test_office'.$officeId.'_'.uniqid(),
            'email' => 'test_office'.$officeId.'_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();

        $staff->assignRole(ucfirst($role->value));

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
    public function test_schedule_update_allows_shifting_own_time(): void
    {
        $blockingStaff = $this->staffForOffice(5);
        $this->actingAs($blockingStaff);

        // Moving a schedule's own meeting to an overlapping range (Mon 9-10 →
        // Mon 9-11, same instructor and room) must not flag a conflict against
        // its own still-present old meeting — the old meeting is replaced.
        $fixture = $this->createBlockWithSchedule();
        $schedule = $fixture['schedule'];

        $response = $this->patch(route('blocking.schedules.update', $schedule), [
            'instructorId' => $fixture['instructor']->userId,
            'roomId' => $fixture['room']->roomId,
            'meetings' => [
                ['dayOfWeek' => 'Monday', 'startTime' => '09:00', 'endTime' => '11:00'],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $schedule->refresh();
        $schedule->load('meetings');
        $this->assertEquals('09:00', $schedule->meetings->first()->startTime);
        $this->assertEquals('11:00', $schedule->meetings->first()->endTime);
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

        // Should succeed but with warning — and the schedule must actually be
        // persisted: the early-return regression would pass this test while
        // silently dropping the save.
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('warning');
        $warning = session('warning');
        $this->assertStringContainsString('exceeds room capacity', $warning);
        $this->assertEquals(1, Schedules::where('blockId', $block->blockId)->count(), 'Warning-only path must still persist the schedule');
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

    #[Test]
    public function test_finalize_marks_block_schedule_final(): void
    {
        $blockingStaff = $this->staffForOffice(5);
        $this->actingAs($blockingStaff);

        $fixture = $this->createBlockWithSchedule();
        $block = $fixture['block'];

        $this->patch(route('blocking.finalize', $block))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('final', $block->fresh()->scheduleStatus);
    }

    #[Test]
    public function test_finalize_is_idempotent(): void
    {
        $blockingStaff = $this->staffForOffice(5);
        $this->actingAs($blockingStaff);

        $fixture = $this->createBlockWithSchedule();
        $block = $fixture['block'];

        $this->patch(route('blocking.finalize', $block))->assertRedirect();
        $this->assertSame('final', $block->fresh()->scheduleStatus);

        // Re-clicking Finalize on an already-final block returns an info
        // flash (mirrors the assessment finalize pattern), not an error.
        $this->patch(route('blocking.finalize', $block))
            ->assertRedirect()
            ->assertSessionHas('info');

        $this->assertSame('final', $block->fresh()->scheduleStatus);
    }

    #[Test]
    public function test_finalized_block_rejects_schedule_changes(): void
    {
        $blockingStaff = $this->staffForOffice(5);
        $this->actingAs($blockingStaff);

        $fixture = $this->createBlockWithSchedule();
        $block = $fixture['block'];
        $schedule = $fixture['schedule'];
        $block->update(['scheduleStatus' => 'final']);

        // Store: adding a slot to a finalized timetable is rejected
        $this->post(route('blocking.schedules.store', $block), [
            'subjectId' => Subjects::firstOrFail()->subjectId,
            'instructorId' => Staffusers::where('officeId', '!=', 1)->firstOrFail()->userId,
            'roomId' => Rooms::firstOrFail()->roomId,
            'meetings' => [['dayOfWeek' => 'Tuesday', 'startTime' => '09:00', 'endTime' => '10:00']],
        ])->assertSessionHasErrors('schedule');

        // Update: editing an existing slot is rejected
        $this->patch(route('blocking.schedules.update', $schedule), [
            'meetings' => [['dayOfWeek' => 'Tuesday', 'startTime' => '09:00', 'endTime' => '10:00']],
        ])->assertSessionHasErrors('schedule');

        // Destroy: deleting a slot is rejected
        $this->delete(route('blocking.schedules.destroy', $schedule))->assertSessionHasErrors('schedule');

        // Nothing changed
        $this->assertSame(1, $schedule->fresh()->meetings()->count());

        // Capacity stays editable on a finalized block (item 9)
        $this->patch(route('blocking.update', $block), [
            'blockName' => 'Renamed Block',
            'maxStudents' => 50,
        ])->assertSessionHasNoErrors();
        $this->assertSame(50, $block->fresh()->maxStudents);
    }

    #[Test]
    public function test_a_block_created_on_screen_reopens_as_the_same_block(): void
    {
        $blockingStaff = $this->staffForOffice(5);
        $this->actingAs($blockingStaff);

        $response = $this->post(route('blocking.store'), [
            'courseId' => $this->testCourseId,
            'termId' => $this->testTermId,
            'yearLevel' => 1,
            'blockName' => 'BSCS-1A',
            'maxStudents' => 40,
        ]);

        $block = Blocks::where('blockName', 'BSCS-1A')->firstOrFail();
        $response->assertRedirect(route('blocking.show', $block))->assertSessionHas('success');

        // A new block opens as an editable draft: finalize() and destroy() both
        // branch on scheduleStatus, so a blank default would lock the desk out.
        $this->assertSame('draft', $block->scheduleStatus);
        $this->assertSame(0, $block->schedules()->count());

        $payload = [
            'courseId' => $this->testCourseId,
            'termId' => $this->testTermId,
            'yearLevel' => 1,
            'blockName' => 'BSCS-1B',
            'maxStudents' => 40,
        ];

        foreach (['blockName' => '', 'yearLevel' => 6, 'maxStudents' => 0, 'courseId' => 99999] as $field => $badValue) {
            $this->from(route('blocking.index'))
                ->post(route('blocking.store'), array_merge($payload, [$field => $badValue]))
                ->assertSessionHasErrors($field);

            $this->assertDatabaseMissing('blocks', ['blockName' => 'BSCS-1B']);
        }
    }

    #[Test]
    public function test_a_view_only_desk_cannot_create_or_delete_a_block(): void
    {
        // The Staff role holds block.view but not block.manage: reading the roster
        // must not quietly carry the right to build or dismantle one.
        $viewer = $this->staffForOffice(5, StaffRole::Staff);
        $this->actingAs($viewer);

        $fixture = $this->createBlockWithSchedule();

        $this->post(route('blocking.store'), [
            'courseId' => $this->testCourseId,
            'termId' => $this->testTermId,
            'yearLevel' => 1,
            'blockName' => 'BSCS-1C',
            'maxStudents' => 40,
        ])->assertForbidden();

        $this->delete(route('blocking.destroy', $fixture['block']))->assertForbidden();

        $this->assertDatabaseMissing('blocks', ['blockName' => 'BSCS-1C']);
        $this->assertDatabaseHas('blocks', ['blockId' => $fixture['block']->blockId]);
    }

    #[Test]
    public function test_delete_is_refused_while_the_block_still_names_a_schedule_or_a_seated_student(): void
    {
        $blockingStaff = $this->staffForOffice(5);
        $this->actingAs($blockingStaff);

        $fixture = $this->createBlockWithSchedule();
        $block = $fixture['block'];
        $schedule = $fixture['schedule'];

        // Scheduled but nobody seated yet: the schedule alone blocks the delete,
        // because schedules.blockId is a RESTRICT key and would raise a raw
        // database error page instead of a message.
        $this->delete(route('blocking.destroy', $block))
            ->assertRedirect()
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, '1 schedule(s)')
                && ! str_contains($message, 'enrolled subject'));

        $this->assertDatabaseHas('blocks', ['blockId' => $block->blockId]);

        $enrollment = $this->createEnrollment();
        $this->post(route('blocking.assign', $block), [
            'enrollmentIds' => [$enrollment->enrollmentId],
            'scheduleId' => $schedule->scheduleId,
        ])->assertSessionHasNoErrors();

        // Every non-dropped subject row of the seated student carries the blockId
        $this->delete(route('blocking.destroy', $block))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, '2 enrolled subject(s)'));

        $this->assertDatabaseHas('blocks', ['blockId' => $block->blockId]);

        // Clearing the children in the order the desk works makes the delete legal
        $this->post(route('blocking.unassign', $block), [
            'enrollmentIds' => [$enrollment->enrollmentId],
        ])->assertSessionHasNoErrors();

        $this->delete(route('blocking.schedules.destroy', $schedule))->assertSessionHasNoErrors();

        $this->delete(route('blocking.destroy', $block))
            ->assertRedirect(route('blocking.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('blocks', ['blockId' => $block->blockId]);
    }

    #[Test]
    public function the_roster_counts_one_seat_per_student_not_per_subject_row(): void
    {
        $this->actingAs($this->staffForOffice(5));

        $fixture = $this->createBlockWithSchedule(maxStudents: 40);
        $block = $fixture['block'];
        $schedule = $fixture['schedule'];

        // Two students, each carrying the block's two subjects. Counting rows would
        // report four of forty seats gone and then refuse students the block can still
        // hold, because assignStudents() measures maxStudents in students.
        $seated = [$this->createEnrollment(), $this->createEnrollment()];
        foreach ($seated as $enrollment) {
            Enrolledsubjects::where('enrollmentId', $enrollment->enrollmentId)
                ->update(['blockId' => $block->blockId, 'scheduleId' => $schedule->scheduleId]);
        }
        $this->assertSame(4, Enrolledsubjects::where('blockId', $block->blockId)->count());

        // A student who left the block reserves nothing.
        $left = $this->createEnrollment();
        Enrolledsubjects::where('enrollmentId', $left->enrollmentId)
            ->update(['blockId' => $block->blockId, 'scheduleId' => $schedule->scheduleId, 'status' => 'dropped']);

        $row = collect($this->get(route('blocking.index'))
            ->assertOk()
            ->viewData('page')['props']['blocks']['data'])
            ->firstWhere('blockId', $block->blockId);

        $this->assertSame(2, $row['students_in_block_count']);

        // The detail page reads the same two seats, not the four rows behind them.
        $this->get(route('blocking.show', $block))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('enrolled', 2)
                ->where('capacity', 40));
    }
}
