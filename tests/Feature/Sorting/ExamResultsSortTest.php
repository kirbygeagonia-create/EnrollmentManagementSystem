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
 * Prompt §14's sorting control on the Examination **results** screen — the second table on `ExamController`, and the
 * twelfth wired (§28.3 G-40 stays open on purpose).
 *
 * This action is the one the Registrar's row action links to: `index()` and `results()` are two different
 * populations of one table, and the row's own note is that `results()` honours `search` exactly as `index()` does
 * and echoes both keys (§28.3 G-15). Pinning an order here therefore has to survive the same filter contract, and
 * the screen is paginated at fifty rows rather than twenty — a tile that read the page would be visibly wrong here,
 * so `total` is asserted as the paginator's own count over the narrowed set, not as a row count.
 *
 * Every column the page paints is a column the query can reach: School ID and Name hop to `students`, Course hops
 * to `courses`, Exam / Result / Date are `examresults` columns. Nothing is withheld on this screen, which is the
 * finding: the pair that the roster withholds on the sibling page (Student Type, Standing) is not a column here at
 * all and the screen does not paint it either.
 *
 * `examResult` is a nullable ENUM, so one case seats an unmarked paper and asks the connection for the order —
 * MySQL reads an ENUM by its declaration index, the suite's varchar-with-CHECK reads the stored word, and NULL
 * reaches one end of either. `scored` is the pass rate's denominator, which is why the unscored row is a number
 * the rate must not count.
 */
