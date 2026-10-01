<?php

namespace Tests\Feature\Workflow;

use App\Enums\EnrollmentStatus;
use App\Enums\UnitType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Models\Students;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Concern #4: the workflow step indicator must tell the truth about which phase
 * a student is in.
 *
 * Two defects made it lie. The tracker named each box after the offices table
 * row that signs it, so the first box read "Guidance" while the student was
 * standing in Department Evaluation; and a Registrar return was indistinguishable
 * from an ordinary completed box, so a record sent back for correction still
 * looked finished. Every desk page is now given the workflow's own phase
 * vocabulary instead of inferring it, and these tests pin that contract — the
 * React stepper reads exactly these props.
 */
class WorkflowPhaseIndicatorTest extends TestCase
{
    use DatabaseTransactions;

    private int $testCourseId;

    private int $testTermId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite']);

        Offices::insert([
            ['officeId' => 1, 'officeName' => 'Registrar'],
            ['officeId' => 2, 'officeName' => 'Accounting Office'],
            ['officeId' => 3, 'officeName' => 'Assessment Office'],
            ['officeId' => 4, 'officeName' => 'Guidance Office'],
            ['officeId' => 5, 'officeName' => 'Blocking and Scheduling'],
            ['officeId' => 6, 'officeName' => 'Admission Office'],
            ['officeId' => 11, 'officeName' => 'Clinic'],
            ['officeId' => 22, 'officeName' => 'ID Office'],
        ]);

        Religions::create(['religionId' => 1, 'religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitCode' => 'CCS',
            'unitName' => 'College of Computer Studies',
            'unitType' => UnitType::College,
        ]);

        $course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

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

        $this->testCourseId = $course->courseId;
        $this->testTermId = $term->termId;

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RbacSeeder', '--database' => 'sqlite']);
    }

    private function staffForOffice(int $officeId, string $spatieRole = 'OfficeHead', ?string $modelRole = null): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => $officeId,
            'role' => $modelRole ?? ($spatieRole === 'Admin' ? 'admin' : 'officeHead'),
            'employeeNo' => 'EMP-PHASE-'.uniqid(),
            'username' => 'phase_office'.$officeId.'_'.uniqid(),
            'email' => 'phase_office'.$officeId.'_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();
        $staff->assignRole($spatieRole);

        return $staff;
    }

    private function makeEnrollment(): Enrollments
    {
        $student = Students::create([
            'schoolIdNumber' => 'PHASE-'.uniqid(),
            'lastName' => 'Dela Cruz',
            'firstName' => 'Juan',
            'middleName' => 'M',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'email' => 'phase_'.uniqid().'@example.com',
            'username' => 'phase_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $this->testCourseId,
            'termId' => $this->testTermId,
            'yearLevel' => 1,
            'studentType' => 'firstYear',
            'enrollmentType' => 'new',
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Paid,
            'evaluatedBy' => $this->staffForOffice(4, 'DeptEvaluator')->userId,
        ]);

        // A settled assessment is what makes the record approvable — the
        // return action runs through the same registrar.approve gate.
        Studentassessments::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'totalAssessedAmount' => 1000,
            'totalScholarshipCoverage' => 0,
            'totalWaived' => 0,
            'remainingBalance' => 0,
            'assessmentDate' => now(),
        ]);

        return $enrollment;
    }

    #[Test]
    public function every_desk_page_is_given_the_phase_vocabulary_the_stepper_prints(): void
    {
        $this->actingAs($this->staffForOffice(1))
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                // Keyed by the office that signs the box, valued by the phase a
                // student is actually standing in.
                ->where('workflowPhases.4', 'Department Evaluation')
                ->where('workflowPhases.3', 'Assessment')
                ->where('workflowPhases.2', 'Accounting Payment')
                ->where('workflowPhases.1', 'Registrar Approval')
                ->where('workflowPhases.5', 'Blocking and Scheduling')
                ->where('workflowPhases.11', 'Clinic')
                ->where('workflowPhases.22', 'ID Validation')
                // A Registrar return sends the record back to this box.
                ->where('workflowReturnOfficeId', 4)
            );
    }

    #[Test]
    public function the_box_order_the_stepper_prints_matches_the_order_steps_are_signed_in(): void
    {
        $enrollment = $this->makeEnrollment();
        $workflow = (new WorkflowService)->createWorkflow($enrollment);

        $storedOrder = $workflow->workflowsteps()->orderBy('stepOrder')->pluck('officeId')->all();

        $this->assertSame([4, 3, 2, 1, 5, 11, 22], $storedOrder, 'The form boxes must appear in signing order.');

        $labels = WorkflowService::stepLabels();
        foreach ($storedOrder as $officeId) {
            $this->assertArrayHasKey($officeId, $labels, "Box for office {$officeId} has no phase name and would render blank.");
        }
    }

    #[Test]
    public function a_returned_enrollment_reaches_the_desk_flagged_for_the_department_evaluation_box(): void
    {
        $enrollment = $this->makeEnrollment();
        (new WorkflowService)->createWorkflow($enrollment);

        $registrar = $this->staffForOffice(1);

        $this->actingAs($registrar)
            ->post(route('registrar.return', $enrollment), [
                'returnReason' => 'Proposed load lists a subject whose prerequisite is not on file.',
            ])
            ->assertSessionHasNoErrors();

        // These three props are what the stepper turns into a red "Returned"
        // box: the status says a return is open, the reason says why, and the
        // step list still carries the Department Evaluation box to put it on.
        $this->actingAs($registrar)
            ->get(route('registrar.show', $enrollment))
            ->assertInertia(fn ($page) => $page
                ->where('enrollment.enrollmentStatus', 'returnedToEvaluation')
                ->where('enrollment.returnReason', 'Proposed load lists a subject whose prerequisite is not on file.')
                ->where('enrollment.enrollmentworkflow.workflowsteps.0.officeId', 4)
                ->where('workflowReturnOfficeId', 4)
            );
    }

    #[Test]
    public function the_dashboard_progress_row_names_the_phase_the_student_is_still_waiting_on(): void
    {
        $enrollment = $this->makeEnrollment();
        $workflow = (new WorkflowService)->createWorkflow($enrollment);
        $workflow->update(['currentStep' => 1]);
        $workflow->workflowsteps()->where('stepOrder', 1)->update(['stepStatus' => 'completed']);

        $admin = $this->staffForOffice(1, 'Admin');

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('progressTracking.0.completedSteps', 1)
                ->where('progressTracking.0.totalSteps', 7)
                ->where('progressTracking.0.currentPhase', 'Assessment')
            );
    }
}
