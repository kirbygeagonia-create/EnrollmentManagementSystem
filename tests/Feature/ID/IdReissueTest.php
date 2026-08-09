<?php

namespace Tests\Feature\ID;

use App\Enums\EnrollmentStatus;
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
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdReissueTest extends TestCase
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
     * Helper to create a full ID request flow up to a specific status.
     */
    private function createIdRequestFlow(Enrollments $enrollment, IdRequestStatus $targetStatus): Idrequests
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        // Create ID request
        $this->post(route('id.create', $enrollment), [
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
        ])->assertSessionHasNoErrors();

        $idRequest = $enrollment->fresh()->idrequests->first();

        if ($targetStatus === IdRequestStatus::Pending) {
            return $idRequest;
        }

        // Produce card
        $this->post(route('id.produce', $idRequest), [
            'qrCode' => 'QR-'.uniqid(),
            'securityPhotoPath' => null,
        ])->assertSessionHasNoErrors();

        $idRequest->refresh();

        if ($targetStatus === IdRequestStatus::CardProduced) {
            return $idRequest;
        }

        // Validate - need to ensure Clinic workflow step is completed
        // The workflow was created with Clinic step completed, so this should work
        $studentId = $idRequest->fresh()->studentids;

        // Debug: check workflow state before validate
        $workflow = $studentId->idRequest?->enrollment?->enrollmentworkflow;
        $this->assertNotNull($workflow, 'Workflow not found');
        $this->assertNotNull($workflow->workflowsteps()->where('officeId', 22)->first(), 'ID Office step not found');
        $clinicStep = $workflow->workflowsteps()->where('officeId', 11)->first();
        $this->assertNotNull($clinicStep, 'Clinic step not found');
        $this->assertEquals('completed', $clinicStep->stepStatus->value, 'Clinic step not completed: '.$clinicStep->stepStatus->value);

        $response = $this->post(route('id.validate', $studentId));
        $this->assertEquals(302, $response->getStatusCode(), 'Validate failed: '.$response->getContent());
        $response->assertSessionHasNoErrors();

        $idRequest->refresh();

        if ($targetStatus === IdRequestStatus::Validated) {
            return $idRequest;
        }

        // Release
        $response = $this->post(route('id.release', $studentId));
        $this->assertEquals(302, $response->getStatusCode(), 'Release failed: '.$response->getContent());
        $response->assertSessionHasNoErrors();

        $idRequest->refresh();

        return $idRequest;
    }

    #[Test]
    public function test_reissue_released_card_sets_reissue_pending_and_stores_reason(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        // Create full flow up to Released
        $idRequest = $this->createIdRequestFlow($enrollment, IdRequestStatus::Released);
        $this->assertEquals(IdRequestStatus::Released, $idRequest->status);

        // Reissue (replacement)
        $response = $this->post(route('id.reissue', $idRequest), [
            'reissueReason' => 'Lost ID card',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'ID replacement requested. Status set to Reissue Pending.');

        $idRequest->refresh();
        $this->assertTrue($idRequest->is_reissue);
        $this->assertEquals('Lost ID card', $idRequest->reissueReason);
        $this->assertEquals(IdRequestStatus::ReissuePending, $idRequest->status);
    }

    #[Test]
    public function test_reissue_card_produced_keeps_card_produced_and_marks_reissue(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        // Create flow up to CardProduced
        $idRequest = $this->createIdRequestFlow($enrollment, IdRequestStatus::CardProduced);
        $this->assertEquals(IdRequestStatus::CardProduced, $idRequest->status);

        // Reissue (reprint)
        $response = $this->post(route('id.reissue', $idRequest), [
            'reissueReason' => 'Damaged during printing',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'ID card marked for reprint.');

        $idRequest->refresh();
        $this->assertTrue($idRequest->is_reissue);
        $this->assertEquals('Damaged during printing', $idRequest->reissueReason);
        $this->assertEquals(IdRequestStatus::CardProduced, $idRequest->status); // Stays CardProduced for reprint
    }

    #[Test]
    public function test_reissue_denied_for_non_id_office_staff(): void
    {
        $idStaff = $this->staffForOffice(22);
        $unauthorizedStaff = $this->staffForOffice(11); // Clinic office

        $enrollment = $this->createEnrollment();

        // Create flow up to Released as authorized staff
        $this->actingAs($idStaff);
        $idRequest = $this->createIdRequestFlow($enrollment, IdRequestStatus::Released);

        // Try to reissue as unauthorized staff
        $this->actingAs($unauthorizedStaff);
        $response = $this->post(route('id.reissue', $idRequest), [
            'reissueReason' => 'Test',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function test_reissue_denied_for_invalid_status_pending(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        // Create ID request only (status = Pending)
        $idRequest = $this->createIdRequestFlow($enrollment, IdRequestStatus::Pending);
        $this->assertEquals(IdRequestStatus::Pending, $idRequest->status);

        // Try to reissue from Pending - should be denied
        $response = $this->post(route('id.reissue', $idRequest), [
            'reissueReason' => 'Test',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function test_reissue_denied_for_invalid_status_validated(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        // Create flow up to Validated
        $idRequest = $this->createIdRequestFlow($enrollment, IdRequestStatus::Validated);
        $this->assertEquals(IdRequestStatus::Validated, $idRequest->status);

        // Try to reissue from Validated - should be denied
        $response = $this->post(route('id.reissue', $idRequest), [
            'reissueReason' => 'Test',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function test_reissue_denied_for_invalid_status_reissue_pending(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        // Create flow up to Released, then reissue once to get ReissuePending
        $idRequest = $this->createIdRequestFlow($enrollment, IdRequestStatus::Released);
        $this->post(route('id.reissue', $idRequest), [
            'reissueReason' => 'First reissue',
        ])->assertSessionHasNoErrors();

        $idRequest->refresh();
        $this->assertEquals(IdRequestStatus::ReissuePending, $idRequest->status);

        // Try to reissue again from ReissuePending - should be denied
        $response = $this->post(route('id.reissue', $idRequest), [
            'reissueReason' => 'Second reissue',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function test_reissue_denied_for_invalid_status_cancelled(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        // Create flow up to CardProduced, then cancel
        $idRequest = $this->createIdRequestFlow($enrollment, IdRequestStatus::CardProduced);
        $this->post(route('id.cancel', $idRequest))->assertSessionHasNoErrors();

        $idRequest->refresh();
        $this->assertEquals(IdRequestStatus::Cancelled, $idRequest->status);

        // Try to reissue from Cancelled - should be denied
        $response = $this->post(route('id.reissue', $idRequest), [
            'reissueReason' => 'Test',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function test_reissue_validation_requires_reissue_reason(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();
        $idRequest = $this->createIdRequestFlow($enrollment, IdRequestStatus::Released);

        // Try to reissue without reason
        $response = $this->post(route('id.reissue', $idRequest), [
            'reissueReason' => '',
        ]);

        $response->assertSessionHasErrors('reissueReason');
    }

    #[Test]
    public function test_reissue_validation_max_255_chars(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();
        $idRequest = $this->createIdRequestFlow($enrollment, IdRequestStatus::Released);

        // Try to reissue with reason > 255 chars
        $longReason = str_repeat('a', 256);
        $response = $this->post(route('id.reissue', $idRequest), [
            'reissueReason' => $longReason,
        ]);

        $response->assertSessionHasErrors('reissueReason');
    }

    #[Test]
    public function test_cancel_pending_request_sets_cancelled(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        // Create ID request (status = Pending)
        $idRequest = $this->createIdRequestFlow($enrollment, IdRequestStatus::Pending);
        $this->assertEquals(IdRequestStatus::Pending, $idRequest->status);

        // Cancel
        $response = $this->post(route('id.cancel', $idRequest));

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'ID request cancelled.');

        $idRequest->refresh();
        $this->assertEquals(IdRequestStatus::Cancelled, $idRequest->status);
    }

    #[Test]
    public function test_cancel_card_produced_request_sets_cancelled(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        // Create flow up to CardProduced
        $idRequest = $this->createIdRequestFlow($enrollment, IdRequestStatus::CardProduced);
        $this->assertEquals(IdRequestStatus::CardProduced, $idRequest->status);

        // Cancel
        $response = $this->post(route('id.cancel', $idRequest));

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'ID request cancelled.');

        $idRequest->refresh();
        $this->assertEquals(IdRequestStatus::Cancelled, $idRequest->status);
    }

    #[Test]
    public function test_cancel_denied_for_non_id_office_staff(): void
    {
        $idStaff = $this->staffForOffice(22);
        $unauthorizedStaff = $this->staffForOffice(11);

        $enrollment = $this->createEnrollment();

        // Create ID request as authorized staff
        $this->actingAs($idStaff);
        $idRequest = $this->createIdRequestFlow($enrollment, IdRequestStatus::Pending);

        // Try to cancel as unauthorized staff
        $this->actingAs($unauthorizedStaff);
        $response = $this->post(route('id.cancel', $idRequest));

        $response->assertForbidden();
    }

    #[Test]
    public function test_cancel_denied_for_invalid_status_released(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        // Create flow up to Released
        $idRequest = $this->createIdRequestFlow($enrollment, IdRequestStatus::Released);
        $this->assertEquals(IdRequestStatus::Released, $idRequest->status);

        // Try to cancel from Released - should be denied
        $response = $this->post(route('id.cancel', $idRequest));

        $response->assertForbidden();
    }

    #[Test]
    public function test_cancel_denied_for_invalid_status_validated(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        // Create flow up to Validated
        $idRequest = $this->createIdRequestFlow($enrollment, IdRequestStatus::Validated);
        $this->assertEquals(IdRequestStatus::Validated, $idRequest->status);

        // Try to cancel from Validated - should be denied
        $response = $this->post(route('id.cancel', $idRequest));

        $response->assertForbidden();
    }

    #[Test]
    public function test_cancel_denied_for_invalid_status_reissue_pending(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        // Create flow up to Released, then reissue
        $idRequest = $this->createIdRequestFlow($enrollment, IdRequestStatus::Released);
        $this->post(route('id.reissue', $idRequest), [
            'reissueReason' => 'Test',
        ])->assertSessionHasNoErrors();

        $idRequest->refresh();
        $this->assertEquals(IdRequestStatus::ReissuePending, $idRequest->status);

        // Try to cancel from ReissuePending - should be denied
        $response = $this->post(route('id.cancel', $idRequest));

        $response->assertForbidden();
    }

    #[Test]
    public function test_cancel_denied_for_invalid_status_cancelled(): void
    {
        $idStaff = $this->staffForOffice(22);
        $this->actingAs($idStaff);

        $enrollment = $this->createEnrollment();

        // Create flow up to CardProduced, then cancel
        $idRequest = $this->createIdRequestFlow($enrollment, IdRequestStatus::CardProduced);
        $this->post(route('id.cancel', $idRequest))->assertSessionHasNoErrors();

        $idRequest->refresh();
        $this->assertEquals(IdRequestStatus::Cancelled, $idRequest->status);

        // Try to cancel again from Cancelled - should be denied
        $response = $this->post(route('id.cancel', $idRequest));

        $response->assertForbidden();
    }
}
