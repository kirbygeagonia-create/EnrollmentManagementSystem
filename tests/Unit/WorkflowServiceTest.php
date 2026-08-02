<?php

namespace Tests\Unit;

use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Workflowsteps;
use App\Models\Staffusers;
use App\Models\Students;
use App\Models\Courses;
use App\Models\Academicterms;
use App\Models\Admissions;
use App\Models\Offices;
use App\Services\WorkflowService;
use App\Enums\EnrollmentStatus;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowStepStatus;
use App\Exceptions\InvalidStateTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowService $workflowService;
    private Enrollments $enrollment;
    private Staffusers $registrarStaff;
    private Staffusers $clinicStaff;
    private Staffusers $idStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflowService = new WorkflowService();

        // Create offices
        Offices::create(['officeId' => 1, 'officeName' => 'Registrar']);
        Offices::create(['officeId' => 2, 'officeName' => 'Accounting']);
        Offices::create(['officeId' => 3, 'officeName' => 'Scholarship']);
        Offices::create(['officeId' => 4, 'officeName' => 'Guidance']);
        Offices::create(['officeId' => 5, 'officeName' => 'Academic Department']);
        Offices::create(['officeId' => 11, 'officeName' => 'Clinic']);
        Offices::create(['officeId' => 22, 'officeName' => 'ID Office']);

        // Create staff
        $this->registrarStaff = Staffusers::factory()->create(['officeId' => 1]);
        $this->clinicStaff = Staffusers::factory()->create(['officeId' => 11]);
        $this->idStaff = Staffusers::factory()->create(['officeId' => 22]);

        // Create enrollment
        $student = Students::create([
            'schoolIdNumber' => '2026-0002',
            'lastName' => 'Test',
            'firstName' => 'Student',
            'gender' => 'male',
            'birthdate' => '2000-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'religionId' => 1,
            'civilStatus' => 'single',
            'contactNumber' => '09123456789',
            'email' => 'test2@example.com',
            'username' => 'teststudent2',
            'passwordHash' => bcrypt('password'),
            'status' => 'active',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
        ]);

        $course = Courses::create([
            'unitId' => 1,
            'courseName' => 'BS Computer Science',
            'courseCode' => 'BSCS',
            'requiresEntranceExam' => true,
            'requiresRetentionExam' => true,
        ]);

        $term = Academicterms::create([
            'academicYearId' => 1,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);

        $admission = Admissions::create([
            'studentId' => $student->studentId,
            'courseId' => $course->courseId,
            'termId' => $term->termId,
            'applicantType' => 'firstYear',
            'admissionStatus' => 'approved',
        ]);

        $this->enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $course->courseId,
            'termId' => $term->termId,
            'admissionId' => $admission->admissionId,
            'yearLevel' => 1,
            'studentType' => 'firstYear',
            'enrollmentType' => 'new',
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Pending,
        ]);
    }

    /** @test */
    public function it_creates_workflow_with_8_steps(): void
    {
        $workflow = $this->workflowService->createWorkflow($this->enrollment);

        $this->assertNotNull($workflow);
        $this->assertEquals(1, $workflow->currentStep);
        $this->assertEquals(WorkflowStatus::InProgress, $workflow->workflowStatus);

        $steps = $workflow->workflowsteps()->orderBy('stepOrder')->get();
        $this->assertCount(8, $steps);
        $this->assertEquals(1, $steps[0]->stepOrder);
        $this->assertEquals(8, $steps[7]->stepOrder);
    }

    /** @test */
    public function it_allows_signing_step_in_order(): void
    {
        $workflow = $this->workflowService->createWorkflow($this->enrollment);

        // Sign step 1 (Registrar - Clearance Desk Receipt)
        $step = $this->workflowService->signStep($workflow, 1, $this->registrarStaff);
        $this->assertEquals(WorkflowStepStatus::Completed, $step->stepStatus);
        $this->assertEquals($this->registrarStaff->userId, $step->signedBy);
        $this->assertNotNull($step->signedDate);
    }

    /** @test */
    public function it_throws_exception_when_signing_out_of_order(): void
    {
        $workflow = $this->workflowService->createWorkflow($this->enrollment);

        $this->expectException(InvalidStateTransitionException::class);

        // Try to sign step 3 before step 1 and 2
        $this->workflowService->signStep($workflow, 3, $this->registrarStaff);
    }

    /** @test */
    public function it_throws_exception_when_wrong_office_signs(): void
    {
        $workflow = $this->workflowService->createWorkflow($this->enrollment);

        // Sign step 1 first
        $this->workflowService->signStep($workflow, 1, $this->registrarStaff);

        $this->expectException(InvalidStateTransitionException::class);

        // Try to sign step 7 (Clinic) with Registrar staff
        $this->workflowService->signStep($workflow, 7, $this->registrarStaff);
    }

    /** @test */
    public function it_allows_correct_office_to_sign(): void
    {
        $workflow = $this->workflowService->createWorkflow($this->enrollment);

        // Sign step 1 (Registrar)
        $this->workflowService->signStep($workflow, 1, $this->registrarStaff);

        // Sign step 2 (Guidance/Dept - officeId 4)
        $deptStaff = Staffusers::factory()->create(['officeId' => 4]);
        $this->workflowService->signStep($workflow, 2, $deptStaff);

        // Sign step 3 (Accounting/Scholarship - officeId 3)
        $scholarshipStaff = Staffusers::factory()->create(['officeId' => 3]);
        $this->workflowService->signStep($workflow, 3, $scholarshipStaff);

        // Sign step 4 (Accounting - officeId 2)
        $accountingStaff = Staffusers::factory()->create(['officeId' => 2]);
        $this->workflowService->signStep($workflow, 4, $accountingStaff);

        // Sign step 5 (Registrar)
        $this->workflowService->signStep($workflow, 5, $this->registrarStaff);

        // Sign step 6 (Academic Dept - officeId 5)
        $acadStaff = Staffusers::factory()->create(['officeId' => 5]);
        $this->workflowService->signStep($workflow, 6, $acadStaff);

        // Sign step 7 (Clinic - officeId 11)
        $this->workflowService->signStep($workflow, 7, $this->clinicStaff);

        // Sign step 8 (ID Office - officeId 22)
        $this->workflowService->signStep($workflow, 8, $this->idStaff);

        $workflow->refresh();
        $this->assertEquals(WorkflowStatus::Completed, $workflow->workflowStatus);
    }

    /** @test */
    public function it_returns_current_pending_step(): void
    {
        $workflow = $this->workflowService->createWorkflow($this->enrollment);

        $currentStep = $this->workflowService->getCurrentStep($workflow);
        $this->assertNotNull($currentStep);
        $this->assertEquals(1, $currentStep->stepOrder);

        // Sign step 1
        $this->workflowService->signStep($workflow, 1, $this->registrarStaff);

        $currentStep = $this->workflowService->getCurrentStep($workflow);
        $this->assertEquals(2, $currentStep->stepOrder);
    }

    /** @test */
    public function it_returns_all_steps(): void
    {
        $workflow = $this->workflowService->createWorkflow($this->enrollment);

        $steps = $this->workflowService->getSteps($workflow);
        $this->assertCount(8, $steps);
    }
}