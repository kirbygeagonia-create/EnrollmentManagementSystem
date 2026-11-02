<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AdmissionStatus;
use App\Enums\ClearanceOverallStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\StudentType;
use App\Enums\WorkflowStepStatus;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Admissions;
use App\Models\Blocks;
use App\Models\Clearanceperiods;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Models\Studentclearances;
use App\Models\Students;
use App\Services\WorkflowService;
use App\Support\DeskQueues;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Prompt §8: "a dashboard count must not contradict the list underneath it" (matrix gap G-46; the docx register
 * has no G-46 row yet — §28.3 stops at G-43, which is why this comment names the matrix).
 *
 * `DashboardController::queueCounts()` restated each desk's filters as its own query and drifted three ways:
 * `admission` and `evaluation` counted the whole school while the pages behind them are bounded by the desk's
 * college; `evaluation` counted only `Pending` while the desk also lists what the Registrar sent back; and
 * `assessment` counted enrollments where its page lists fee sheets. All three now ask `App\Support\DeskQueues`,
 * so the badge and the desk's own tile are one predicate.
 *
 * The direction of each case matters. Asserting "a number came back" would pass on every one of the old bugs, so
 * each case reads the desk's own printed figure and requires the badge to equal it, and the fixture is built so the
 * two would disagree under the retired filter. The college case is the same shape from the other side: a row in
 * another college is on the database and NOT on this desk, so an institution-wide count is provably wrong here.
 *
 * The `blocking` key joined the rule on 2026-11-02, when the owner ruled sheet item 9: the card counts what its own
 * page lists, so it counts timetable blocks — the rows `BlockingController::index()` hands its tiles — rather than
 * the enrollments waiting at that office. The retired predicate is the one thing the case below is built to refuse.
 *
 * Two directions are pinned, and only one of them was true before this batch. Bounded where the list is bounded:
 * `admission` and `evaluation` honour `CollegeScope` because those two controllers bound their own lists with it,
 * so a department's card must shrink with its page. Unbounded where the list is unbounded: the Assessment,
 * Accounting, Registrar, Clinic, ID, Clearance and Blocking desks carry no college filter in their handlers or
 * policies, so
 * their badges must not grow one either — `desks_without_a_college_filter_count_the_school_as_their_lists_do`
 * is the case that would have failed under the first draft of this batch, which scoped the two assessment badges
 * and left their pages school-wide. `clinic`, `id` and `blocking` now build their desk lists from the very builder
 * the badge counts (`DeskQueues::clinic()` / `::id()` / `::blocking()`), so those equalities are one call rather
 * than a duplicated rule.
 */
class DashboardQueueCountMatchesDeskTest extends TestCase
{
    use RefreshDatabase;

    private Academicterms $term;

    private Courses $courseA;

    private Courses $courseB;

    private Staffusers $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $unitA = Academicunits::create(['unitName' => 'College of Computing', 'unitType' => 'college']);
        $unitB = Academicunits::create(['unitName' => 'College of Business', 'unitType' => 'college']);
        $this->courseA = Courses::create([
            'unitId' => $unitA->unitId, 'courseCode' => 'BSIT', 'courseName' => 'Information Technology',
            'requiresEntranceExam' => false, 'requiresRetentionExam' => false,
        ]);
        $this->courseB = Courses::create([
            'unitId' => $unitB->unitId, 'courseCode' => 'BSBA', 'courseName' => 'Business Administration',
            'requiresEntranceExam' => false, 'requiresRetentionExam' => false,
        ]);

        $year = Academicyears::create([
            'yearLabel' => '2026-2027', 'startDate' => '2026-06-01', 'endDate' => '2027-03-31',
        ]);
        $this->term = Academicterms::create([
            'academicYearId' => $year->academicYearId, 'semester' => '1st',
            'startDate' => '2026-06-01', 'endDate' => '2026-10-31',
        ]);

