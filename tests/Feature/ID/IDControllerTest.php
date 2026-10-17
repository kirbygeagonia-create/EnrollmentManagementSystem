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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
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

        // These tests post a real image to the attach-photo route. Without the fake every
        // run left another face photo behind in storage/app/private/id-photos — 131
        // unreferenced files by 2026-10-10 — in the same folder the school's real
        // student pictures live in.
        Storage::fake('documents');
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

        // As RbacSeeder pairs them: the ID office's head is IdOfficer + OfficeHead. Validation
        // is the ID box's signature, so it now needs id.sign as well as id.validate — ruling 7
        // moved that signature out of OfficeHead, and headOnlyInOffice() is the account left
        // holding only the counter right.
        $staff->assignRole('OfficeHead', 'IdOfficer');

        return $staff;
    }

    /**
     * A bare OfficeHead of the given office: the counter rights, no desk signature.
     */
    private function headOnlyInOffice(int $officeId): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => $officeId,
            'role' => 'officeHead',
            'employeeNo' => 'EMP-TEST-'.uniqid(),
            'username' => 'test_head'.$officeId.'_'.uniqid(),
            'email' => 'test_head'.$officeId.'_'.uniqid().'@example.com',
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
            // Ruled 2026-10-10: the create route no longer reads a photo path. A request
            // carries a face only after attachPhoto() stored one and checked the write.
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
        // The field was sent and must have been ignored: a request created at the counter
        // has no photo, however much text the post carried.
        $this->assertNull($idRequest->cardPhotoPath);
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
    public function test_retaking_the_photo_removes_the_picture_it_replaced(): void
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

        $this->post(route('id.photo', $idRequest), ['photo' => UploadedFile::fake()->image('first.jpg')])
            ->assertSessionHasNoErrors();

        $first = $idRequest->fresh()->cardPhotoPath;

        $this->post(route('id.photo', $idRequest), ['photo' => UploadedFile::fake()->image('second.jpg')])
            ->assertSessionHasNoErrors();

        $second = $idRequest->fresh()->cardPhotoPath;

        // Ruled 2026-10-10: the retake replaces the record's picture, and the blurred
        // attempt it displaced is removed rather than left in the folder of student photos.
        $this->assertNotEquals($first, $second);
        Storage::disk('documents')->assertExists($second);
        Storage::disk('documents')->assertMissing($first);
    }

    #[Test]
    public function test_a_rejected_retake_leaves_the_photo_on_file_in_place(): void
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

        $this->post(route('id.photo', $idRequest), ['photo' => UploadedFile::fake()->image('good.jpg')])
            ->assertSessionHasNoErrors();

        $kept = $idRequest->fresh()->cardPhotoPath;

        $this->post(route('id.photo', $idRequest), ['photo' => UploadedFile::fake()->create('scan.pdf', 50, 'application/pdf')])
            ->assertSessionHasErrors('photo');

        // The removal happens after a stored write and a successful update, so a failed
        // attempt cannot destroy the one picture the request actually has.
        $this->assertEquals($kept, $idRequest->fresh()->cardPhotoPath);
        Storage::disk('documents')->assertExists($kept);
    }

    #[Test]
    public function validating_a_request_needs_the_id_signature_as_well_as_the_counter_right(): void
    {
        // id.sign was held by IdOfficer and consulted by nothing. The head of the ID office
        // keeps id.validate and id.request.create (ruling 7 left the counter with the head)
        // but not the signature, so validation must be refused and the request must stay
        // pending with its box unsigned.
        $head = $this->headOnlyInOffice(22);
        $this->assertTrue($head->can('id.validate'));
        $this->assertFalse($head->can('id.sign'));

        $this->actingAs($head);

        $enrollment = $this->createEnrollment();

        $this->post(route('id.create', $enrollment), [
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
        ])->assertSessionHasNoErrors();

        $idRequest = $enrollment->fresh()->idrequests->first();
        $idRequest->update(['cardPhotoPath' => 'id-photos/test-capture.jpg']);

        $this->post(route('id.validate', $idRequest))->assertForbidden();

        $idRequest->refresh();
        $this->assertEquals(IdRequestStatus::Pending, $idRequest->status);
        $this->assertNull($idRequest->validatedBy);

        $idStep = $enrollment->fresh()->enrollmentworkflow->workflowsteps()->where('officeId', 22)->first();
        $this->assertEquals(WorkflowStepStatus::Pending, $idStep->stepStatus, 'the box stays unsigned');
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

    /**
     * Every box already signed, including the ID office's — the state an enrollment
     * reaches after the desk has finished with it. The request row is written directly
     * because id.create refuses an enrollment that has left the ID stage.
     */
    private function createFullySignedWorkflow(Enrollments $enrollment, Staffusers $notThisOfficer): Enrollmentworkflow
    {
        // createEnrollment() already opened a workflow parked at the ID box, and the
        // desk reads its box through a hasOne — leaving both rows means the desk signs
        // the other one and the test proves nothing. Retire it first, as the existing
        // wrong-stage test does.
        $enrollment->enrollmentworkflow?->workflowsteps()->delete();
        $enrollment->enrollmentworkflow()->delete();

        $workflow = Enrollmentworkflow::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'currentStep' => 7,
            'workflowStatus' => WorkflowStatus::Completed,
        ]);

        // A signer that is provably not the officer about to press the button, so an
        // overwrite of the box would show up as a changed signedBy rather than a no-op.
        $previous = Staffusers::factory()->create(['officeId' => 22]);
        $this->assertNotSame($previous->userId, $notThisOfficer->userId);

        foreach ([[4, 1], [3, 2], [2, 3], [1, 4], [5, 5], [11, 6], [22, 7]] as [$officeId, $order]) {
            Workflowsteps::create([
                'workflowId' => $workflow->workflowId,
                'officeId' => $officeId,
                'stepOrder' => $order,
                'stepStatus' => WorkflowStepStatus::Completed,
                'signedBy' => $previous->userId,
                'signedDate' => now()->subWeek(),
            ]);
        }

        return $workflow;
    }

    #[Test]
    public function validating_a_request_whose_box_is_already_signed_refuses_and_keeps_the_first_signature(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();
        $workflow = $this->createFullySignedWorkflow($enrollment, $idStaff);
        $idBox = $workflow->workflowsteps()->where('officeId', 22)->first();
        $originalSigner = $idBox->signedBy;
        $originalDate = $idBox->signedDate;

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

        $this->assertSame(1, Enrollmentworkflow::where('enrollmentId', $enrollment->enrollmentId)->count(), 'the desk must see exactly one workflow');

        $response = $this->from(route('id.show', $idRequest))
            ->post(route('id.validate', $idRequest));

        // The request did not move, because the signature write rolled the transaction back.
        $this->assertEquals(IdRequestStatus::Pending, $idRequest->fresh()->status);
        $this->assertNull($idRequest->fresh()->validatedBy);

        // The box still carries the first officer's signature and date, not this attempt's.
        $freshBox = $idBox->fresh();
        $this->assertEquals($originalSigner, $freshBox->signedBy);
        $this->assertEquals($originalDate->toDateTimeString(), $freshBox->signedDate->toDateTimeString());

        // The desk is told which box refused it and who already signed it — not that the
        // save failed for an unnamed reason, and not a raw 500.
        $response->assertRedirect(route('id.show', $idRequest));
        $error = session('error');
        $this->assertIsString($error);
        $this->assertStringContainsString('ID Validation', $error);
        $this->assertStringContainsString('Nothing on the record changed', $error);
        $this->assertStringContainsString('corrected by the Registrar with a system administrator', $error);
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
    public function test_card_release_action_is_gone_and_validation_is_terminal(): void
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
        $idRequest->update(['cardPhotoPath' => 'id-photos/test-capture.jpg']);
        $this->post(route('id.validate', $idRequest))->assertSessionHasNoErrors();

        // Card production and handover were removed from the system: the
        // release route, its permission and the Released status are all gone,
        // and nothing can re-open a validated request.
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('id.release'));
        $this->assertNull(DB::table('permissions')->where('name', 'id.release')->first());
        $this->assertNull(IdRequestStatus::tryFrom('released'));

        // Ruling 13 takes 'cancelled' with it — no action in app/ writes it since
        // card-making went, and live `ems` held no row carrying it.
        $this->assertNull(IdRequestStatus::tryFrom('cancelled'));
        $this->assertSame(['pending', 'validated'], array_column(IdRequestStatus::cases(), 'value'));

        $this->post(route('id.photo', $idRequest), [
            'photo' => UploadedFile::fake()->image('retake.jpg'),
        ])->assertForbidden();

        $this->assertEquals(IdRequestStatus::Validated, $idRequest->fresh()->status);
    }

    #[Test]
    public function a_retired_id_card_state_cannot_be_written_to_the_request(): void
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

        // The vocabulary is retired in storage, not only in PHP: the column narrowed on
        // MySQL and its check constraint on SQLite, so a request in a state no desk can
        // act on cannot be filed by anything that bypasses the policy.
        $this->expectException(QueryException::class);

        DB::table('idrequests')->where('idRequestId', $idRequest->idRequestId)->update(['status' => 'cancelled']);
    }

    #[Test]
    public function test_record_remark_stores_what_did_not_match_on_an_open_request(): void
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

        $this->post(route('id.remark', $idRequest), [
            'mismatchRemark' => 'Photo does not match the student at the window.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            'Photo does not match the student at the window.',
            $idRequest->fresh()->mismatchRemark
        );
        $this->assertEquals(IdRequestStatus::Pending, $idRequest->fresh()->status, 'A remark must not decide anything.');
    }

    #[Test]
    public function test_record_remark_refuses_an_empty_reason(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();
        $this->post(route('id.create', $enrollment), [
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
        ]);
        $idRequest = $enrollment->fresh()->idrequests->first();

        $this->post(route('id.remark', $idRequest), ['mismatchRemark' => ''])
            ->assertSessionHasErrors('mismatchRemark');

        $this->assertNull($idRequest->fresh()->mismatchRemark);
    }

    #[Test]
    public function test_a_closed_request_cannot_be_annotated_afterwards(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();
        $this->post(route('id.create', $enrollment), [
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
        ]);
        $idRequest = $enrollment->fresh()->idrequests->first();
        $idRequest->update(['status' => IdRequestStatus::Validated]);

        $this->post(route('id.remark', $idRequest), ['mismatchRemark' => 'Wrong face.'])
            ->assertForbidden();

        $this->assertNull($idRequest->fresh()->mismatchRemark, 'A note on a signed request would read as though it was seen before validation.');
    }

    #[Test]
    public function test_only_the_id_office_can_record_a_mismatch(): void
    {
        $otherOffice = $this->staffForOffice(1);
        $this->actingAs($otherOffice);

        $enrollment = $this->createEnrollment();
        $idRequest = Idrequests::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
            'requestDate' => now(),
            'status' => IdRequestStatus::Pending,
        ]);

        $this->post(route('id.remark', $idRequest), ['mismatchRemark' => 'Wrong face.'])
            ->assertForbidden();
    }

    /**
     * Inertia serves HTML until X-Inertia is sent, so props come from viewData('page'), not json().
     *
     * @return array<string, mixed>
     */
    private function props(TestResponse $response): array
    {
        return $response->viewData('page')['props'];
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    private function ids(array $rows): array
    {
        return array_column($rows, 'enrollmentId');
    }

    #[Test]
    public function a_validated_request_stays_reachable_on_the_issued_tab_once_it_leaves_the_queue(): void
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
        $idRequest->update(['cardPhotoPath' => 'id-photos/test-capture.jpg']);
        $this->post(route('id.validate', $idRequest))->assertSessionHasNoErrors();

        $queue = $this->props($this->get(route('id.index')));
        $this->assertNotContains(
            $enrollment->enrollmentId,
            $this->ids($queue['enrollments']['data']),
            'signing the box retires the row from the queue — that part is the design, and the old bug lived here'
        );

        $issued = $this->props($this->get(route('id.index', ['tab' => 'issued'])));
        $this->assertSame('issued', $issued['tab']);
        $this->assertContains(
            $enrollment->enrollmentId,
            $this->ids($issued['enrollments']['data']),
            'the desk must be able to reopen a request it validated without typing /id/N from memory'
        );
        $this->assertGreaterThanOrEqual(1, $issued['stats']['validated']);
        $this->assertGreaterThanOrEqual(1, $issued['tabs']['issued']['count']);

        // The tab's row is only a fix if the link it renders actually opens.
        $this->get(route('id.show', $enrollment))->assertOk();
    }

    #[Test]
    public function a_signed_box_with_no_request_behind_it_is_listed_on_the_issued_tab_rather_than_hidden(): void
    {
        $officer = $this->staffForOffice(22);
        $this->actingAs($officer);

        $enrollment = $this->createEnrollment();
        $this->createFullySignedWorkflow($enrollment, $officer);

        $issued = $this->props($this->get(route('id.index', ['tab' => 'issued'])));
        $rows = $issued['enrollments']['data'];
        $this->assertContains($enrollment->enrollmentId, $this->ids($rows));

        $row = collect($rows)->firstWhere('enrollmentId', $enrollment->enrollmentId);
        $this->assertEmpty($row['idrequests'] ?? [], 'a box signed with nothing on file is shown as such, not omitted');
        $this->assertSame(
            0,
            $issued['stats']['validated'],
            'a signed box with no request row is not a validated request and must not be counted as one'
        );
    }

    #[Test]
    public function the_two_tabs_stay_disjoint_an_open_box_is_queue_only_and_a_signed_one_issued_only(): void
    {
        $officer = $this->staffForOffice(22);
        $this->actingAs($officer);

        $open = $this->createEnrollment();
        $signed = $this->createEnrollment();
        $this->createFullySignedWorkflow($signed, $officer);

        $queueRows = $this->ids($this->props($this->get(route('id.index')))['enrollments']['data']);
        $issuedRows = $this->ids($this->props($this->get(route('id.index', ['tab' => 'issued'])))['enrollments']['data']);

        $this->assertContains($open->enrollmentId, $queueRows);
        $this->assertNotContains($open->enrollmentId, $issuedRows, 'the issued tab is finished work, not the queue again');
        $this->assertContains($signed->enrollmentId, $issuedRows);
        $this->assertNotContains($signed->enrollmentId, $queueRows, 'a signed box must not still read as a task');
    }

    #[Test]
    public function the_desk_screen_sends_the_officer_back_to_the_tab_the_record_was_opened_from(): void
    {
        $officer = $this->staffForOffice(22);
        $this->actingAs($officer);

        $enrollment = $this->createEnrollment();
        $this->createFullySignedWorkflow($enrollment, $officer);

        // Opening a record straight from its id is the queue's business, and the screen must say so.
        $this->assertSame(
            'queue',
            $this->props($this->get(route('id.show', $enrollment)))['backTab'],
            'the default is the pending list, which is what the desk was before the second tab existed'
        );

        // The Issued tab's own links carry the query; the screen has to send the officer back there, because
        // dropping them on the pending list after reading a finished record loses the list they were working.
        $this->assertSame(
            'issued',
            $this->props($this->get(route('id.show', ['enrollment' => $enrollment, 'tab' => 'issued'])))['backTab'],
            'a record opened from the Issued tab must return to the Issued tab'
        );
    }
}
