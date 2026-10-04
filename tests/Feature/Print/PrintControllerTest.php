<?php

namespace Tests\Feature\Print;

use App\Enums\ClearanceApprovalStatus;
use App\Enums\ClearanceOverallStatus;
use App\Enums\ClearancePeriodStatus;
use App\Enums\DocumentType;
use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Blocks;
use App\Models\Clearanceapprovals;
use App\Models\Clearanceperiods;
use App\Models\Clearancerequirements;
use App\Models\Courses;
use App\Models\Documentprintlog;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Majors;
use App\Models\Offices;
use App\Models\Rooms;
use App\Models\Schedules;
use App\Models\Staffusers;
use App\Models\Studentclearances;
use App\Models\Students;
use App\Models\Subjects;
use App\Services\PrintedDocument;
use App\Services\PrintService;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PrintControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $termId;

    private int $courseId;

    private int $majorId;

    private array $subjectIds = [];

    private int $blockId;

    private array $scheduleIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Seed RBAC permissions and roles
        $this->seed(RbacSeeder::class);

        // Create reference data needed for enrollment
        $this->createReferenceData();
    }

    private function createReferenceData(): void
    {
        // Offices are created by RbacSeeder, but ensure they exist
        $offices = [
            1 => 'System Administration',
            2 => 'Accounting Office',
            3 => 'Assessment Office',
            4 => 'Department Evaluation',
            5 => 'Blocking and Scheduling',
            6 => 'Admission Office',
            7 => 'Guidance / Entrance Exam',
            11 => 'Clinic',
            22 => 'ID Office',
        ];
        foreach ($offices as $id => $name) {
            Offices::firstOrCreate(['officeId' => $id], ['officeName' => $name]);
        }

        // Religion (required for students).
        // Pin religionId to 1 on MySQL: RefreshDatabase rolls each test back but
        // AUTO_INCREMENT keeps climbing, so an unpinned insert lands on a new id
        // while Students fixture rows hard-code religionId 1 (SQLite does not
        // enforce FKs, so this only ever breaks on the MySQL CI job).
        DB::table('religions')->insert([
            'religionId' => 1,
            'religionName' => 'Roman Catholic',
        ]);

        // Academic unit (required for courses)
        $unit = Academicunits::create([
            'unitName' => 'College of Computer Studies',
            'unitType' => 'college',
        ]);

        // Academic year
        $academicYear = DB::table('academicyears')->insertGetId([
            'yearLabel' => '2024-2025',
            'startDate' => '2024-08-01',
            'endDate' => '2025-05-31',
        ]);

        // Academic term
        $term = Academicterms::create([
            'academicYearId' => $academicYear,
            'semester' => '1st',
            'startDate' => '2024-08-01',
            'endDate' => '2024-12-15',
        ]);
        $this->termId = $term->termId;

        // Course
        $course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);
        $this->courseId = $course->courseId;

        // Major
        $major = Majors::create([
            'courseId' => $this->courseId,
            'majorName' => 'Software Engineering',
        ]);
        $this->majorId = $major->majorId;

        // Subjects
        $subject1 = Subjects::create([
            'subjectCode' => 'CS101',
            'subjectName' => 'Introduction to Programming',
            'lectureUnits' => 3,
            'labUnits' => 0,
            'subjectType' => 'lecture',
        ]);
        $subject2 = Subjects::create([
            'subjectCode' => 'CS102',
            'subjectName' => 'Data Structures',
            'lectureUnits' => 3,
            'labUnits' => 0,
            'subjectType' => 'lecture',
        ]);
        $this->subjectIds = [$subject1->subjectId, $subject2->subjectId];

        // Room (for schedules)
        $room = Rooms::create([
            'roomName' => 'Room 101',
            'capacity' => 40,
            'building' => 'Main Building',
        ]);

        // Block
        $block = Blocks::create([
            'courseId' => $this->courseId,
            'termId' => $this->termId,
            'yearLevel' => 1,
            'blockName' => 'BSCS-1A',
            'maxStudents' => 40,
        ]);
        $this->blockId = $block->blockId;

        // Staff for instructor (office 4 - Department Evaluation)
        $instructor = Staffusers::factory()->create([
            'officeId' => 4,
            'role' => 'officeHead',
            'employeeNo' => 'EMP-INSTRUCTOR-'.uniqid(),
            'username' => 'instructor_'.uniqid(),
            'email' => 'instructor_'.uniqid().'@example.com',
        ]);
        $instructor->assignRole('OfficeHead');

        // Schedules for the block
        $schedule1 = Schedules::create([
            'blockId' => $this->blockId,
            'subjectId' => $this->subjectIds[0],
            'instructorId' => $instructor->userId,
            'roomId' => $room->roomId,
        ]);
        $schedule2 = Schedules::create([
            'blockId' => $this->blockId,
            'subjectId' => $this->subjectIds[1],
            'instructorId' => $instructor->userId,
            'roomId' => $room->roomId,
        ]);
        $this->scheduleIds = [$schedule1->scheduleId, $schedule2->scheduleId];

        // Clearance requirements (one per office for clearance test)
        $clearanceOffices = [1, 2, 3, 4, 5, 11, 22];
        foreach ($clearanceOffices as $officeId) {
            Clearancerequirements::firstOrCreate(
                ['officeId' => $officeId],
                ['officeId' => $officeId]
            );
        }
    }

    /**
     * Create a staff user with OfficeHead role in the given office.
     * Clear permission cache after role assignment to ensure permissions are recognized.
     */
    private function createStaffForOffice(int $officeId): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => $officeId,
            'role' => 'officeHead',
            'employeeNo' => 'EMP-PRINT-'.uniqid(),
            'username' => 'print_office'.$officeId.'_'.uniqid(),
            'email' => 'print_office'.$officeId.'_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('OfficeHead');

        // Clear permission cache to ensure role permissions are recognized
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    /**
     * Create a staff user WITHOUT print permissions (for unauthorized test).
     */
    private function createStaffWithoutPrintPermissions(int $officeId): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => $officeId,
            'role' => 'staff',
            'employeeNo' => 'EMP-NOPRINT-'.uniqid(),
            'username' => 'noprint_office'.$officeId.'_'.uniqid(),
            'email' => 'noprint_office'.$officeId.'_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('Staff'); // Staff role has only view permissions

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    /**
     * Create a student.
     */
    private function createStudent(): Students
    {
        return Students::create([
            'schoolIdNumber' => 'PRINT-'.uniqid(),
            'lastName' => 'PrintTest',
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
            'telephoneNumber' => null,
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'email' => 'print_student_'.uniqid().'@example.com',
            'username' => 'print_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);
    }

    /**
     * Create an enrolled enrollment with confirmed enrolled subjects.
     */
    private function createEnrolledEnrollment(Students $student): Enrollments
    {
        $evaluator = Staffusers::where('officeId', 4)->first();
        $registrar = Staffusers::where('officeId', 1)->first();

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $this->courseId,
            'majorId' => $this->majorId,
            'termId' => $this->termId,
            'yearLevel' => 1,
            'admissionId' => null,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => 'regular',
            'evaluatedBy' => $evaluator?->userId,
            'enrollmentStatus' => 'enrolled', // Use string to ensure correct DB value
            'registrarProcessedBy' => $registrar?->userId,
            'enrolledDate' => now(),
            'formIssuedDate' => now()->toDateString(),
        ]);

        // Create confirmed enrolled subjects
        foreach ($this->subjectIds as $index => $subjectId) {
            Enrolledsubjects::create([
                'enrollmentId' => $enrollment->enrollmentId,
                'subjectId' => $subjectId,
                'blockId' => $this->blockId,
                'scheduleId' => $this->scheduleIds[$index],
                'status' => EnrolledSubjectStatus::Confirmed,
            ]);
        }

        return $enrollment->fresh(['enrolledSubjects.subject']);
    }

    /**
     * Create a clearance slip for clearance print test.
     */
    private function createClearanceSlip(Students $student): Studentclearances
    {
        $period = Clearanceperiods::create([
            'termId' => $this->termId,
            'clearanceStartDate' => '2024-07-01',
            'clearanceEndDate' => '2024-07-31',
            'periodStatus' => ClearancePeriodStatus::Open,
        ]);

        $registrar = Staffusers::where('officeId', 1)->first();

        $clearance = Studentclearances::create([
            'studentId' => $student->studentId,
            'clearancePeriodId' => $period->clearancePeriodId,
            'overallStatus' => ClearanceOverallStatus::Approved,
            'receivedBy' => $registrar?->userId,
            'receivedDate' => now(),
        ]);

        // Create approvals for all clearance requirements
        $requirements = Clearancerequirements::all();
        foreach ($requirements as $req) {
            $approver = Staffusers::where('officeId', $req->officeId)->first();
            Clearanceapprovals::create([
                'studentClearanceId' => $clearance->studentClearanceId,
                'clearanceRequirementId' => $req->clearanceRequirementId,
                'status' => ClearanceApprovalStatus::Approved,
                'approvedBy' => $approver?->userId,
                'approvalDate' => now(),
                'remarks' => 'Approved for testing',
            ]);
        }

        return $clearance->fresh(['approvals.requirement.office']);
    }

    #[Test]
    public function test_registrar_can_print_certificate(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrolledEnrollment($student);

        // Verify enrollment status is correctly set to Enrolled in database
        $dbStatus = DB::table('enrollments')->where('enrollmentId', $enrollment->enrollmentId)->value('enrollmentStatus');
        $this->assertEquals('enrolled', $dbStatus);

        // Verify enrollment status is correctly set to Enrolled on model
        $this->assertEquals(EnrollmentStatus::Enrolled, $enrollment->enrollmentStatus);

        // Registrar staff (office 1) - OfficeHead role has print.certificate permission
        $registrarStaff = $this->createStaffForOffice(1);

        // Verify the user has the required permission
        $this->assertTrue($registrarStaff->hasPermissionTo('print.certificate'));

        $response = $this->actingAs($registrarStaff)
            ->get(route('registrar.print-certificate', $enrollment));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Registrar/PrintCertificate')
            ->has('enrollment'));

        // Assert Documentprintlog row created
        $this->assertDatabaseHas('documentprintlog', [
            'enrollmentId' => $enrollment->enrollmentId,
            'documentType' => DocumentType::Certificate,
            'printedBy' => $registrarStaff->userId,
            'documentNumber' => 1,
        ]);
    }

    #[Test]
    public function test_registrar_can_print_class_cards(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrolledEnrollment($student);

        // Verify enrollment status is correctly set to Enrolled
        $this->assertEquals(EnrollmentStatus::Enrolled, $enrollment->enrollmentStatus);

        // Registrar staff (office 1) - OfficeHead role has print.classCard permission
        $registrarStaff = $this->createStaffForOffice(1);

        // Verify the user has the required permission
        $this->assertTrue($registrarStaff->hasPermissionTo('print.classCard'));

        $response = $this->actingAs($registrarStaff)
            ->get(route('registrar.print-class-cards', $enrollment));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Registrar/PrintClassCards')
            ->has('enrollment'));

        // Assert Documentprintlog rows created (one per enrolled subject)
        $subjectCount = $enrollment->enrolledSubjects->count();
        $this->assertEquals(2, $subjectCount);

        $logs = Documentprintlog::where('enrollmentId', $enrollment->enrollmentId)
            ->where('documentType', DocumentType::ClassCard)
            ->where('printedBy', $registrarStaff->userId)
            ->orderBy('documentNumber')
            ->get();

        $this->assertCount($subjectCount, $logs);
        $this->assertEquals(1, $logs[0]->documentNumber);
        $this->assertEquals(2, $logs[1]->documentNumber);
    }

    #[Test]
    public function reprinting_class_cards_numbers_the_copies_instead_of_reusing_card_positions(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrolledEnrollment($student);
        $registrarStaff = $this->createStaffForOffice(1);

        $print = route('registrar.print-class-cards', $enrollment);
        $this->actingAs($registrarStaff)->get($print)->assertStatus(200);
        $this->actingAs($registrarStaff)->get($print)->assertStatus(200);

        $numbers = Documentprintlog::where('enrollmentId', $enrollment->enrollmentId)
            ->where('documentType', DocumentType::ClassCard)
            ->orderBy('printLogId')
            ->pluck('documentNumber')
            ->map(fn ($number) => (int) $number)
            ->all();

        // Two prints of the same two-card load are four copies handed out, not the
        // numbers 1 and 2 written twice. Numbering by card position made every repeat
        // print collide, so the trail could not count copies for a student at all.
        $this->assertSame([1, 2, 3, 4], $numbers);
    }

    #[Test]
    public function the_print_trail_refuses_two_copies_sharing_one_issuance_number(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrolledEnrollment($student);
        $registrarStaff = $this->createStaffForOffice(1);

        $this->actingAs($registrarStaff)
            ->get(route('registrar.print-certificate', $enrollment))
            ->assertStatus(200);

        // Copy #1 for this enrollment and document type is taken; the database, not
        // just the counting code, is what says a second one cannot be filed.
        $this->expectException(QueryException::class);

        Documentprintlog::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'documentType' => DocumentType::Certificate,
            'printedDate' => now(),
            'printedBy' => $registrarStaff->userId,
            'documentNumber' => 1,
        ]);
    }

    #[Test]
    public function test_registrar_can_print_subject_load(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrolledEnrollment($student);

        // Verify enrollment status is correctly set to Enrolled
        $this->assertEquals(EnrollmentStatus::Enrolled, $enrollment->enrollmentStatus);

        // Registrar staff (office 1) - OfficeHead role has print.subjectLoad permission
        $registrarStaff = $this->createStaffForOffice(1);

        // Verify the user has the required permission
        $this->assertTrue($registrarStaff->hasPermissionTo('print.subjectLoad'));

        $response = $this->actingAs($registrarStaff)
            ->get(route('registrar.print-subject-load', $enrollment));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Registrar/PrintSubjectLoad')
            ->has('enrollment'));

        // Assert Documentprintlog row created
        $this->assertDatabaseHas('documentprintlog', [
            'enrollmentId' => $enrollment->enrollmentId,
            'documentType' => DocumentType::SubjectLoad,
            'printedBy' => $registrarStaff->userId,
            'documentNumber' => 1,
        ]);
    }

    #[Test]
    public function test_clearance_slip_prints(): void
    {
        $student = $this->createStudent();
        $clearance = $this->createClearanceSlip($student);

        // The clearance counter is the Registrar's office (1) since ruling 12 folded the
        // legacy Clearance department into it; OfficeHead holds clearance.view either way.
        $clearanceStaff = $this->createStaffForOffice(1);

        $this->assertTrue($clearanceStaff->hasPermissionTo('clearance.view'));

        $response = $this->actingAs($clearanceStaff)
            ->get(route('clearance.print-slip', $clearance));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Clearance/PrintSlip')
            ->has('clearance'));

        // Assert Documentprintlog row created (BR: every print inserts a log row)
        $this->assertDatabaseHas('documentprintlog', [
            'documentType' => DocumentType::ClearanceSlip->value,
            'printedBy' => $clearanceStaff->userId,
        ]);
    }

    #[Test]
    public function test_block_schedule_prints(): void
    {
        // Blocking staff (office 5) - OfficeHead role does NOT have print.blockSchedule by default
        // Grant it explicitly
        $blockingStaff = $this->createStaffForOffice(5);
        $blockingStaff->givePermissionTo('print.blockSchedule');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->assertTrue($blockingStaff->hasPermissionTo('print.blockSchedule'));

        $block = Blocks::findOrFail($this->blockId);

        $response = $this->actingAs($blockingStaff)
            ->get(route('blocking.print-schedule', $block));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Blocking/PrintSchedule')
            ->has('block'));

        // BR: every print inserts a log row — a block schedule printed from the
        // desk must be as visible in the trail as one rendered to PDF.
        $this->assertDatabaseHas('documentprintlog', [
            'documentType' => DocumentType::BlockSchedule->value,
            'printedBy' => $blockingStaff->userId,
        ]);
    }

    #[Test]
    public function test_unauthorized_user_cannot_print(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrolledEnrollment($student);

        // Staff WITHOUT print.certificate permission (Staff role only has view permissions)
        $unauthorizedStaff = $this->createStaffWithoutPrintPermissions(1);

        $this->assertFalse($unauthorizedStaff->hasPermissionTo('print.certificate'));

        $response = $this->actingAs($unauthorizedStaff)
            ->get(route('registrar.print-certificate', $enrollment));

        $response->assertStatus(403);
    }

    #[Test]
    public function test_registrar_can_download_the_certificate_pdf(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrolledEnrollment($student);
        $registrarStaff = $this->createStaffForOffice(1);

        $this->bindFakePrintService();

        $this->actingAs($registrarStaff)
            ->get(route('registrar.download-certificate', $enrollment))
            ->assertStatus(200)
            ->assertDownload("certificate-{$student->schoolIdNumber}-{$enrollment->enrollmentId}.pdf");

        $this->assertDatabaseHas('documentprintlog', [
            'enrollmentId' => $enrollment->enrollmentId,
            'documentType' => DocumentType::Certificate,
            'printedBy' => $registrarStaff->userId,
            'documentNumber' => 1,
        ]);

        // A second saved copy is a second issuance, numbered after the first.
        $this->actingAs($registrarStaff)
            ->get(route('registrar.download-certificate', $enrollment))
            ->assertStatus(200);

        $this->assertDatabaseHas('documentprintlog', [
            'enrollmentId' => $enrollment->enrollmentId,
            'documentType' => DocumentType::Certificate,
            'documentNumber' => 2,
        ]);
    }

    #[Test]
    public function test_a_download_of_another_students_class_card_is_not_found(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrolledEnrollment($student);
        $otherEnrollment = $this->createEnrolledEnrollment($this->createStudent());
        $registrarStaff = $this->createStaffForOffice(1);

        $this->bindFakePrintService();

        $foreignCard = $otherEnrollment->enrolledSubjects->first();

        $this->actingAs($registrarStaff)
            ->get(route('registrar.download-class-card', [
                'enrollment' => $enrollment->enrollmentId,
                'enrolledSubject' => $foreignCard->enrolledSubjectId,
            ]))
            ->assertStatus(404);

        $this->assertDatabaseMissing('documentprintlog', [
            'documentType' => DocumentType::ClassCard,
            'enrollmentId' => $enrollment->enrollmentId,
        ]);
    }

    #[Test]
    public function test_unauthorized_user_cannot_download(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrolledEnrollment($student);
        $unauthorizedStaff = $this->createStaffWithoutPrintPermissions(1);

        $this->actingAs($unauthorizedStaff)
            ->get(route('registrar.download-subject-load', $enrollment))
            ->assertStatus(403);
    }

    #[Test]
    public function a_slip_printed_before_any_enrollment_exists_is_still_counted_against_its_student(): void
    {
        $clearanceStaff = $this->createStaffForOffice(1);
        $print = fn (Studentclearances $c) => $this->actingAs($clearanceStaff)->get(route('clearance.print-slip', $c));

        // The clearance desk draws a slip for a student in a period. Nothing in that
        // flow requires an enrollment, so the row used to be filed with enrollmentId
        // NULL — a group MySQL considers unrelated to every other NULL, which let two
        // students' slips carry the same document number (§25.10).
        $first = $this->createClearanceSlip($this->createStudent());
        $second = $this->createClearanceSlip($this->createStudent());

        $print($first)->assertStatus(200);
        $print($first)->assertStatus(200);
        $print($second)->assertStatus(200);

        $this->assertDatabaseHas('documentprintlog', [
            'studentId' => $first->studentId,
            'enrollmentId' => null,
            'documentType' => DocumentType::ClearanceSlip->value,
            'documentNumber' => 2,
        ]);

        // The other student's copy counts against that student, not against the first
        // one's bucket or against a shared pile of unattributed rows.
        $this->assertDatabaseHas('documentprintlog', [
            'studentId' => $second->studentId,
            'documentType' => DocumentType::ClearanceSlip->value,
            'documentNumber' => 1,
        ]);

        $this->assertSame(
            [1, 2],
            Documentprintlog::where('studentId', $first->studentId)
                ->where('documentType', DocumentType::ClearanceSlip)
                ->orderBy('printLogId')
                ->pluck('documentNumber')
                ->map(fn ($n) => (int) $n)
                ->all()
        );
    }

    #[Test]
    public function a_fresh_issue_never_repeats_a_number_an_unattributable_row_already_printed(): void
    {
        $clearanceStaff = $this->createStaffForOffice(1);

        // The legacy pile: issuance rows carrying no enrollment, student or block key,
        // which ruling 6 keeps and no scope counts. Without the skip, a new student's
        // first slip would be issued number 1 — the same number a historical slip already
        // went out under, and the unique index cannot object because MySQL compares the
        // NULL keys as unrelated.
        foreach ([1, 2] as $taken) {
            Documentprintlog::create([
                'enrollmentId' => null,
                'studentId' => null,
                'blockId' => null,
                'documentType' => DocumentType::ClearanceSlip,
                'printedDate' => now(),
                'printedBy' => $clearanceStaff->userId,
                'documentNumber' => $taken,
            ]);
        }

        $slip = $this->createClearanceSlip($this->createStudent());
        $print = fn () => $this->actingAs($clearanceStaff)->get(route('clearance.print-slip', $slip));

        $print()->assertStatus(200);
        $print()->assertStatus(200);

        $numbers = Documentprintlog::where('studentId', $slip->studentId)
            ->where('documentType', DocumentType::ClearanceSlip)
            ->orderBy('printLogId')
            ->pluck('documentNumber')
            ->map(fn ($n) => (int) $n)
            ->all();

        $this->assertSame([3, 4], $numbers);

        // And no number is issued twice across the whole document type.
        $this->assertSame(
            count($all = Documentprintlog::where('documentType', DocumentType::ClearanceSlip)
                ->pluck('documentNumber')->map(fn ($n) => (int) $n)->all()),
            count(array_unique($all)),
            'clearance slip numbers must be unique across every scope'
        );
    }

    #[Test]
    public function a_block_roster_is_attributed_to_the_block_it_covers(): void
    {
        $blockingStaff = $this->createStaffForOffice(5);
        $blockingStaff->givePermissionTo('print.blockSchedule');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $block = Blocks::findOrFail($this->blockId);

        // Logged to the first enrollment in the block, a roster was numbered as if one
        // student owned it — and an empty block logged nothing to point at at all.
        $this->actingAs($blockingStaff)->get(route('blocking.print-schedule', $block))->assertStatus(200);
        $this->actingAs($blockingStaff)->get(route('blocking.print-schedule', $block))->assertStatus(200);

        $rows = Documentprintlog::where('documentType', DocumentType::BlockSchedule)
            ->orderBy('printLogId')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame([$block->blockId, $block->blockId], $rows->pluck('blockId')->all());
        $this->assertSame([null, null], $rows->pluck('enrollmentId')->all());
        $this->assertSame([1, 2], $rows->pluck('documentNumber')->map(fn ($n) => (int) $n)->all());
    }

    #[Test]
    public function a_copy_issued_against_an_enrollment_also_names_the_student_it_belongs_to(): void
    {
        $student = $this->createStudent();
        $enrollment = $this->createEnrolledEnrollment($student);
        $registrarStaff = $this->createStaffForOffice(1);

        $this->actingAs($registrarStaff)
            ->get(route('registrar.print-certificate', $enrollment))
            ->assertStatus(200);

        // Both keys on one row: the enrollment the certificate certifies, and the
        // student holding it, so the trail answers either question without a join.
        $this->assertDatabaseHas('documentprintlog', [
            'enrollmentId' => $enrollment->enrollmentId,
            'studentId' => $student->studentId,
            'documentType' => DocumentType::Certificate->value,
            'documentNumber' => 1,
        ]);
    }

    /**
     * Swap in a PrintService that writes a stand-in file instead of driving a
     * browser, so the suite covers the download routes' authorization and audit
     * behaviour without depending on a local Chrome install.
     */
    private function bindFakePrintService(): void
    {
        $fake = new class extends PrintService
        {
            public function printEnrollmentCertificate(Enrollments $enrollment, int $printedBy): PrintedDocument
            {
                return new PrintedDocument(
                    $this->recordIssue($enrollment->enrollmentId, DocumentType::Certificate, $printedBy, $enrollment->studentId),
                    $this->placeholder('certificate')
                );
            }

            public function printClassCard(
                Enrollments $enrollment,
                Enrolledsubjects $enrolledSubject,
                int $printedBy
            ): PrintedDocument {
                return new PrintedDocument(
                    $this->recordIssue($enrollment->enrollmentId, DocumentType::ClassCard, $printedBy, $enrollment->studentId),
                    $this->placeholder('class-card')
                );
            }

            private function placeholder(string $prefix): string
            {
                // The real PrintService creates this folder before writing; the
                // fake has to as well, or the download tests only pass on a
                // developer machine that already ran the fidelity command.
                $dir = storage_path('app/prints');
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $path = $dir.'/'.$prefix.'-'.uniqid().'.pdf';
                file_put_contents($path, '%PDF-1.4 placeholder');

                return $path;
            }
        };

        $this->app->instance(PrintService::class, $fake);
    }
}
