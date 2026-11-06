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
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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
        $phases = $this->actingAs($this->staffForOffice(1))
            ->get('/dashboard')
            ->assertOk()
            ->viewData('page')['props']['workflowPhases'];

        // Keyed by nothing. The list ARRIVES as a list: an object keyed by office id is what
        // the tracker used to be handed, and JavaScript enumerates an object's integer-like
        // keys in ascending numeric order, so the seven boxes were re-sorted into the offices
        // table's numbering before a single pixel was painted — Registrar first, Department
        // Evaluation fourth. The order is the claim, so the order has to survive the wire.
        $this->assertTrue(array_is_list($phases), 'workflowPhases must reach the browser as a list, not as a map keyed by office id.');

        // The order a student walks, not the order the offices were seeded in.
        $this->assertSame([4, 3, 2, 1, 5, 11, 22], array_column($phases, 'officeId'));
        $this->assertSame(
            ['Department Evaluation', 'Assessment', 'Accounting Payment', 'Registrar Approval', 'Blocking and Scheduling', 'Clinic', 'ID Validation'],
            array_column($phases, 'label')
        );
        $this->assertSame(['Guidance', 'Scholarship', 'Accounting', 'Registrar', 'Blocking', 'Clinic', 'IdOffice'], array_column($phases, 'office'));
        $this->assertSame(
            array_column(WorkflowService::boxOrder(), 'officeId'),
            array_column($phases, 'officeId'),
            'The page is given the workflow’s own declaration, not a restatement of it.'
        );

        // A Registrar return sends the record back to this box.
        $this->assertSame(4, $this->actingAs($this->staffForOffice(1))
            ->get('/dashboard')
            ->assertOk()
            ->viewData('page')['props']['workflowReturnOfficeId']);
    }

    #[Test]
    public function the_stepper_numbers_the_boxes_the_record_holds_not_the_seven_the_form_declares(): void
    {
        // No JavaScript suite runs here, so this reads the source: the record's own rows are
        // what the numbering is taken from. `boxes.length` is the canonical seven, and a
        // continuing student's form holds six — printing the canonical index would put a "4"
        // on the third box of that record.
        $stepper = file_get_contents(base_path('resources/js/Components/ui/WorkflowStepper.jsx'));

        $this->assertStringContainsString('const positions = new Map(stored.map(', $stepper);
        $this->assertStringContainsString('position: positions.get(officeId) ?? null', $stepper);
        $this->assertStringNotContainsString('position: index + 1', $stepper);
        $this->assertStringNotContainsString('boxes.findIndex', $stepper);
        $this->assertStringNotContainsString('Object.entries(workflowPhases', $stepper, 'Enumerating a map keyed by office id is what re-sorted the boxes.');

        // The progress circles print the same number the sentence under them prints.
        $progress = file_get_contents(base_path('resources/js/Components/ui/StepProgress.jsx'));
        $this->assertStringContainsString('step.number ?? index + 1', $progress);
    }

    #[Test]
    public function no_surface_spells_a_workflow_phase_number_by_hand(): void
    {
        $typed = [];

        foreach ($this->paintedSources() as $path => $source) {
            // `Phase <digit>` typed into a screen is a second arithmetic for the same box. The
            // numbers come from `WorkflowService::boxOrder()`, so a surface spells the WORD of
            // the box it works and lets the shared list number it.
            if (preg_match_all('/Phase [0-9]+/', $source, $hits)) {
                foreach ($hits[0] as $hit) {
                    $typed[] = $path.' → '.$hit;
                }
            }
        }

        $this->assertGreaterThan(60, count($this->paintedSources()), 'The walk read almost nothing, so its clean answer would mean nothing.');

        sort($typed);
        $this->assertSame([], $typed, 'A hand-typed phase number disagrees with the tracker the moment the boxes move.');
    }

    #[Test]
    public function every_phase_box_word_a_surface_names_is_a_box_the_workflow_declares(): void
    {
        $labels = array_column(WorkflowService::boxOrder(), 'label');
        $claimed = [];
        $unknown = [];

        foreach ($this->paintedSources() as $path => $source) {
            if (! str_ends_with($path, '.jsx')) {
                continue;
            }

            preg_match_all('/phaseBox(?:=|:)\s*(?:"|\x27)([^"\x27]+)(?:"|\x27)/', $source, $hits);

            foreach ($hits[1] as $word) {
                $claimed[$word][] = $path;

                if (! in_array($word, $labels, true)) {
                    $unknown[] = $word.' ('.basename($path).')';
                }
            }
        }

        sort($unknown);
        $this->assertSame([], $unknown, 'A surface named a box the workflow does not declare, so its chip would print no phase.');

        // The other direction is the claim worth pinning: a box no desk page names would mean a
        // phase that appears nowhere in the chrome — the header and the tracker would silently
        // stop answering for the same desk.
        $unclaimed = array_values(array_diff($labels, array_keys($claimed)));
        sort($unclaimed);
        $this->assertSame([], $unclaimed, 'A workflow box is declared but no desk page numbers it.');
        $this->assertCount(count($labels), $claimed);
    }

    /**
     * Every React source under `resources/js`, with the comments taken out.
     *
     * A comment that quotes the retired spelling ("the header saw Phase 5") is a record of the
     * defect, not a second arithmetic, so the guard reads what a screen can paint. Whole-line
     * comments and docblocks go; a trailing comment on a code line stays, because an
     * over-reported comment is a loud nuisance and an under-reported screen is a silent one.
     *
     * @return array<string, string>
     */
    private function paintedSources(): array
    {
        $sources = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('resources/js')));

        foreach ($files as $file) {
            if (! $file->isFile() || ! in_array($file->getExtension(), ['jsx', 'js'], true)) {
                continue;
            }

            $text = file_get_contents($file->getPathname());
            $text = preg_replace('/\/\*.*?\*\//s', '', $text);
            $text = implode("\n", array_filter(
                explode("\n", $text),
                static fn (string $line): bool => ! preg_match('/^\s*(\/\/|\*)/', $line)
            ));

            $sources[str_replace('\\', '/', $file->getPathname())] = $text;
        }

        return $sources;
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
        $workflow = (new WorkflowService)->createWorkflow($enrollment);

        // The one return act development prompt §6.4 asks for (gap G-10) refuses a destination that
        // has not acted yet, so the box the record is sent back to has to hold a signature first.
        $workflow->workflowsteps()->where('officeId', 4)->update([
            'stepStatus' => 'completed',
            'signedDate' => now(),
        ]);

        // Ruling 7: the return is the Registrar desk's own right (`enrollment.return`, seated with
        // RegistrarApprover), not a reuse of the approval right and not any office head's.
        $registrar = $this->staffForOffice(1, 'RegistrarApprover', 'staff');

        $this->actingAs($registrar)
            ->post(route('enrollment.return', $enrollment), [
                'destination' => 'departmentEvaluation',
                'returnCategory' => 'curriculumSectionMismatch',
                'returnNote' => 'Proposed load lists a subject whose prerequisite is not on file.',
                'expectedCorrection' => 'Re-propose the load without that subject, then sign again.',
            ])
            ->assertSessionHasNoErrors();

        // These props are what the stepper turns into a red "Returned" box: the status says a return
        // is open, the reason says why, and the step list still carries the Department Evaluation box
        // to put it on.
        $this->actingAs($registrar)
            ->get(route('registrar.show', $enrollment))
            ->assertInertia(fn ($page) => $page
                ->where('enrollment.enrollmentStatus', 'returnedToEvaluation')
                ->where('enrollment.returnReason', 'Proposed load lists a subject whose prerequisite is not on file.')
                ->where('enrollment.enrollmentworkflow.workflowsteps.0.officeId', 4)
                ->where('workflowReturnOfficeId', 4)
                // …and since §6.4 the notice also names the desk that sent it, which is no longer a
                // fact the page may infer from its own office number.
                ->where('openReturn.originOfficeName', 'Registrar')
                ->where('openReturn.destinationLabel', 'Department Evaluation'));
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
