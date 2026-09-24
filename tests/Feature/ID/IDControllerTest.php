<?php

namespace Tests\Feature\ID;

use App\Enums\EnrollmentStatus;
use App\Enums\IdRequestReason;
use App\Enums\IdRequestStatus;
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
use App\Models\Students;
use App\Models\Workflowsteps;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
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
    public function test_show_renders_id_form_with_null_idrequest_initially(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        $response = $this->get(route('id.show', $enrollment));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('ID/Show')
            ->has('enrollment')
            ->where('idRequest', null)
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
        ])->assertSessionHasNoErrors();

        // Show should now contain the idRequest
        $response = $this->get(route('id.show', $enrollment));
        $response->assertInertia(fn ($page) => $page->where('idRequest.requestReason', 'newStudent')
            ->where('idRequest.status', 'pending')
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
    public function test_attach_photo_stores_file_and_keeps_request_pending(): void
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

        $response = $this->post(route('id.photo', $idRequest), [
            'photo' => UploadedFile::fake()->image('capture.jpg'),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'Face photo attached to the ID request.');

        $idRequest->refresh();
        $this->assertNotNull($idRequest->cardPhotoPath);
        $this->assertEquals(IdRequestStatus::Pending, $idRequest->status);
    }

    #[Test]
    public function test_attach_photo_rejects_non_image_file(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        $this->post(route('id.create', $enrollment), [
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
        ])->assertSessionHasNoErrors();

        $idRequest = $enrollment->fresh()->idrequests->first();

        $response = $this->post(route('id.photo', $idRequest), [
            'photo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('photo');
    }

    #[Test]
    public function test_validate_sets_request_validated_and_signs_workflow_step(): void
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

        // Attach the face photo (validation prerequisite)
        $idRequest->update(['cardPhotoPath' => 'id-photos/test-capture.jpg']);

        // Validate ID
        $response = $this->post(route('id.validate', $idRequest));

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'ID validated successfully.');

        // Request should be Validated with validatedBy/validatedDate
        $idRequest->refresh();
        $this->assertEquals(IdRequestStatus::Validated, $idRequest->status);
        $this->assertEquals($idStaff->userId, $idRequest->validatedBy);
        $this->assertNotNull($idRequest->validatedDate);

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
    public function test_validate_fails_without_attached_photo(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        // Create ID request WITHOUT a photo
        $this->post(route('id.create', $enrollment), [
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
        ])->assertSessionHasNoErrors();

        $idRequest = $enrollment->fresh()->idrequests->first();

        // Validate — IDPolicy::validate requires the face photo, so the
        // authorization fails with 403 (not a silent pass).
        $response = $this->post(route('id.validate', $idRequest));

        $response->assertForbidden();

        // Verify the step was NOT signed and the request stays pending
        $idRequest->refresh();
        $this->assertEquals(IdRequestStatus::Pending, $idRequest->status);
        $workflow = $enrollment->fresh()->enrollmentworkflow;
        $idStep = $workflow->workflowsteps()->where('officeId', 22)->first();
        $this->assertEquals(WorkflowStepStatus::Pending, $idStep->stepStatus);
        $this->assertNull($idStep->signedBy);
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

        // Create ID request directly (policy allows create only when ID step
        // is current, but we bypass by creating directly for this test)
        $idRequest = Idrequests::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
            'cardPhotoPath' => 'id-photos/test-capture.jpg',
            'requestDate' => now(),
            'status' => IdRequestStatus::Pending,
        ]);

        // Try to validate — WorkflowService::signStepByOffice throws
        // InvalidStateTransitionException because Clinic step (office 11) is not
        // completed. The global exception renderer converts it to a friendly
        // redirect-back with a flash error (not a raw 500).
        $response = $this->from(route('id.index'))
            ->post(route('id.validate', $idRequest));

        $response->assertRedirect(route('id.index'));
        $response->assertSessionHas('error');

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
    public function test_unauthorized_staff_cannot_attach_photo_returns_403(): void
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

        // Try to attach photo as unauthorized staff (office 11, not ID Office 22)
        $this->actingAs($unauthorizedStaff);
        $response = $this->post(route('id.photo', $idRequest), [
            'photo' => UploadedFile::fake()->image('capture.jpg'),
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function test_unauthorized_staff_cannot_validate_returns_403(): void
    {
        $idStaff = $this->staffForOffice(22);
        $unauthorizedStaff = $this->staffForOffice(11);

        $enrollment = $this->createEnrollment();

        // Create ID request and attach photo as authorized staff
        $this->actingAs($idStaff);
        $this->post(route('id.create', $enrollment), [
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
        ])->assertSessionHasNoErrors();

        $idRequest = $enrollment->fresh()->idrequests->first();
        $idRequest->update(['cardPhotoPath' => 'id-photos/test-capture.jpg']);

        // Try to validate as unauthorized staff
        $this->actingAs($unauthorizedStaff);
        $response = $this->post(route('id.validate', $idRequest));

        $response->assertForbidden();
    }

    #[Test]
    public function test_release_sets_request_released(): void
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

        // Attach photo, then validate (sets to Validated)
        $idRequest->update(['cardPhotoPath' => 'id-photos/test-capture.jpg']);
        $this->post(route('id.validate', $idRequest))->assertSessionHasNoErrors();

        // Release (moves to Released)
        $response = $this->post(route('id.release', $idRequest));

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'ID released to student.');

        $idRequest->refresh();
        $this->assertEquals(IdRequestStatus::Released, $idRequest->status);
    }
}
