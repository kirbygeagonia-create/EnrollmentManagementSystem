<?php

namespace Tests\Feature\ID;

use App\Enums\EnrollmentStatus;
use App\Enums\IdRequestReason;
use App\Enums\IdRequestStatus;
use App\Enums\IdValidationStatus;
use App\Enums\UnitType;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowStepStatus;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Idrequests;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentids;
use App\Models\Students;
use App\Models\Workflowsteps;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IDControllerTest extends TestCase
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

        // Create a workflow with all steps before ID Office (office 22) completed,
        // so the ID Office step is the next pending step.
        // Steps for firstYear: [4 Dept Eval, 3 Assessment, 2 Accounting, 1 Registrar, 5 Blocking, 11 Clinic, 22 ID]
        $this->createWorkflowAtIDStep($enrollment);

        return $enrollment;
    }

    /**
     * Create a workflow for the enrollment with all steps before the ID Office
     * step (office 22) marked completed, so office 22 is the next pending step.
     */
    private function createWorkflowAtIDStep(Enrollments $enrollment): void
    {
        $workflow = Enrollmentworkflow::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'currentStep' => 6, // Clinic (step 6) completed; ID Office (step 7) is next
            'workflowStatus' => WorkflowStatus::InProgress,
        ]);

        // Steps in order: [4, 3, 2, 1, 5, 11, 22] (firstYear per WorkflowService)
        $steps = [
            [4, 1],  // Dept Eval — completed
            [3, 2],  // Assessment — completed
            [2, 3],  // Accounting — completed
            [1, 4],  // Registrar — completed
            [5, 5],  // Blocking — completed
            [11, 6], // Clinic — completed
            [22, 7], // ID Office — pending (next)
        ];

        $signer = Staffusers::where('officeId', 4)->first() ?? Staffusers::first();

        foreach ($steps as [$officeId, $order]) {
            Workflowsteps::create([
                'workflowId' => $workflow->workflowId,
                'officeId' => $officeId,
                'stepOrder' => $order,
                'stepStatus' => $order <= 6 ? WorkflowStepStatus::Completed : WorkflowStepStatus::Pending,
                'signedBy' => $order <= 6 ? $signer->userId : null,
                'signedDate' => $order <= 6 ? now() : null,
            ]);
        }
    }

    /**
     * Create a workflow where Clinic (office 11) is still pending — used for
     * testing wrong workflow position (test 9).
     */
    private function createWorkflowAtClinicStep(Enrollments $enrollment): void
    {
        $workflow = Enrollmentworkflow::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'currentStep' => 5, // Blocking (step 5) completed; Clinic (step 6) is next
            'workflowStatus' => WorkflowStatus::InProgress,
        ]);

        $steps = [
            [4, 1],  // Dept Eval — completed
            [3, 2],  // Assessment — completed
            [2, 3],  // Accounting — completed
            [1, 4],  // Registrar — completed
            [5, 5],  // Blocking — completed
            [11, 6], // Clinic — pending (next)
            [22, 7], // ID Office — pending
        ];

        $signer = Staffusers::where('officeId', 4)->first() ?? Staffusers::first();

        foreach ($steps as [$officeId, $order]) {
            Workflowsteps::create([
                'workflowId' => $workflow->workflowId,
                'officeId' => $officeId,
                'stepOrder' => $order,
                'stepStatus' => $order <= 5 ? WorkflowStepStatus::Completed : WorkflowStepStatus::Pending,
                'signedBy' => $order <= 5 ? $signer->userId : null,
                'signedDate' => $order <= 5 ? now() : null,
            ]);
        }
    }

    #[Test]
    public function test_index_shows_only_enrollments_at_id_stage(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        // Enrollment at ID stage (Clinic completed, ID pending)
        $enrollmentAtID = $this->createEnrollment();

        // Enrollment still at Clinic stage (Blocking completed, Clinic pending)
        $enrollmentAtClinic = $this->createEnrollment();
        $enrollmentAtClinic->enrollmentworkflow->workflowsteps()->delete();
        $enrollmentAtClinic->enrollmentworkflow->delete();
        $this->createWorkflowAtClinicStep($enrollmentAtClinic);

        $response = $this->get(route('id.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('ID/Index')
            ->has('enrollments.data', 1)
            ->where('enrollments.data.0.enrollmentId', $enrollmentAtID->enrollmentId)
        );
    }

    #[Test]
    public function test_index_respects_search_by_student_name(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment1 = $this->createEnrollment();
        $enrollment1->student->update(['lastName' => 'Smith', 'firstName' => 'John']);

        $enrollment2 = $this->createEnrollment();
        $enrollment2->student->update(['lastName' => 'Doe', 'firstName' => 'Jane']);

        // Search by last name
        $response = $this->get(route('id.index', ['search' => 'Smith']));
        $response->assertInertia(fn ($page) => $page->has('enrollments.data', 1)
            ->where('enrollments.data.0.student.lastName', 'Smith')
        );

        // Search by first name
        $response = $this->get(route('id.index', ['search' => 'Jane']));
        $response->assertInertia(fn ($page) => $page->has('enrollments.data', 1)
            ->where('enrollments.data.0.student.firstName', 'Jane')
        );
    }

    #[Test]
    public function test_index_respects_search_by_school_id_number(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();
        $schoolId = $enrollment->student->schoolIdNumber;

        $response = $this->get(route('id.index', ['search' => $schoolId]));
        $response->assertInertia(fn ($page) => $page->has('enrollments.data', 1)
            ->where('enrollments.data.0.student.schoolIdNumber', $schoolId)
        );
    }

    #[Test]
    public function test_show_renders_id_form_with_null_idrequest_and_studentid_initially(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        $response = $this->get(route('id.show', $enrollment));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('ID/Show')
            ->has('enrollment')
            ->where('idRequest', null)
            ->where('studentId', null)
            ->has('requestReasons')
        );
    }

    #[Test]
    public function test_show_contains_idrequest_after_create(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        // Create ID request
        $this->post(route('id.create', $enrollment), [
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Emergency Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
            'cardPhotoPath' => null,
            'producedByVendor' => null,
        ])->assertSessionHasNoErrors();

        // Show should now contain the idRequest
        $response = $this->get(route('id.show', $enrollment));
        $response->assertInertia(fn ($page) => $page->where('idRequest.requestReason', 'newStudent')
            ->where('idRequest.status', 'pending')
            ->where('studentId', null)
        );
    }

    #[Test]
    public function test_create_creates_idrequests_row_with_pending_status_and_request_date(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        $response = $this->post(route('id.create', $enrollment), [
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Emergency Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
            'cardPhotoPath' => '/photos/card.jpg',
            'producedByVendor' => 'Vendor Inc',
        ]);

        $response->assertRedirect(route('id.show', $enrollment));
        $response->assertSessionHas('success', 'ID request created.');

        $idRequest = Idrequests::where('enrollmentId', $enrollment->enrollmentId)->first();
        $this->assertNotNull($idRequest);
        $this->assertEquals(IdRequestReason::NewStudent, $idRequest->requestReason);
        $this->assertEquals('Emergency Contact', $idRequest->emergencyContactName);
        $this->assertEquals('09171234569', $idRequest->emergencyContactNumber);
        $this->assertEquals('O+', $idRequest->bloodType);
        $this->assertEquals('/photos/card.jpg', $idRequest->cardPhotoPath);
        $this->assertEquals('Vendor Inc', $idRequest->producedByVendor);
        $this->assertEquals(IdRequestStatus::Pending, $idRequest->status);
        $this->assertNotNull($idRequest->requestDate);
    }

    #[Test]
    public function test_create_validation_missing_required_fields(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        $response = $this->post(route('id.create', $enrollment), [
            'requestReason' => '',
            'emergencyContactName' => '',
            'emergencyContactNumber' => '',
            'bloodType' => '',
        ]);

        $response->assertSessionHasErrors(['requestReason', 'emergencyContactName', 'emergencyContactNumber', 'bloodType']);
    }

    #[Test]
    public function test_create_validation_invalid_request_reason(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        $response = $this->post(route('id.create', $enrollment), [
            'requestReason' => 'invalidReason',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
        ]);

        $response->assertSessionHasErrors('requestReason');
    }

    #[Test]
    public function test_create_validation_invalid_blood_type(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        $response = $this->post(route('id.create', $enrollment), [
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'X+',
        ]);

        $response->assertSessionHasErrors('bloodType');
    }

    #[Test]
    public function test_produce_card_creates_studentids_row_and_updates_request_status(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        // Create ID request first
        $this->post(route('id.create', $enrollment), [
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
        ])->assertSessionHasNoErrors();

        $idRequest = $enrollment->fresh()->idrequests->first();
        $qrCode = 'QR-'.uniqid();

        $response = $this->post(route('id.produce', $idRequest), [
            'qrCode' => $qrCode,
            'securityPhotoPath' => '/photos/security.jpg',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'ID card produced.');

        $studentId = Studentids::where('idRequestId', $idRequest->idRequestId)->first();
        $this->assertNotNull($studentId);
        $this->assertEquals($qrCode, $studentId->qrCode);
        $this->assertEquals('/photos/security.jpg', $studentId->securityPhotoPath);
        $this->assertEquals(IdValidationStatus::PendingValidation, $studentId->validationStatus);
        $this->assertNotNull($studentId->issueDate);
        $this->assertEquals($enrollment->studentId, $studentId->studentId);

        // Request status should be updated to CardProduced
        $idRequest->refresh();
        $this->assertEquals(IdRequestStatus::CardProduced, $idRequest->status);
    }

    #[Test]
    public function test_produce_card_rejects_duplicate_qr_code(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment1 = $this->createEnrollment();
        $enrollment2 = $this->createEnrollment();

        // Create first ID request and produce card
        $this->post(route('id.create', $enrollment1), [
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
        ])->assertSessionHasNoErrors();

        $idRequest1 = $enrollment1->fresh()->idrequests->first();
        $qrCode = 'DUPLICATE-QR-'.uniqid();

        $this->post(route('id.produce', $idRequest1), [
            'qrCode' => $qrCode,
            'securityPhotoPath' => null,
        ])->assertSessionHasNoErrors();

        // Create second ID request
        $this->post(route('id.create', $enrollment2), [
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
        ])->assertSessionHasNoErrors();

        $idRequest2 = $enrollment2->fresh()->idrequests->first();

        // Try to produce card with same QR code — unique constraint violation
        // Laravel validation catches unique:studentids,qrCode before DB insert,
        // so we expect a validation error (session error on qrCode).
        $response = $this->post(route('id.produce', $idRequest2), [
            'qrCode' => $qrCode,
            'securityPhotoPath' => null,
        ]);

        // The unique rule in validation should catch this and return session error
        $response->assertSessionHasErrors('qrCode');
        $errors = session('errors')?->get('qrCode') ?? [];
        $this->assertStringContainsString('already been taken', $errors[0] ?? '');
    }

    #[Test]
    public function test_validate_sets_studentid_active_and_signs_workflow_step(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        // Create ID request
        $this->post(route('id.create', $enrollment), [
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
        ])->assertSessionHasNoErrors();

        $idRequest = $enrollment->fresh()->idrequests->first();

        // Produce card
        $this->post(route('id.produce', $idRequest), [
            'qrCode' => 'QR-'.uniqid(),
            'securityPhotoPath' => null,
        ])->assertSessionHasNoErrors();

        $studentId = $idRequest->fresh()->studentids;
        $this->assertNotNull($studentId);

        // Validate ID
        $response = $this->post(route('id.validate', $studentId));

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'ID validated successfully.');

        // Student ID should be Active with validatedBy/validatedDate
        $studentId->refresh();
        $this->assertEquals(IdValidationStatus::Active, $studentId->validationStatus);
        $this->assertEquals($idStaff->userId, $studentId->validatedBy);
        $this->assertNotNull($studentId->validatedDate);

        // Workflow step for ID Office (office 22) should be signed
        $workflow = $enrollment->fresh()->enrollmentworkflow;
        $idStep = $workflow->workflowsteps()->where('officeId', 22)->first();
        $this->assertNotNull($idStep);
        $this->assertEquals(WorkflowStepStatus::Completed, $idStep->stepStatus);
        $this->assertEquals($idStaff->userId, $idStep->signedBy);
        $this->assertNotNull($idStep->signedDate);

        // Workflow status should be Completed (this is the FINAL step)
        $workflow->refresh();
        $this->assertEquals(WorkflowStatus::Completed, $workflow->workflowStatus);
    }

    #[Test]
    public function test_validate_fails_when_clinic_step_still_pending(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        // Create enrollment where Clinic (office 11) is still pending
        $enrollment = $this->createEnrollment();
        $enrollment->enrollmentworkflow->workflowsteps()->delete();
        $enrollment->enrollmentworkflow->delete();
        $this->createWorkflowAtClinicStep($enrollment);

        // Create ID request (policy allows create only when ID step is current,
        // but we bypass by creating directly for this test)
        $idRequest = Idrequests::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
            'requestDate' => now(),
            'status' => IdRequestStatus::Pending,
        ]);

        // Produce card
        $studentId = Studentids::create([
            'studentId' => $enrollment->studentId,
            'idRequestId' => $idRequest->idRequestId,
            'qrCode' => 'QR-'.uniqid(),
            'issueDate' => now(),
            'validationStatus' => IdValidationStatus::PendingValidation,
        ]);

        // Try to validate — WorkflowService::signStepByOffice should throw
        // InvalidStateTransitionException because Clinic step (office 11) is not completed
        $response = $this->post(route('id.validate', $studentId));

        // The exception is not caught in the controller, so it bubbles up as 500.
        // We assert the actual behavior: 500 error response.
        $response->assertStatus(500);

        // Verify the step was NOT signed (remains pending)
        $workflow = $enrollment->fresh()->enrollmentworkflow;
        $idStep = $workflow->workflowsteps()->where('officeId', 22)->first();
        $this->assertEquals(WorkflowStepStatus::Pending, $idStep->stepStatus);
        $this->assertNull($idStep->signedBy);
    }

    #[Test]
    public function test_unauthorized_staff_cannot_create_returns_403(): void
    {
        // Staff from Clinic (office 11), not ID Office (22)
        $unauthorizedStaff = $this->staffForOffice(11);
        $this->actingAs($unauthorizedStaff);

        $enrollment = $this->createEnrollment();

        $response = $this->post(route('id.create', $enrollment), [
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function test_unauthorized_staff_cannot_produce_card_returns_403(): void
    {
        $idStaff = $this->staffForOffice(22);
        $unauthorizedStaff = $this->staffForOffice(11);

        $enrollment = $this->createEnrollment();

        // Create ID request as authorized staff
        $this->actingAs($idStaff);
        $this->post(route('id.create', $enrollment), [
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
        ])->assertSessionHasNoErrors();

        $idRequest = $enrollment->fresh()->idrequests->first();

        // Try to produce card as unauthorized staff
        $this->actingAs($unauthorizedStaff);
        $response = $this->post(route('id.produce', $idRequest), [
            'qrCode' => 'QR-'.uniqid(),
            'securityPhotoPath' => null,
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function test_unauthorized_staff_cannot_validate_returns_403(): void
    {
        $idStaff = $this->staffForOffice(22);
        $unauthorizedStaff = $this->staffForOffice(11);

        $enrollment = $this->createEnrollment();

        // Create ID request and produce card as authorized staff
        $this->actingAs($idStaff);
        $this->post(route('id.create', $enrollment), [
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
        ])->assertSessionHasNoErrors();

        $idRequest = $enrollment->fresh()->idrequests->first();
        $this->post(route('id.produce', $idRequest), [
            'qrCode' => 'QR-'.uniqid(),
            'securityPhotoPath' => null,
        ])->assertSessionHasNoErrors();

        $studentId = $idRequest->fresh()->studentids;

        // Try to validate as unauthorized staff
        $this->actingAs($unauthorizedStaff);
        $response = $this->post(route('id.validate', $studentId));

        $response->assertForbidden();
    }

    #[Test]
    public function test_release_sets_validation_status_active(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        // Create ID request
        $this->post(route('id.create', $enrollment), [
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
        ])->assertSessionHasNoErrors();

        $idRequest = $enrollment->fresh()->idrequests->first();

        // Produce card
        $this->post(route('id.produce', $idRequest), [
            'qrCode' => 'QR-'.uniqid(),
            'securityPhotoPath' => null,
        ])->assertSessionHasNoErrors();

        $studentId = $idRequest->fresh()->studentids;

        // Validate first (sets to Active)
        $this->post(route('id.validate', $studentId))->assertSessionHasNoErrors();

        // Release (should keep Active)
        $response = $this->post(route('id.release', $studentId));

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'ID released to student.');

        $studentId->refresh();
        $this->assertEquals(IdValidationStatus::Active, $studentId->validationStatus);
    }
}
