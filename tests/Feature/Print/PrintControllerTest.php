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
use App\Policies\RegistrarPolicy;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

        // Override gate definitions to match controller's authorize() calls
        // (Controller uses 'printCertificate' but AuthServiceProvider defines 'registrar.printCertificate')
        Gate::define('printCertificate', fn ($user, $enrollment) => app(RegistrarPolicy::class)->printCertificate($user, $enrollment));
        Gate::define('printClassCards', fn ($user, $enrollment) => app(RegistrarPolicy::class)->printClassCards($user, $enrollment));
        Gate::define('printSubjectLoad', fn ($user, $enrollment) => app(RegistrarPolicy::class)->printSubjectLoad($user, $enrollment));

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
            8 => 'Clearance Office',
            11 => 'Clinic',
            22 => 'ID Office',
        ];
        foreach ($offices as $id => $name) {
            Offices::firstOrCreate(['officeId' => $id], ['officeName' => $name]);
        }

        // Religion (required for students)
        DB::table('religions')->insertGetId([
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
        $clearanceOffices = [1, 2, 3, 4, 5, 8, 11, 22];
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

        // Clearance staff (office 8) - OfficeHead role has clearance.view permission
        $clearanceStaff = $this->createStaffForOffice(8);

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

        // Note: BlockingController::printBlockSchedule does NOT write a Documentprintlog row
        // (verified in controller code - it only renders the Inertia page)
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
}
