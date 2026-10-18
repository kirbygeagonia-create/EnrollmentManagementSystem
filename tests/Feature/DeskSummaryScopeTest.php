<?php

namespace Tests\Feature;

use App\Enums\ClearanceOverallStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamType;
use App\Enums\StaffRole;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Admissions;
use App\Models\Auditlogs;
use App\Models\Blocks;
use App\Models\Clearanceperiods;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Examresults;
use App\Models\Offices;
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
 * Lane 47 — a summary tile must describe the set its label claims.
 *
 * Every desk screen puts numbers above its list, and the numbers were produced four different ways: counts
 * over the whole filtered set, the paginator's total, a reduce over the 20 rows on the page, and a predicate
 * the list query can never return. Two of those are silent on page 1, which is the only page a demo reaches.
 *
 * The page-2 cases exist because `paginate()` mutates the builder it is called on — forPage() sets limit and
 * offset on $this (vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php:3208) — so an aggregate
 * taken from `(clone $query)` afterwards inherits "LIMIT 20 OFFSET 20" and answers with nothing. The three
 * desks that did that each carried a comment promising "the full queue, not just the current page".
 *
 * SysAdmin is used deliberately: `Gate::before` grants it everything, so these cases measure the arithmetic of
 * the tiles and not who is allowed to open the screen.
 */
class DeskSummaryScopeTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $desk;

    private Academicterms $term;

    private Courses $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        foreach ([1, 2, 3, 4, 5, 11, 22] as $officeId) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => 'Office '.$officeId]);
        }

        // A student row needs a religion on MySQL, where the foreign key is enforced — and on SQLite the
        // counter is whatever the suite left behind, so the row is written by explicit id.
        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $year = Academicyears::create([
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-03-31',
        ]);

        $this->term = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);

        $unit = Academicunits::create([
            'unitName' => 'College of Computer Studies',
            'unitType' => 'college',
        ]);

        $this->course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        $staff = Staffusers::factory()->create([
            'officeId' => 1,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-LANE47-'.uniqid(),
            'username' => 'lane47_'.uniqid(),
            'email' => 'lane47_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('SysAdmin');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->desk = $staff;
    }

    private function student(string $tag): Students
    {
        return Students::create([
            'schoolIdNumber' => $tag.'-'.uniqid().random_int(1000, 9999),
            'lastName' => 'Surname'.$tag,
            'firstName' => 'Given',
            'middleName' => 'M',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'username' => strtolower($tag).'_'.uniqid().random_int(1000, 9999),
            'passwordHash' => bcrypt('password123'),
            'email' => strtolower($tag).'_'.uniqid().'@example.com',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function the_clearance_tiles_still_describe_the_queue_on_the_second_page(): void
    {
        $period = Clearanceperiods::create([
            'termId' => $this->term->termId,
            'clearanceStartDate' => '2026-09-01',
            'clearanceEndDate' => '2026-10-31',
            'periodStatus' => 'open',
        ]);

        // 25 slips: one more than the page, so the desk has a second page to stand on.
        foreach (range(1, 25) as $n) {
            Studentclearances::create([
                'studentId' => $this->student('CLR'.$n)->studentId,
                'clearancePeriodId' => $period->clearancePeriodId,
                'overallStatus' => $n % 5 === 0 ? ClearanceOverallStatus::Approved : ClearanceOverallStatus::Pending,
            ]);
        }

        $this->assertSame(25, Studentclearances::count());

        $this->actingAs($this->desk)->get(route('clearance.index', ['page' => 2]))
            ->assertInertia(fn ($page) => $page
                ->where('clearances.current_page', 2)
                // Before the fix these read 0: the GROUP BY carried "LIMIT 20 OFFSET 20" and every status
                // row fell past the offset.
                ->where('stats.pending', 20)
                ->where('stats.approved', 5));
    }

    #[Test]
    public function the_admission_tiles_still_describe_the_queue_on_the_second_page(): void
    {
        foreach (range(1, 25) as $n) {
            Admissions::create([
                'studentId' => $this->student('ADM'.$n)->studentId,
                'courseId' => $this->course->courseId,
                'termId' => $this->term->termId,
                'applicantType' => 'firstYear',
                'admissionStatus' => $n % 4 === 0 ? 'approved' : 'pending',
            ]);
        }

        $this->assertSame(25, Admissions::count());

        $this->actingAs($this->desk)->get(route('admission.index', ['page' => 2]))
            ->assertInertia(fn ($page) => $page
                ->where('admissions.current_page', 2)
                // `total` is the sum of the status counts, so an empty aggregate read "0 applications" to a
                // desk that had 25 on file.
                ->where('stats.total', 25)
                ->where('stats.pending', 19)
                ->where('stats.approved', 6));
    }

    #[Test]
    public function the_assessment_desk_keeps_its_money_totals_when_the_desk_pages(): void
    {
        foreach (range(1, 25) as $n) {
            $enrollment = Enrollments::create([
                'studentId' => $this->student('ASO'.$n)->studentId,
                'courseId' => $this->course->courseId,
                'termId' => $this->term->termId,
                'yearLevel' => 1,
                'studentType' => StudentType::FirstYear,
                'enrollmentType' => 'new',
                'enrollmentStatus' => EnrollmentStatus::Evaluated,
                'evaluatedBy' => $this->desk->userId,
            ]);

            Studentassessments::create([
                'enrollmentId' => $enrollment->enrollmentId,
                'totalAssessedAmount' => 1000,
                'totalScholarshipCoverage' => 0,
                'totalWaived' => 0,
                'remainingBalance' => $n % 2 === 0 ? 0 : 500,
                'assessmentDate' => '2026-08-01',
            ]);
        }

        $this->actingAs($this->desk)->get(route('assessment.index', ['page' => 2]))
            ->assertInertia(fn ($page) => $page
                ->where('assessments.current_page', 2)
                // The aggregate was `->first()` over a builder with OFFSET 20, so it returned no row at all
                // and the desk read ₱0.00 for ₱25,000 of assessments.
                ->where('summary.total', 25000)
                ->where('summary.balance', 6500)
                ->where('summary.settledCount', 12));
    }

    #[Test]
    public function the_blocking_tiles_count_the_filtered_set_not_the_rows_on_the_page(): void
    {
        foreach (range(1, 25) as $n) {
            Blocks::create([
                'courseId' => $this->course->courseId,
                'termId' => $this->term->termId,
                'yearLevel' => $n % 2 === 0 ? 2 : 1,
                'blockName' => 'BLK-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                'maxStudents' => 40,
            ]);
        }

        $this->actingAs($this->desk)->get(route('blocking.index'))
            ->assertInertia(fn ($page) => $page
                // 25 blocks at 40 places each. The page shows 20, which is what this screen used to add up.
                ->where('stats.blocks', 25)
                ->where('stats.capacity', 1000)
                ->where('stats.available', 1000));

        // And the tiles track a filter, because they are taken from the same query as the list.
        $this->actingAs($this->desk)->get(route('blocking.index', ['yearLevel' => 1]))
            ->assertInertia(fn ($page) => $page
                ->where('stats.blocks', 13)
                ->where('stats.capacity', 520));
    }

    #[Test]
    public function the_pass_rate_excludes_unscored_rows_and_does_not_move_with_the_page(): void
    {
        // 60 papers: 40 scored (30 pass, 10 fail) and 20 not yet marked. examResult is a nullable enum, so
        // an unmarked paper is not a failure — counting it made the rate say 50% instead of 75%.
        foreach (range(1, 60) as $n) {
            Examresults::create([
                'studentId' => $this->student('EXM'.$n)->studentId,
                'courseId' => $this->course->courseId,
                'termId' => $this->term->termId,
                'examStage' => ExamStage::Entrance,
                'examType' => ExamType::General,
                'examResult' => $n <= 30 ? ExamResult::Pass : ($n <= 40 ? ExamResult::Fail : null),
                'examDate' => '2026-06-01',
            ]);
        }

        $this->actingAs($this->desk)->get(route('exam.results'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.pass', 30)
                ->where('stats.fail', 10)
                ->where('stats.scored', 40)
                ->where('stats.passRate', 75)
                ->where('stats.total', 60));

        $this->actingAs($this->desk)->get(route('exam.results', ['page' => 2]))
            ->assertInertia(fn ($page) => $page
                ->where('exams.current_page', 2)
                ->where('stats.passRate', 75));
    }

    #[Test]
    public function the_evaluation_desk_reports_the_work_that_left_it_not_only_the_rows_on_screen(): void
    {
        foreach (range(1, 3) as $n) {
            Enrollments::create([
                'studentId' => $this->student('EVL'.$n)->studentId,
                'courseId' => $this->course->courseId,
                'termId' => $this->term->termId,
                'yearLevel' => 2,
                'studentType' => StudentType::Transferee,
                'enrollmentType' => 'new',
                'enrollmentStatus' => EnrollmentStatus::Pending,
                'evaluatedBy' => $this->desk->userId,
            ]);
        }

        Enrollments::create([
            'studentId' => $this->student('EVR')->studentId,
            'courseId' => $this->course->courseId,
            'termId' => $this->term->termId,
            'yearLevel' => 1,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => 'new',
            'enrollmentStatus' => EnrollmentStatus::ReturnedToEvaluation,
            'evaluatedBy' => $this->desk->userId,
        ]);

        // Four records this desk has already signed and sent on. They are not in the queue, so the tile used
        // to read 0 while it claimed to count them — and no row the list can return is `evaluated`.
        foreach (range(1, 4) as $n) {
            Enrollments::create([
                'studentId' => $this->student('EVD'.$n)->studentId,
                'courseId' => $this->course->courseId,
                'termId' => $this->term->termId,
                'yearLevel' => 1,
                'studentType' => StudentType::Continuing,
                'enrollmentType' => 'new',
                'enrollmentStatus' => EnrollmentStatus::Evaluated,
                'evaluatedBy' => $this->desk->userId,
            ]);
        }

        $this->actingAs($this->desk)->get(route('evaluation.index'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.total', 4)
                ->where('stats.pending', 3)
                ->where('stats.returned', 1)
                ->where('stats.transferees', 3)
                ->where('stats.evaluated', 4)
                // The list itself still holds only the queue: the completed records are counted, not listed.
                ->has('enrollments.data', 4)
                ->where('enrollments.data.0.enrollmentStatus', 'returnedToEvaluation'));
    }

    #[Test]
    public function the_audit_log_can_be_filtered_for_an_entity_that_only_appears_past_page_one(): void
    {
        // 60 rows on one entity (59 created, the last deleted) plus one row on an entity that exists nowhere
        // else and is older than the first page of 50 — precisely the value a page-derived dropdown cannot name.
        foreach (range(1, 60) as $n) {
            Auditlogs::create([
                'userId' => $this->desk->userId,
                'action' => $n === 60 ? 'deleted' : 'created',
                'entityTable' => 'lane47bulk',
                'entityId' => $n,
                'createdAt' => '2026-09-01 00:'.str_pad((string) $n, 2, '0', STR_PAD_LEFT).':00',
            ]);
        }

        Auditlogs::create([
            'userId' => $this->desk->userId,
            'action' => 'created',
            'entityTable' => 'lane47late',
            'entityId' => 1,
            'createdAt' => '2020-01-01 00:00:00',
        ]);

        $response = $this->actingAs($this->desk)->get(route('admin.users.audit-logs'));

        $tables = collect($response->viewData('page')['props']['entityTables']);

        $this->assertTrue($tables->contains('lane47late'), 'the Entity list must name every table the log holds');
        $this->assertTrue($tables->contains('lane47bulk'));
        // Observers write their own audit rows, so the list is judged against the table, not a literal count.
        $this->assertSame($tables->count(), Auditlogs::distinct()->count('entityTable'));

        // And choosing the value the dropdown used to hide actually narrows the list.
        $this->actingAs($this->desk)->get(route('admin.users.audit-logs', ['entityTable' => 'lane47late']))
            ->assertInertia(fn ($page) => $page
                ->where('logs.total', 1)
                ->where('stats.total', 1)
                ->where('filters.entityTable', 'lane47late'));

        // The tiles count the whole filtered log: one page holds 50 of these 60, so a page-derived "Deleted"
        // could not have seen the only deletion, and page 2 would have shown none of the creations.
        $this->actingAs($this->desk)->get(route('admin.users.audit-logs', ['entityTable' => 'lane47bulk']))
            ->assertInertia(fn ($page) => $page
                ->where('logs.total', 60)
                ->where('stats.total', 60)
                ->where('stats.created', 59)
                ->where('stats.deleted', 1));

        $this->actingAs($this->desk)->get(route('admin.users.audit-logs', ['entityTable' => 'lane47bulk', 'page' => 2]))
            ->assertInertia(fn ($page) => $page
                ->where('logs.current_page', 2)
                ->where('stats.created', 59)
                ->where('stats.deleted', 1));
    }
}