class ExamResultsSortTest extends TestCase
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

        $staff = Staffusers::factory()->create([
            'officeId' => 4,
            'unitId' => null,
            'employeeNo' => 'EMP-EXRESSORT-'.uniqid(),
            'username' => 'exressort_'.uniqid(),
            'email' => 'exressort_'.uniqid().'@example.com',
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
            'email' => 'exressort_'.uniqid().'@example.com',
            'username' => 'exressort_'.uniqid(),
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
     * Created Zulueta / ER-002 / Accountancy / 2026-07-05 / general / pass / official, then Bautista / ER-003 /
     * Nursing / 2026-07-01 / courseSpecific / fail / provisional, then Magsaysay / ER-001 / Information Technology /
     * 2026-07-03 / general / fail / official.
     */
    private function trio(): void
    {
        $this->record($this->accountancy, 'Zulueta', 'ER-002', '2026-07-05', ExamType::General->value, ExamResult::Pass->value, ExamStatus::Official->value);
        $this->record($this->nursing, 'Bautista', 'ER-003', '2026-07-01', ExamType::CourseSpecific->value, ExamResult::Fail->value, ExamStatus::Provisional->value);
        $this->record($this->informationTechnology, 'Magsaysay', 'ER-001', '2026-07-03', ExamType::General->value, ExamResult::Fail->value, ExamStatus::Official->value);
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
    public function the_screen_opens_newest_result_first_when_nothing_is_asked(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)->get(route('exam.results'));
        $response->assertOk();

        $this->assertSame(['Magsaysay', 'Bautista', 'Zulueta'], $this->surnames($response));
        $this->assertSame('examId', $this->sort($response)['column']);
        $this->assertSame('desc', $this->sort($response)['direction']);
    }

    #[Test]
    public function the_screen_can_be_ordered_by_the_students_surname_one_hop_away(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)
            ->get(route('exam.results', ['sort' => 'name', 'direction' => 'asc']));
        $response->assertOk();

        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($response));

        // The aggregate and the pass rate are taken from clones of the builder BEFORE this join exists. One row is a
        // pass and two are failures, so 1 / 2 / 3 / 33 are numbers a late join could have disturbed — and the
        // denominator is scored rows, which all three are.
        $stats = $response->viewData('page')['props']['stats'];
        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['pass']);
        $this->assertSame(2, $stats['fail']);
        $this->assertSame(3, $stats['scored']);
        $this->assertSame(33, $stats['passRate']);
    }

    #[Test]
    public function a_second_click_on_the_same_header_reverses_it(): void
    {
        $this->trio();

        $down = $this->actingAs($this->desk)
            ->get(route('exam.results', ['sort' => 'name', 'direction' => 'desc']));
        $down->assertOk();

        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($down));
        $this->assertSame('name', $this->sort($down)['column']);
    }

    #[Test]
    public function the_course_column_orders_the_program_and_not_the_student(): void
    {
        $this->trio();

        $up = $this->actingAs($this->desk)
            ->get(route('exam.results', ['sort' => 'course', 'direction' => 'asc']));
        $up->assertOk();
        $this->assertSame(['Accountancy', 'Information Technology', 'Nursing'], $this->courses($up));
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($up));

        $down = $this->actingAs($this->desk)
            ->get(route('exam.results', ['sort' => 'course', 'direction' => 'desc']));
        $down->assertOk();
        $this->assertSame(['Nursing', 'Information Technology', 'Accountancy'], $this->courses($down));
    }

    #[Test]
    public function the_school_number_column_orders_by_the_number_and_not_by_the_name(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)
            ->get(route('exam.results', ['sort' => 'schoolId', 'direction' => 'asc']));
        $response->assertOk();

        $this->assertSame(['ER-001', 'ER-002', 'ER-003'], $this->schoolIds($response));
        $this->assertSame(['Magsaysay', 'Zulueta', 'Bautista'], $this->surnames($response));
    }

    #[Test]
    public function the_date_column_orders_the_day_the_paper_was_recorded(): void
    {
        $this->trio();

        $up = $this->actingAs($this->desk)
            ->get(route('exam.results', ['sort' => 'examDate', 'direction' => 'asc']));
        $up->assertOk();
        $this->assertSame(['2026-07-01', '2026-07-03', '2026-07-05'], $this->dates($up));

        $down = $this->actingAs($this->desk)
            ->get(route('exam.results', ['sort' => 'examDate', 'direction' => 'desc']));
        $down->assertOk();
        $this->assertSame(array_reverse($this->dates($up)), $this->dates($down));
    }

    #[Test]
    public function the_enum_headers_order_the_words_this_connection_sorts_them_in(): void
    {
        $this->trio();

        $types = $this->actingAs($this->desk)
            ->get(route('exam.results', ['sort' => 'examType', 'direction' => 'asc']));
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
            ->get(route('exam.results', ['sort' => 'examResult', 'direction' => 'asc']));
        $results->assertOk();
        $this->assertSame(
            EnumColumnOrder::ascending('examresults', 'examResult', [
                ExamResult::Pass->value,
                ExamResult::Fail->value,
                ExamResult::Fail->value,
            ]),
            $this->column($results, 'examResult')
        );

        $reversed = $this->actingAs($this->desk)
            ->get(route('exam.results', ['sort' => 'examType', 'direction' => 'desc']));
        $reversed->assertOk();
        $this->assertSame(
            array_reverse($this->column($types, 'examType')),
            $this->column($reversed, 'examType')
        );
    }

    #[Test]
    public function an_unmarked_paper_is_not_counted_in_the_rate_and_reaches_the_top_of_the_order(): void
    {
        $this->trio();
        $this->record($this->nursing, 'Roxas', 'ER-004', '2026-07-02', ExamType::General->value, null, ExamStatus::Provisional->value);

        $response = $this->actingAs($this->desk)
            ->get(route('exam.results', ['sort' => 'examResult', 'direction' => 'asc']));
        $response->assertOk();
        $props = $response->viewData('page')['props'];

        // Four rows on the screen, three verdicts: the rate's denominator stayed `scored`, and the absent mark is
        // the first row of the ascending order on both drivers rather than a silent third verdict.
        $this->assertSame(4, $props['stats']['total']);
        $this->assertSame(3, $props['stats']['scored']);
        $this->assertSame(33, $props['stats']['passRate']);
        $values = $this->column($response, 'examResult');
        $this->assertCount(4, $values);
        $this->assertSame('', $values[0]);
    }

    #[Test]
    public function every_column_the_screen_paints_is_offered_and_nothing_else_is(): void
    {
        $this->trio();

        $available = $this->sort($this->actingAs($this->desk)->get(route('exam.results')))['available'];

        // Six painted columns plus the screen's own key. Unlike the roster, this page paints no derived pair, so no
        // column is withheld here — a claim about the set, which is why the case states it in full.
        $this->assertSame(
            ['schoolId', 'name', 'course', 'examType', 'examResult', 'examDate', 'examId'],
            $available
        );
        $this->assertNotContains('passwordHash', $available);
    }

    #[Test]
    public function a_column_the_handler_never_listed_falls_back_and_never_reaches_the_query(): void
    {
        $this->trio();

        $asked = $this->actingAs($this->desk)
            ->get(route('exam.results', ['sort' => 'birthdate', 'direction' => 'asc']));
        $asked->assertOk();

        $sort = $this->sort($asked);
        $this->assertSame('examId', $sort['column']);
        $this->assertNotContains('birthdate', $sort['available']);

        $explicit = $this->actingAs($this->desk)
            ->get(route('exam.results', ['sort' => 'examId', 'direction' => 'asc']));
        $explicit->assertOk();
        $this->assertSame($this->schoolIds($explicit), $this->schoolIds($asked));
        $this->assertSame(['Zulueta', 'Bautista', 'Magsaysay'], $this->surnames($explicit));
    }

    #[Test]
    public function a_result_filter_and_an_order_are_both_echoed_so_neither_control_resets(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)
            ->get(route('exam.results', ['result' => ExamResult::Fail->value, 'sort' => 'name', 'direction' => 'asc']));
        $props = $response->viewData('page')['props'];
        $response->assertOk();

        $this->assertSame(ExamResult::Fail->value, $props['filters']['result']);
        $this->assertSame('name', $props['filters']['sort']);
        $this->assertSame('asc', $props['filters']['direction']);
        $this->assertSame('name', $this->sort($response)['column']);
        $this->assertSame(['Bautista', 'Magsaysay'], $this->surnames($response));

        // The row action that links here arrives with the student's School ID in `search`, which is the reason this
        // handler honours the same three matches the roster does — and the order still arranges what it narrows to.
        $one = $this->actingAs($this->desk)
            ->get(route('exam.results', ['search' => 'ER-003', 'sort' => 'examDate', 'direction' => 'desc']));
        $one->assertOk();
        $this->assertSame(['Bautista'], $this->surnames($one));
        $this->assertSame('2026-07-01', $this->dates($one)[0]);
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_not_passed_through(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)
            ->get(route('exam.results', ['sort' => 'name', 'direction' => 'asc; drop table examresults']));
        $response->assertOk();

        $this->assertSame('desc', $this->sort($response)['direction']);
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($response));
    }

    #[Test]
    public function the_screen_loads_the_student_for_the_identity_columns_and_nothing_else(): void
    {
        $this->trio();

        $plain = $this->actingAs($this->desk)->get(route('exam.results'));
        $ordered = $this->actingAs($this->desk)
            ->get(route('exam.results', ['sort' => 'name', 'direction' => 'asc']));
        $plain->assertOk();
        $ordered->assertOk();

        foreach ([[$plain, 'unsorted'], [$ordered, 'ordered by surname']] as [$response, $label]) {
            $row = $this->rows($response)[0] ?? null;
            $this->assertNotNull($row, "the {$label} results screen answered no row, so the payload says nothing");

            $keys = array_keys($row['student'] ?? []);
            sort($keys);
            $this->assertSame(
                ['firstName', 'lastName', 'middleName', 'schoolIdNumber', 'studentId'],
                $keys,
                "the results screen lists a name and a school number on the {$label} page; birthdate is the student's record, not this row's"
            );
        }
    }
}