        // `enrollments.evaluatedBy` is NOT NULL with no default — every enrollment is credited to a department
        // account the moment it exists, so a fixture that wants a queue row must name one.
        $this->evaluator = Staffusers::factory()->create(['officeId' => 7]);
    }

    private function student(string $tag): Students
    {
        return Students::create([
            'schoolIdNumber' => 'DQ-'.$tag.str_pad((string) uniqid(), 4, '0', STR_PAD_LEFT),
            'lastName' => 'Queue', 'firstName' => 'Given', 'middleName' => 'M', 'suffix' => 'N/A',
            'gender' => 'female', 'birthdate' => '2004-01-01', 'birthplace' => 'Test City',
            'citizenship' => 'Filipino', 'civilStatus' => 'single', 'religionId' => 1,
            'contactNumber' => '09171234567', 'email' => 'dq_'.uniqid().'@example.com',
            'username' => 'dq_'.uniqid(), 'passwordHash' => bcrypt('password123'), 'status' => 'active',
        ]);
    }

    private function enrollment(Courses $course, EnrollmentStatus $status): Enrollments
    {
        return Enrollments::create([
            'studentId' => $this->student($course->courseCode)->studentId,
            'courseId' => $course->courseId,
            'termId' => $this->term->termId,
            'yearLevel' => 1,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'enrollmentStatus' => $status,
            'evaluatedBy' => $this->evaluator->userId,
        ]);
    }

    /** The desk account that can see every card, bounded by no college. */
    private function unbounded(): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => 1, 'unitId' => null,
            'employeeNo' => 'EMP-DQ-'.uniqid(), 'username' => 'dq_admin_'.uniqid(),
            'email' => 'dq_admin_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('SysAdmin');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    /** @return array<string, int> */
    private function badges(Staffusers $staff): array
    {
        return $this->actingAs($staff)->getJson(route('dashboard.queue-counts'))->assertOk()->json('queueCounts');
    }

    /** @return array<string, mixed> */
    private function props(Staffusers $staff, string $route): array
    {
        return $this->actingAs($staff)->get(route($route))->assertOk()->viewData('page')['props'];
    }

    #[Test]
    public function the_evaluation_badge_counts_what_the_department_actually_lists(): void
    {
        // The retired filter counted Pending only; this desk's list has always carried the returned papers too,
        // so a badge built from Pending alone read one while the table showed two.
        $this->enrollment($this->courseA, EnrollmentStatus::Pending);
        $this->enrollment($this->courseA, EnrollmentStatus::ReturnedToEvaluation);

        $staff = $this->unbounded();
        $badges = $this->badges($staff);
        $props = $this->props($staff, 'evaluation.index');

        $this->assertSame(2, (int) $props['stats']['total'], 'the fixture no longer disagrees with the old filter');
        $this->assertSame(
            (int) $props['stats']['total'],
            (int) $badges['evaluation'],
            'the badge contradicts the queue the card opens'
        );
    }

    #[Test]
    public function a_college_desk_is_counted_inside_its_own_college(): void
    {
        $this->enrollment($this->courseA, EnrollmentStatus::Pending);
        $this->enrollment($this->courseB, EnrollmentStatus::Pending);

        // Two applications, one per college. `AdmissionController::index()` bounds its own list with
        // CollegeScope::ofAdmissions, so the card that opens it must bound the same way — and this is the only
        // case that reads that badge, which is why the harness's admission leg has somewhere to bite.
        Admissions::create([
            'studentId' => $this->student('A1')->studentId,
            'courseId' => $this->courseA->courseId,
            'termId' => $this->term->termId,
            'applicantType' => 'firstYear',
            'admissionStatus' => AdmissionStatus::Pending->value,
        ]);
        Admissions::create([
            'studentId' => $this->student('B1')->studentId,
            'courseId' => $this->courseB->courseId,
            'termId' => $this->term->termId,
            'applicantType' => 'firstYear',
            'admissionStatus' => AdmissionStatus::Pending->value,
        ]);

        $desk = Staffusers::factory()->create([
            'officeId' => 7, 'unitId' => $this->courseA->unitId,
            'employeeNo' => 'EMP-DQD-'.uniqid(), 'username' => 'dq_dept_'.uniqid(),
            'email' => 'dq_dept_'.uniqid().'@example.com',
        ]);
        $desk->assignRole('DeptEvaluator');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $badges = $this->badges($desk);
        $props = $this->props($desk, 'evaluation.index');
        $admission = $this->props($desk, 'admission.index');

        $this->assertSame(
            1,
            (int) $props['stats']['total'],
            'a department should list one college’s enrollment, not both'
        );
        $this->assertSame(
            1,
            (int) $badges['evaluation'],
            'the badge counted the whole school while the page behind it is bounded by the college'
        );
        $this->assertSame(1, (int) $admission['stats']['pending'], 'the admission list is college-bound');
        $this->assertSame(
            (int) $admission['stats']['pending'],
            (int) $badges['admission'],
            'the admission card counted the other college’s application too'
        );
    }

    #[Test]
    public function each_named_desk_list_builds_its_rows_from_the_helper_and_the_clearance_desk_still_lists_more_than_its_badge(): void
    {
        // The agreement between a badge and its list used to be only a tested one: two copies of a predicate, with a
        // case watching whether they still said the same thing. Each desk named below now asks `DeskQueues` for its
        // rows, so there is one copy and drift is not representable — a stronger claim than equality, and it needs
        // its own guard, because a later batch that re-inlined the predicate would keep every number equal while
        // putting the two copies back. The population is named here rather than counted: a number in this method's
        // name rots the day one more list joins, which is the membership defect §28.3 owns.
        //
        // The reading is scoped to each index ACTION, and that matters: `AssessmentController::pendingEvaluations()`
        // holds a file-wide search would have called a legitimate query in another action a duplicate. And a
        // whole-file string test would have tripped on `AccountingController`'s own comment, which names
        // `DeskQueues::accounting()` to explain why the list does not call it.
        $shared = [
            'Assessment/AssessmentController.php' => [
                'DeskQueues::assessmentsFor(EnrollmentStatus::Evaluated)',
                "->whereHas('enrollment', fn (\$q) => \$q->where('enrollmentStatus', EnrollmentStatus::Evaluated))",
            ],
            'Registrar/RegistrarController.php' => [
                'DeskQueues::registrar()',
                "->whereIn('enrollmentStatus', [EnrollmentStatus::Assessed, EnrollmentStatus::Paid])",
            ],
            'Accounting/AccountingController.php' => [
                'DeskQueues::assessmentsFor(EnrollmentStatus::Assessed)',
                "->whereHas('enrollment', fn (\$q) => \$q->where('enrollmentStatus', EnrollmentStatus::Assessed))",
            ],
            'Evaluation/EvaluationController.php' => [
                'DeskQueues::evaluation($desk)',
                "->whereIn('enrollmentStatus', [EnrollmentStatus::Pending, EnrollmentStatus::ReturnedToEvaluation])",
            ],
            // The blocking desk since the owner's ruling of 2026-11-02. The retired text is that act's own
            // pre-ruling bytes, read off the file before this batch replaced them — not a guess at what a
            // re-inline might look like, because a needle that matches nothing makes this half of the guard
            // un-failable.
            'Blocking/BlockingController.php' => [
                'DeskQueues::blocking()',
                "Blocks::with(['course', 'term.academicYear'",
            ],
        ];
        foreach ($shared as $path => [$call, $retired]) {
            $body = $this->actionBody('app/Http/Controllers/'.$path, 'index');
            $this->assertStringContainsString(
                $call,
                $body,
                $path.'::index() builds its queue inline again: the dashboard card and this desk are two predicates once more'
            );
            // The claim is about THIS queue's predicate, not about every status filter in the action: an assessment
            // page builds a second list (the evaluated enrollments with no sheet yet) and that is a different
            // population with a badge of its own. A first draft of this assertion banned `where('enrollmentStatus',`
            // anywhere in index() and tripped on that legitimate second list — a guard that fires on the wrong
            // construct reports the file, not the rule.
            $this->assertStringNotContainsString(
                $retired,
                $body,
                $path.'::index() restates the queue predicate the helper now owns'
            );
        }

        // The clearance desk is the pair that must NOT be unified: its card counts the pending queue, its page lists
        // every status a desk may still act on. One approved clearance therefore makes the list longer than the
        // badge, and a future "fix" that built the list from `DeskQueues::clearance()` would hide that row.
        $period = Clearanceperiods::create([
            'periodName' => 'Subset proof period 2026-2027',
            'termId' => $this->term->termId,
            'clearanceStartDate' => '2026-11-01',
            'clearanceEndDate' => '2026-12-15',
            'periodStatus' => 'open',
        ]);
        foreach ([ClearanceOverallStatus::Pending, ClearanceOverallStatus::Approved] as $status) {
            // One clearance per student: `uq_student_clearanceperiod` is on (studentId, clearancePeriodId), so two
            // rows for the same pair would not be a test of anything, it would be an integrity error.
            Studentclearances::create([
                'studentId' => $this->student('SUB'.$status->value)->studentId,
                'clearancePeriodId' => $period->clearancePeriodId,
                'overallStatus' => $status->value,
                'academicStanding' => 'good',
                'studentType' => StudentType::FirstYear->value,
            ]);
        }

        $staff = $this->unbounded();
        $badges = $this->badges($staff);
        $clearance = $this->props($staff, 'clearance.index');

        $this->assertSame(1, (int) $badges['clearance'], 'the card counts the queue the desk awaits');
        $this->assertSame(2, (int) $clearance['clearances']['total'], 'the page lists every clearance a desk may act on');
    }

    /**
     * The body of one action, read from the file by byte offset.
     *
     * A whole-file string check cannot tell `index()` from its neighbours, and this repository's own rule for a
     * claim about a file is to read the construct the sentence names.
     */
    private function actionBody(string $relative, string $action): string
    {
        $src = (string) file_get_contents(base_path($relative));
        if (preg_match('/function\s+'.$action.'\s*\([^)]*\)[^{]*\{/', $src, $m, PREG_OFFSET_CAPTURE) !== 1) {
            $this->fail("no {$action}() in {$relative} — the file moved or the action was renamed");
        }
        $start = $m[0][1] + strlen($m[0][0]);
        $rest = substr($src, $start);
        $depth = 1;
        for ($i = 0, $len = strlen($rest); $i < $len; $i++) {
            if ($rest[$i] === '{') {
                $depth++;
            } elseif ($rest[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }
        }

        return substr($rest, 0, $i);
    }

    #[Test]
    public function the_assessment_badge_counts_the_sheets_the_desk_lists_not_the_enrollments(): void
    {
        // An evaluated enrollment with no fee sheet yet is the other list on that same page; counting
        // enrollments made the badge one larger than the table it opens.
        $withSheet = $this->enrollment($this->courseA, EnrollmentStatus::Evaluated);
        $this->enrollment($this->courseA, EnrollmentStatus::Evaluated);
        Studentassessments::create([
            'enrollmentId' => $withSheet->enrollmentId,
            'assessmentDate' => '2026-08-01',
            'totalAssessedAmount' => 18500,
            'totalScholarshipCoverage' => 0,
            'totalWaived' => 0,
            'remainingBalance' => 18500,
            'assessmentStatus' => 'pending',
        ]);

        $staff = $this->unbounded();
        $badges = $this->badges($staff);
        $props = $this->props($staff, 'assessment.index');

        $this->assertSame(1, (int) $props['assessments']['total']);
        $this->assertSame(
            (int) $props['assessments']['total'],
            (int) $badges['assessment'],
            'the badge counts enrollments while the desk lists assessment sheets'
        );
    }

    #[Test]
    public function the_accounting_badge_equals_the_due_queue_the_desk_opens_on(): void
    {
        $owing = $this->enrollment($this->courseA, EnrollmentStatus::Assessed);
        $settled = $this->enrollment($this->courseA, EnrollmentStatus::Assessed);
        foreach ([[$owing, 12000], [$settled, 0]] as [$enrollment, $balance]) {
            Studentassessments::create([
                'enrollmentId' => $enrollment->enrollmentId,
                'assessmentDate' => '2026-08-01',
                'totalAssessedAmount' => 18500,
                'totalScholarshipCoverage' => 0,
                'totalWaived' => $balance === 0 ? 18500 : 0,
                'remainingBalance' => $balance,
                'assessmentStatus' => 'pending',
            ]);
        }

        $staff = $this->unbounded();
        $badges = $this->badges($staff);
        $props = $this->props($staff, 'accounting.index');

        $this->assertSame(1, (int) $props['queueCounts']['due'], 'the desk sees one owing account');
        $this->assertSame(
            (int) $props['queueCounts']['due'],
            (int) $badges['accounting'],
            'the badge counted settled accounts the page puts on its other tab'
        );
    }

    #[Test]
    public function the_registrar_clinic_and_clearance_badges_match_their_own_pages(): void
    {
        $this->enrollment($this->courseA, EnrollmentStatus::Assessed);
        $this->enrollment($this->courseA, EnrollmentStatus::Paid);

        Admissions::create([
            'studentId' => $this->student('ADM')->studentId,
            'courseId' => $this->courseA->courseId,
            'termId' => $this->term->termId,
            'applicantType' => 'firstYear',
            'admissionStatus' => AdmissionStatus::Pending->value,
        ]);
        Admissions::create([
            'studentId' => $this->student('APR')->studentId,
            'courseId' => $this->courseA->courseId,
            'termId' => $this->term->termId,
            'applicantType' => 'firstYear',
            'admissionStatus' => AdmissionStatus::Approved->value,
        ]);

        $period = Clearanceperiods::create([
            'periodName' => 'First semester 2026-2027',
            'termId' => $this->term->termId,
            'clearanceStartDate' => '2026-11-01',
            'clearanceEndDate' => '2026-12-15',
            'periodStatus' => 'open',
        ]);
        foreach ([ClearanceOverallStatus::Pending, ClearanceOverallStatus::Pending, ClearanceOverallStatus::Approved] as $status) {
            Studentclearances::create([
                'studentId' => $this->student('CLR')->studentId,
                'clearancePeriodId' => $period->clearancePeriodId,
                'overallStatus' => $status->value,
                'academicStanding' => 'good',
                'studentType' => StudentType::FirstYear->value,
            ]);
        }

        $staff = $this->unbounded();
        $badges = $this->badges($staff);
        $registrar = $this->props($staff, 'registrar.index');
        $admission = $this->props($staff, 'admission.index');
        $clearance = $this->props($staff, 'clearance.index');

        $this->assertSame(2, (int) $registrar['enrollments']['total']);
        $this->assertSame((int) $registrar['enrollments']['total'], (int) $badges['registrar']);

        // The Admission card says "pending"; the page shows every status, so the number that must agree is the
        // page's own pending tile — the badge is a queue count, not a row count.
        $this->assertSame(1, (int) $admission['stats']['pending']);
        $this->assertSame((int) $admission['stats']['pending'], (int) $badges['admission']);

        $this->assertSame((int) $clearance['stats']['pending'], (int) $badges['clearance']);
    }

    #[Test]
    public function desks_without_a_college_filter_count_the_school_as_their_lists_do(): void
    {
        // These desks apply no CollegeScope to their own lists — the population is named in `DeskQueues`' own
        // docblock rather than counted here, because a number in a case name rots the day one more desk joins.
        // Their badges may not grow one either: a badge
        // bounded while the page underneath it is not is the §8 contradiction with the sign flipped. The account
        // here is therefore college-bound on purpose — that is the only shape that can tell the two sides apart.
        // Under this batch's first draft, which scoped the assessment badge by college, the badge answered 1 and
        // the list 2; the assertion below is the one that refuses such a pair from either direction.
        foreach ([$this->courseA, $this->courseB] as $course) {
            $evaluated = $this->enrollment($course, EnrollmentStatus::Evaluated);
            $assessed = $this->enrollment($course, EnrollmentStatus::Assessed);
            $paid = $this->enrollment($course, EnrollmentStatus::Paid);
            foreach ([[$evaluated, 18500], [$assessed, 12000], [$paid, 0]] as [$enrollment, $balance]) {
                Studentassessments::create([
                    'enrollmentId' => $enrollment->enrollmentId,
                    'assessmentDate' => '2026-08-01',
                    'totalAssessedAmount' => 18500,
                    'totalScholarshipCoverage' => 0,
                    'totalWaived' => $balance === 0 ? 18500 : 0,
                    'remainingBalance' => $balance,
                    'assessmentStatus' => 'pending',
                ]);
            }
        }

        $desk = Staffusers::factory()->create([
            'officeId' => 2, 'unitId' => $this->courseA->unitId,
            'employeeNo' => 'EMP-DQB-'.uniqid(), 'username' => 'dq_bound_'.uniqid(),
            'email' => 'dq_bound_'.uniqid().'@example.com',
        ]);
        $desk->givePermissionTo(['assessment.view', 'payment.view']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $badges = $this->badges($desk);
        $assessment = $this->props($desk, 'assessment.index');
        $accounting = $this->props($desk, 'accounting.index');

        // The list is school-wide (2 rows, two colleges) and the badge that opens it must say 2 as well.
        $this->assertSame(2, (int) $assessment['assessments']['total'], 'the assessment list is not college-bound');
        $this->assertSame(2, (int) $accounting['queueCounts']['due'], 'the accounting list is not college-bound');
        $this->assertSame((int) $assessment['assessments']['total'], (int) $badges['assessment']);
        $this->assertSame((int) $accounting['queueCounts']['due'], (int) $badges['accounting']);
    }

    #[Test]
    public function the_blocking_badge_counts_the_rows_its_page_lists_and_not_the_enrollments_waiting_at_that_box(): void
    {
        // Sheet item 9, ruled 2026-11-02: the card counts what its own page lists. The fixture is built so the
        // retired predicate answers a DIFFERENT number from the page — one enrollment whose first open box is the
        // Blocking office, beside two blocks — because an equality between two zeros would certify any badge at all.
        $staff = $this->unbounded();

        // `workflowsteps.officeId` is a real foreign key, and this class seeded no offices before — the eight other
        // cases never needed them, because none of them opens a workflow form. `firstOrCreate` because RbacSeeder
        // already carries some of these rows, and a fixture that re-inserts a seeded id fails on its key rather than
        // proving anything.
        foreach ([1 => 'Registrar', 2 => 'Accounting', 3 => 'Scholarship', 4 => 'Guidance',
            5 => 'Blocking', 11 => 'Clinic', 22 => 'ID Office'] as $officeId => $officeName) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => $officeName]);
        }

        $waiting = $this->enrollment($this->courseA, EnrollmentStatus::Enrolled);
        $form = app(WorkflowService::class)->createWorkflow($waiting);
        $steps = $form->workflowsteps()->get();
        $blockingStep = $steps->first(fn ($s) => (int) $s->officeId === OfficeId::Blocking->value);
        $this->assertNotNull($blockingStep, 'the workflow form holds no Blocking box, so this case has nothing to discriminate');
        // Every box ahead of Blocking is closed, which is what makes the waiting enrollment the retired predicate's
        // answer rather than an accident of insertion order.
        foreach ($steps->where('stepOrder', '<', $blockingStep->stepOrder) as $earlier) {
            $earlier->update(['stepStatus' => WorkflowStepStatus::Completed->value]);
        }
        $this->assertSame(1, DeskQueues::waitingAtFirstPendingBox(OfficeId::Blocking)->count(),
            'the number the badge used to print; if this is not 1 the fixture does not hold the state this case tests');

        foreach (['DQ-BLOCK-A', 'DQ-BLOCK-B'] as $offset => $name) {
            Blocks::create([
                'courseId' => $this->courseA->courseId, 'termId' => $this->term->termId,
                'yearLevel' => 1, 'blockName' => $name, 'maxStudents' => 20 + $offset,
            ]);
        }

        $badges = $this->badges($staff);
        $page = $this->props($staff, 'blocking.index');

        // Direction one: the card equals the figure the desk's own tile prints, and both name rows on its table.
        $this->assertSame(2, (int) $page['stats']['blocks'], 'two blocks are the whole list with no filter applied');
        $this->assertSame((int) $page['stats']['blocks'], (int) $badges['blocking'],
            'the card must be the number of rows the page underneath it lists (§8)');
        // Direction two: the retired subject is on the database and is provably not what the card prints.
        $this->assertNotSame((int) $badges['blocking'], 1,
            'the badge counts enrollments waiting at the Blocking box again, which is the reading this ruling retired');

        // And the tile arithmetic travels with the rows, so a badge built over a different population would show up
        // here as a card that agrees with nothing on the screen.
        $this->assertSame(41, (int) $page['stats']['capacity'], 'the desk sums the ceilings of the rows it lists');
    }
}
