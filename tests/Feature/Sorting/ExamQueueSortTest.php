<?php

namespace Tests\Feature\Sorting;

use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Examresults;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EnumColumnOrder;
use Tests\TestCase;

/**
 * Prompt §14's sorting control on the Examination desk's result queue — the tenth table wired (§28.3 G-40 stays
 * open on purpose: the reference-data and user-management screens still offer no order).
 *
 * Two columns this desk can see are deliberately NOT offered, and one case exists to say so rather than to pass:
 * Student Type and Standing are not `examresults` columns at all — an exam record is per (student, term) with no
 * `enrollmentId`, so the handler looks the pair up from the matching enrollment AFTER the page is cut
 * (`Enrollments::standingMapFor()` at the bottom of `index()`). A header over them would order rows the query never
 * joined, which is the same defect §28.3 owns for filters.
 *
 * Three columns are ENUM, and the school's ruling of 2026-10-31 gives them headers: the order the school's own
 * server applies is the declaration order written into the migration. The suite's `enum()` is a varchar with a
 * CHECK and orders the stored word, so each sequence is asked of the connection through `EnumColumnOrder` and never
 * typed here — the shape CI named on the clearance queue when this box was green.
 *
 * The fixture gives the seven orders seven different sequences (lane 88's rule: a target order that coincides with
 * the list's own default lets a mutant which ignores the requested column pass by accident), and one of the three
 * rows is still provisional, so the "Awaiting the Registrar" tile is a non-zero number a late join could have
 * disturbed had the tiles been cloned after the order instead of before it.
 */
class ExamQueueSortTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $desk;

    private Academicterms $term;

    private Courses $accountancy;

    private Courses $informationTechnology;

    private Courses $nursing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $unit = Academicunits::create(['unitName' => 'College of Computing', 'unitType' => 'college']);

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

        $this->accountancy = $this->course($unit->unitId, 'BSBA', 'Accountancy');
        $this->informationTechnology = $this->course($unit->unitId, 'BSIT', 'Information Technology');
        $this->nursing = $this->course($unit->unitId, 'BSN', 'Nursing');

        // SysAdmin takes the handler's first branch (`if ($canVerify)` — the desk that confirms a result must read
        // every row at either stage), so every case below judges the ORDER and not the ownership split, which
        // §13's own cases already pin.
        $staff = Staffusers::factory()->create([
            'officeId' => 4,
            'unitId' => null,
            'employeeNo' => 'EMP-EXAMSORT-'.uniqid(),
            'username' => 'exsort_'.uniqid(),
            'email' => 'exsort_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('SysAdmin');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->desk = $staff;
    }

    private function course(int $unitId, string $code, string $name): Courses
    {
        return Courses::create([
            'unitId' => $unitId,
            'courseCode' => $code,
            'courseName' => $name,
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);
    }

    private function student(string $lastName, string $schoolId): Students
    {
        return Students::create([
            'schoolIdNumber' => $schoolId,
            'lastName' => $lastName,
            'firstName' => 'Given',
            'middleName' => 'M',
            'suffix' => 'N/A',
            'gender' => 'female',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'email' => 'exsort_'.uniqid().'@example.com',
            'username' => 'exsort_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);
    }

    private function record(Courses $course, string $lastName, string $schoolId, string $date, string $examType, ?string $examResult, string $resultStatus): Examresults
    {
        return Examresults::create([
            'studentId' => $this->student($lastName, $schoolId)->studentId,
            'courseId' => $course->courseId,
            'termId' => $this->term->termId,
            'examStage' => ExamStage::Entrance,
            'examType' => $examType,
            'examResult' => $examResult,
            'examDate' => $date,
            'resultStatus' => $resultStatus,
        ]);
    }

    /**
     * Created Zulueta / EX-002 / Accountancy / 2026-07-05 / general / pass / official, then Bautista / EX-003 /
     * Nursing / 2026-07-01 / courseSpecific / fail / provisional, then Magsaysay / EX-001 / Information Technology /
     * 2026-07-03 / general / fail / official.
     */
    private function trio(): void
    {
        $this->record($this->accountancy, 'Zulueta', 'EX-002', '2026-07-05', ExamType::General->value, ExamResult::Pass->value, ExamStatus::Official->value);
        $this->record($this->nursing, 'Bautista', 'EX-003', '2026-07-01', ExamType::CourseSpecific->value, ExamResult::Fail->value, ExamStatus::Provisional->value);
        $this->record($this->informationTechnology, 'Magsaysay', 'EX-001', '2026-07-03', ExamType::General->value, ExamResult::Fail->value, ExamStatus::Official->value);
    }

    /** @return list<array<string, mixed>> */
    private function rows($response): array
    {
        return $response->viewData('page')['props']['exams']['data'];
    }

    /** @return list<string> */
    private function surnames($response): array
    {
        return array_map(fn (array $row): string => (string) ($row['student']['lastName'] ?? ''), $this->rows($response));
    }

    /** @return list<string> */
    private function schoolIds($response): array
    {
        return array_map(fn (array $row): string => (string) ($row['student']['schoolIdNumber'] ?? ''), $this->rows($response));
    }

    /** @return list<string> */
    private function courses($response): array
    {
        return array_map(fn (array $row): string => (string) ($row['course']['courseName'] ?? ''), $this->rows($response));
    }

    /** @return list<string> */
    private function dates($response): array
    {
        return array_map(fn (array $row): string => substr((string) $row['examDate'], 0, 10), $this->rows($response));
    }

    /** @return list<string> */
    private function column($response, string $key): array
    {
        return array_map(fn (array $row): string => (string) ($row[$key] ?? ''), $this->rows($response));
    }

    private function sort($response): array
    {
        return $response->viewData('page')['props']['sort'];
    }

    #[Test]
    public function the_queue_opens_newest_result_first_when_nothing_is_asked(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)->get(route('exam.index'));
        $response->assertOk();

        $this->assertSame(['Magsaysay', 'Bautista', 'Zulueta'], $this->surnames($response));
        $this->assertSame('examId', $this->sort($response)['column']);
        $this->assertSame('desc', $this->sort($response)['direction']);
    }

    #[Test]
    public function the_queue_can_be_ordered_by_the_students_surname_one_hop_away(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)
            ->get(route('exam.index', ['sort' => 'name', 'direction' => 'asc']));
        $response->assertOk();

        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($response));

        // Both tiles are cloned from the builder BEFORE this join exists. One of the three rows is still provisional
        // and one is a pass, so the 3 / 1 / 2 / 1 below are numbers the join could have disturbed had the order run
        // first. This screen answers four tiles and no rate — `passRate` belongs to the Results screen, and asking
        // this handler for it is the mistake this file keeps making about a page's own arithmetic.
        $stats = $response->viewData('page')['props']['stats'];
        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['pass']);
        $this->assertSame(2, $stats['fail']);
        $this->assertSame(1, $stats['pending']);
        $this->assertArrayNotHasKey('passRate', $stats, 'the result queue states counts; the rate is the Results screen\'s question');
    }

    #[Test]
    public function a_second_click_on_the_same_header_reverses_it(): void
    {
        $this->trio();

        $down = $this->actingAs($this->desk)
            ->get(route('exam.index', ['sort' => 'name', 'direction' => 'desc']));
        $down->assertOk();

        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($down));
        $this->assertSame('name', $this->sort($down)['column']);
        $this->assertSame('desc', $this->sort($down)['direction']);
    }

    #[Test]
    public function the_course_column_orders_the_program_and_not_the_student(): void
    {
        $this->trio();

        $up = $this->actingAs($this->desk)
            ->get(route('exam.index', ['sort' => 'course', 'direction' => 'asc']));
        $up->assertOk();
        $this->assertSame(['Accountancy', 'Information Technology', 'Nursing'], $this->courses($up));
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($up));

        $down = $this->actingAs($this->desk)
            ->get(route('exam.index', ['sort' => 'course', 'direction' => 'desc']));
        $down->assertOk();
        $this->assertSame(['Nursing', 'Information Technology', 'Accountancy'], $this->courses($down));
    }

    #[Test]
    public function the_school_number_column_orders_by_the_number_and_not_by_the_name(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)
            ->get(route('exam.index', ['sort' => 'schoolId', 'direction' => 'asc']));
        $response->assertOk();

        $this->assertSame(['EX-001', 'EX-002', 'EX-003'], $this->schoolIds($response));
        $this->assertSame(['Magsaysay', 'Zulueta', 'Bautista'], $this->surnames($response));
    }

    #[Test]
    public function the_date_column_orders_the_day_the_paper_was_recorded(): void
    {
        $this->trio();

        $up = $this->actingAs($this->desk)
            ->get(route('exam.index', ['sort' => 'examDate', 'direction' => 'asc']));
        $up->assertOk();
        $this->assertSame(['2026-07-01', '2026-07-03', '2026-07-05'], $this->dates($up));
        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($up));

        // Reversing it is the other reading of the same column, and a third click must not invent a fourth order.
        $down = $this->actingAs($this->desk)
            ->get(route('exam.index', ['sort' => 'examDate', 'direction' => 'desc']));
        $down->assertOk();
        $this->assertSame(array_reverse($this->dates($up)), $this->dates($down));
    }

    #[Test]
    public function the_enum_headers_order_the_words_this_connection_sorts_them_in(): void
    {
        $this->trio();

        $types = $this->actingAs($this->desk)
            ->get(route('exam.index', ['sort' => 'examType', 'direction' => 'asc']));
        $types->assertOk();
        $this->assertSame(
            EnumColumnOrder::ascending('examresults', 'examType', [
                ExamType::General->value,
                ExamType::General->value,
                ExamType::CourseSpecific->value,
            ]),
            $this->column($types, 'examType')
        );

        $results = $this->actingAs($this->desk)
            ->get(route('exam.index', ['sort' => 'examResult', 'direction' => 'asc']));
        $results->assertOk();
        $this->assertSame(
            EnumColumnOrder::ascending('examresults', 'examResult', [
                ExamResult::Pass->value,
                ExamResult::Fail->value,
                ExamResult::Fail->value,
            ]),
            $this->column($results, 'examResult')
        );

        $statuses = $this->actingAs($this->desk)
            ->get(route('exam.index', ['sort' => 'resultStatus', 'direction' => 'asc']));
        $statuses->assertOk();
        $this->assertSame(
            EnumColumnOrder::ascending('examresults', 'resultStatus', [
                ExamStatus::Official->value,
                ExamStatus::Provisional->value,
                ExamStatus::Official->value,
            ]),
            $this->column($statuses, 'resultStatus')
        );
    }

    #[Test]
    public function the_two_derived_columns_are_not_offered_because_this_query_never_joined_them(): void
    {
        $this->trio();

        $available = $this->sort($this->actingAs($this->desk)->get(route('exam.index')))['available'];

        // School ID, Name and Course reach one hop; Exam, Result, Registrar and Date are `examresults` columns of
        // their own. Student Type and Standing are the page's own lookups against the matching enrollment, done
        // AFTER the page is cut — the query holds nothing to order by, so no header is painted for them.
        $this->assertSame(
            ['schoolId', 'name', 'course', 'examType', 'examResult', 'resultStatus', 'examDate', 'examId'],
            $available
        );
        $this->assertNotContains('studentType', $available, 'studentType is read from the enrollment after the page is cut');
        $this->assertNotContains('academicStanding', $available, 'academicStanding is read from the enrollment after the page is cut');
    }

    #[Test]
    public function a_column_the_handler_never_listed_falls_back_and_never_reaches_the_query(): void
    {
        $this->trio();

        $asked = $this->actingAs($this->desk)
            ->get(route('exam.index', ['sort' => 'passwordHash', 'direction' => 'asc']));
        $asked->assertOk();

        $sort = $this->sort($asked);
        $this->assertSame('examId', $sort['column']);
        $this->assertNotContains('passwordHash', $sort['available']);

        $explicit = $this->actingAs($this->desk)
            ->get(route('exam.index', ['sort' => 'examId', 'direction' => 'asc']));
        $explicit->assertOk();
        $this->assertSame($this->schoolIds($explicit), $this->schoolIds($asked));
        $this->assertSame(['Zulueta', 'Bautista', 'Magsaysay'], $this->surnames($explicit));
    }

    #[Test]
    public function a_filter_and_an_order_are_both_echoed_so_neither_control_resets(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)
            ->get(route('exam.index', ['type' => ExamType::General->value, 'sort' => 'name', 'direction' => 'asc']));
        $props = $response->viewData('page')['props'];
        $response->assertOk();

        $this->assertSame(ExamType::General->value, $props['filters']['type']);
        $this->assertSame('name', $props['filters']['sort']);
        $this->assertSame('asc', $props['filters']['direction']);
        $this->assertSame('name', $this->sort($response)['column']);
        $this->assertSame(['Magsaysay', 'Zulueta'], $this->surnames($response));
        $this->assertSame(2, $props['stats']['total'], 'the tile describes the filtered set, not the two rows on screen by name');
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_not_passed_through(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)
            ->get(route('exam.index', ['sort' => 'name', 'direction' => 'asc; drop table students']));
        $response->assertOk();

        $this->assertSame('desc', $this->sort($response)['direction']);
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($response));
    }

    #[Test]
    public function the_queue_loads_the_student_for_the_identity_columns_and_nothing_else(): void
    {
        $this->trio();

        $plain = $this->actingAs($this->desk)->get(route('exam.index'));
        $ordered = $this->actingAs($this->desk)
            ->get(route('exam.index', ['sort' => 'name', 'direction' => 'asc']));
        $plain->assertOk();
        $ordered->assertOk();

        foreach ([[$plain, 'unsorted'], [$ordered, 'ordered by surname']] as [$response, $label]) {
            $row = $this->rows($response)[0] ?? null;
            $this->assertNotNull($row, "the {$label} examination queue answered no row, so the payload says nothing");

            $keys = array_keys($row['student'] ?? []);
            sort($keys);
            $this->assertSame(
                ['firstName', 'lastName', 'middleName', 'schoolIdNumber', 'studentId'],
                $keys,
                "the exam desk lists a name and a school number on the {$label} page; birthdate and contact number belong to the student record, not to this row"
            );
        }
    }

    #[Test]
    public function an_unscored_paper_is_neither_a_passer_nor_a_failure_and_the_screen_says_so(): void
    {
        // `examresults.examResult` is a nullable enum and the pass rate's denominator is the scored rows only, so a
        // fourth row with nothing marked must leave `scored` where it was. Recorded here because this batch orders
        // that column: an order over a nullable ENUM puts the absent paper at one end of the list, which is exactly
        // where a desk looks for the work nobody has marked yet.
        $this->trio();
        $this->record($this->nursing, 'Roxas', 'EX-004', '2026-07-02', ExamType::General->value, null, ExamStatus::Provisional->value);

        $orderedAsc = $this->actingAs($this->desk)
            ->get(route('exam.index', ['sort' => 'examResult', 'direction' => 'asc']));
        $orderedAsc->assertOk();
        $props = $orderedAsc->viewData('page')['props'];

        $this->assertSame(4, $props['stats']['total']);
        $this->assertSame(3, $props['stats']['pass'] + $props['stats']['fail']);

        // Both surfaces put a NULL first in an ascending order, so the unmarked paper is the row at the top of the
        // list rather than a silent third verdict — which is the whole reason `scored` is the rate's denominator.
        $values = $this->column($orderedAsc, 'examResult');
        $this->assertCount(4, $values);
        $this->assertSame('', $values[0], 'an unmarked paper must reach the top of the column, not read as a pass or a fail');
    }
}
