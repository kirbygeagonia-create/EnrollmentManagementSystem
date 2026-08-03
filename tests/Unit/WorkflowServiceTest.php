<?php

namespace Tests\Unit;

use App\Enums\EnrollmentStatus;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowStepStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Admissions;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
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

        $this->workflowService = new WorkflowService;

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

        Religions::create(['religionName' => 'Roman Catholic']);
        Academicunits::create([
            'unitName' => 'College of Computing',
            'unitType' => 'college',
        ]);
        Academicyears::create([
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-03-31',
        ]);

        // Create enrollment
        $student = Students::create([
            'schoolIdNumber' => '2026-0002',
            'lastName' => 'Test',
            'firstName' => 'Student',
            'middleName' => 'M',
            'suffix' => '',
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
            'evaluatedBy' => $this->registrarStaff->userId,
        ]);
    }

    #[Test]
    public function it_creates_workflow_with_7_steps_for_first_year(): void
    {
        $workflow = $this->workflowService->createWorkflow($this->enrollment);

        $this->assertEquals(1, $workflow->currentStep);
        $this->assertEquals(WorkflowStatus::InProgress, $workflow->workflowStatus);

        $steps = $workflow->workflowsteps()->orderBy('stepOrder')->get();
        $this->assertCount(7, $steps);

        // Verify stepOrder is sequential 1..7
        $this->assertEquals(1, $steps[0]->stepOrder);
        $this->assertEquals(7, $steps[6]->stepOrder);

        // Verify officeId sequence: [4, 3, 2, 1, 5, 11, 22]
        $expectedOfficeIds = [4, 3, 2, 1, 5, 11, 22];
        foreach ($expectedOfficeIds as $i => $officeId) {
            $this->assertEquals($officeId, $steps[$i]->officeId, 'Step '.($i + 1)." should have officeId {$officeId}");
        }
    }

    #[Test]
    public function it_creates_workflow_without_assessment_for_continuing_student(): void
    {
        // Create a continuing student enrollment
        $student = Students::create([
            'schoolIdNumber' => '2026-0003',
            'lastName' => 'Continuing',
            'firstName' => 'Student',
            'middleName' => 'C',
            'suffix' => '',
            'gender' => 'female',
            'birthdate' => '2001-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'religionId' => 1,
            'civilStatus' => 'single',
            'contactNumber' => '09123456780',
            'email' => 'continuing@example.com',
            'username' => 'continuingstudent',
            'passwordHash' => bcrypt('password'),
            'status' => 'active',
            'semestersCompleted' => 2,
            'yearsInInstitution' => 1,
        ]);

        $continuingEnrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => 1,
            'termId' => 1,
            'admissionId' => null,
            'yearLevel' => 2,
            'studentType' => 'continuing',
            'enrollmentType' => 'old',
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Pending,
            'evaluatedBy' => $this->registrarStaff->userId,
        ]);

        $workflow = $this->workflowService->createWorkflow($continuingEnrollment);

        $this->assertEquals(1, $workflow->currentStep);
        $this->assertEquals(WorkflowStatus::InProgress, $workflow->workflowStatus);

        $steps = $workflow->workflowsteps()->orderBy('stepOrder')->get();
        $this->assertCount(6, $steps);

        // Verify stepOrder is sequential 1..6
        $this->assertEquals(1, $steps[0]->stepOrder);
        $this->assertEquals(6, $steps[5]->stepOrder);

        // Verify officeIds: [4, 2, 1, 5, 11, 22] — no officeId 3 (Assessment)
        $expectedOfficeIds = [4, 2, 1, 5, 11, 22];
        foreach ($expectedOfficeIds as $i => $officeId) {
            $this->assertEquals($officeId, $steps[$i]->officeId, 'Step '.($i + 1)." should have officeId {$officeId}");
        }

        // Step at stepOrder 2 should be Accounting Payment (officeId 2)
        $this->assertEquals(2, $steps[1]->officeId, 'Step 2 should be Accounting Payment (officeId 2)');
    }

    #[Test]
    public function it_creates_workflow_without_assessment_for_shifter_student(): void
    {
        // Create a shifter student enrollment
        $student = Students::create([
            'schoolIdNumber' => '2026-0004',
            'lastName' => 'Shifter',
            'firstName' => 'Student',
            'middleName' => 'S',
            'suffix' => '',
            'gender' => 'male',
            'birthdate' => '2002-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'religionId' => 1,
            'civilStatus' => 'single',
            'contactNumber' => '09123456781',
            'email' => 'shifter@example.com',
            'username' => 'shifterstudent',
            'passwordHash' => bcrypt('password'),
            'status' => 'active',
            'semestersCompleted' => 1,
            'yearsInInstitution' => 1,
        ]);

        $shifterEnrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => 1,
            'termId' => 1,
            'admissionId' => null,
            'yearLevel' => 2,
            'studentType' => 'shifter',
            'enrollmentType' => 'old',
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Pending,
            'evaluatedBy' => $this->registrarStaff->userId,
        ]);

        $workflow = $this->workflowService->createWorkflow($shifterEnrollment);

        $this->assertEquals(1, $workflow->currentStep);
        $this->assertEquals(WorkflowStatus::InProgress, $workflow->workflowStatus);

        $steps = $workflow->workflowsteps()->orderBy('stepOrder')->get();
        $this->assertCount(6, $steps);

        // Verify stepOrder is sequential 1..6
        $this->assertEquals(1, $steps[0]->stepOrder);
        $this->assertEquals(6, $steps[5]->stepOrder);

        // Verify officeIds: [4, 2, 1, 5, 11, 22] — no officeId 3 (Assessment)
        $expectedOfficeIds = [4, 2, 1, 5, 11, 22];
        foreach ($expectedOfficeIds as $i => $officeId) {
            $this->assertEquals($officeId, $steps[$i]->officeId, 'Step '.($i + 1)." should have officeId {$officeId}");
        }

        // Step at stepOrder 2 should be Accounting Payment (officeId 2)
        $this->assertEquals(2, $steps[1]->officeId, 'Step 2 should be Accounting Payment (officeId 2)');
    }

    #[Test]
    public function it_allows_signing_step_in_order(): void
    {
        $workflow = $this->workflowService->createWorkflow($this->enrollment);

        // Sign step 1 (Department Evaluation - officeId 4)
        $deptStaff = Staffusers::factory()->create(['officeId' => 4]);
        $step = $this->workflowService->signStep($workflow, 1, $deptStaff);
        $this->assertEquals(WorkflowStepStatus::Completed, $step->stepStatus);
        $this->assertEquals($deptStaff->userId, $step->signedBy);
        $this->assertNotNull($step->signedDate);
    }

    #[Test]
    public function it_throws_exception_when_signing_out_of_order(): void
    {
        $workflow = $this->workflowService->createWorkflow($this->enrollment);

        $this->expectException(InvalidStateTransitionException::class);

        // Try to sign step 2 (Assessment, officeId 3) before step 1
        $assessmentStaff = Staffusers::factory()->create(['officeId' => 3]);
        $this->workflowService->signStep($workflow, 2, $assessmentStaff);
    }

    #[Test]
    public function it_throws_exception_when_wrong_office_signs(): void
    {
        $workflow = $this->workflowService->createWorkflow($this->enrollment);

        // Sign step 1 (Department Evaluation) with office-4 staff
        $deptStaff = Staffusers::factory()->create(['officeId' => 4]);
        $this->workflowService->signStep($workflow, 1, $deptStaff);

        $this->expectException(InvalidStateTransitionException::class);

        // Try to sign step 6 (Clinic, officeId 11) with Registrar staff (officeId 1)
        $this->workflowService->signStep($workflow, 6, $this->registrarStaff);
    }

    #[Test]
    public function it_allows_correct_office_to_sign(): void
    {
        $workflow = $this->workflowService->createWorkflow($this->enrollment);

        // Sign step 1 (Department Evaluation - officeId 4)
        $deptStaff = Staffusers::factory()->create(['officeId' => 4]);
        $this->workflowService->signStep($workflow, 1, $deptStaff);

        // Sign step 2 (Assessment - officeId 3)
        $assessmentStaff = Staffusers::factory()->create(['officeId' => 3]);
        $this->workflowService->signStep($workflow, 2, $assessmentStaff);

        // Sign step 3 (Accounting Payment - officeId 2)
        $accountingStaff = Staffusers::factory()->create(['officeId' => 2]);
        $this->workflowService->signStep($workflow, 3, $accountingStaff);

        // Sign step 4 (Registrar Approval - officeId 1)
        $this->workflowService->signStep($workflow, 4, $this->registrarStaff);

        // Sign step 5 (Blocking and Scheduling - officeId 5)
        $acadStaff = Staffusers::factory()->create(['officeId' => 5]);
        $this->workflowService->signStep($workflow, 5, $acadStaff);

        // Sign step 6 (Clinic - officeId 11)
        $this->workflowService->signStep($workflow, 6, $this->clinicStaff);

        // Sign step 7 (ID Office - officeId 22)
        $this->workflowService->signStep($workflow, 7, $this->idStaff);

        $workflow->refresh();
        $this->assertEquals(WorkflowStatus::Completed, $workflow->workflowStatus);
    }

    #[Test]
    public function it_returns_current_pending_step(): void
    {
        $workflow = $this->workflowService->createWorkflow($this->enrollment);

        $currentStep = $this->workflowService->getCurrentStep($workflow);
        $this->assertNotNull($currentStep);
        $this->assertEquals(1, $currentStep->stepOrder);

        // Sign step 1 (Department Evaluation - officeId 4)
        $deptStaff = Staffusers::factory()->create(['officeId' => 4]);
        $this->workflowService->signStep($workflow, 1, $deptStaff);

        $currentStep = $this->workflowService->getCurrentStep($workflow);
        $this->assertEquals(2, $currentStep->stepOrder);
    }

    #[Test]
    public function it_returns_all_steps(): void
    {
        $workflow = $this->workflowService->createWorkflow($this->enrollment);

        $steps = $this->workflowService->getSteps($workflow);
        $this->assertCount(7, $steps);
    }

    #[Test]
    public function it_signs_step_by_office_id(): void
    {
        $workflow = $this->workflowService->createWorkflow($this->enrollment);

        // Sign step 1 (Department Evaluation - officeId 4) via signStepByOffice
        $deptStaff = Staffusers::factory()->create(['officeId' => 4]);
        $step = $this->workflowService->signStepByOffice($workflow, 4, $deptStaff);
        $this->assertNotNull($step);
        $this->assertEquals(WorkflowStepStatus::Completed, $step->stepStatus);
        $this->assertEquals(1, $step->stepOrder);

        // Sign step 2 (Assessment - officeId 3) via signStepByOffice
        $assessmentStaff = Staffusers::factory()->create(['officeId' => 3]);
        $step = $this->workflowService->signStepByOffice($workflow, 3, $assessmentStaff);
        $this->assertNotNull($step);
        $this->assertEquals(WorkflowStepStatus::Completed, $step->stepStatus);
        $this->assertEquals(2, $step->stepOrder);
    }

    #[Test]
    public function it_returns_null_when_signing_office_not_in_workflow(): void
    {
        // Create a continuing student enrollment (no Assessment step)
        $student = Students::create([
            'schoolIdNumber' => '2026-0005',
            'lastName' => 'Continuing',
            'firstName' => 'Student',
            'middleName' => 'C',
            'suffix' => '',
            'gender' => 'female',
            'birthdate' => '2001-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'religionId' => 1,
            'civilStatus' => 'single',
            'contactNumber' => '09123456780',
            'email' => 'continuing2@example.com',
            'username' => 'continuingstudent2',
            'passwordHash' => bcrypt('password'),
            'status' => 'active',
            'semestersCompleted' => 2,
            'yearsInInstitution' => 1,
        ]);

        $continuingEnrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => 1,
            'termId' => 1,
            'admissionId' => null,
            'yearLevel' => 2,
            'studentType' => 'continuing',
            'enrollmentType' => 'old',
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Pending,
            'evaluatedBy' => $this->registrarStaff->userId,
        ]);

        $workflow = $this->workflowService->createWorkflow($continuingEnrollment);

        // Try to sign Assessment step (officeId 3) - should return null since it's skipped
        $assessmentStaff = Staffusers::factory()->create(['officeId' => 3]);
        $result = $this->workflowService->signStepByOffice($workflow, 3, $assessmentStaff);
        $this->assertNull($result);

        // Workflow should still have 6 steps (no Assessment)
        $steps = $workflow->workflowsteps()->orderBy('stepOrder')->get();
        $this->assertCount(6, $steps);
    }

    #[Test]
    public function it_throws_when_wrong_office_uses_sign_step_by_office(): void
    {
        $workflow = $this->workflowService->createWorkflow($this->enrollment);

        $this->expectException(InvalidStateTransitionException::class);

        // Try to sign step 1 (Department Evaluation - officeId 4) with Registrar staff (officeId 1)
        $this->workflowService->signStepByOffice($workflow, 4, $this->registrarStaff);
    }
}
