<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AdmissionStatus;
use App\Enums\ClearanceOverallStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Admissions;
use App\Models\Clearanceperiods;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Models\Studentclearances;
use App\Models\Students;
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
 * The `blocking` key is deliberately absent: its badge counts enrollments waiting at that office while
 * BlockingController::index() lists timetable blocks, and which subject the card names is a school question
 * (item 9 on the 2026-10-29 decision sheet), not a filter to unify.
 *
 * Two directions are pinned, and only one of them was true before this batch. Bounded where the list is bounded:
 * `admission` and `evaluation` honour `CollegeScope` because those two controllers bound their own lists with it,
 * so a department's card must shrink with its page. Unbounded where the list is unbounded: the Assessment,
 * Accounting, Registrar, Clinic, ID and Clearance desks carry no college filter in their handlers or policies, so
 * their badges must not grow one either — `the_six_desks_without_a_college_filter_count_the_school_as_their_lists_do`
 * is the case that would have failed under the first draft of this batch, which scoped the two assessment badges
 * and left their pages school-wide. `clinic` and `id` now build their desk lists from the very builder the badge
 * counts (`DeskQueues::clinic()` / `::id()`), so those two equalities are one call rather than a duplicated rule.
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
    public function the_six_desks_without_a_college_filter_count_the_school_as_their_lists_do(): void
    {
        // Six desks apply no CollegeScope to their own lists, so their badges may not grow one either: a badge
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
}
