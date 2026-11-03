<?php

namespace Tests\Feature\Admin;

use App\Models\Academicterms;
use App\Models\Academicyears;
use App\Models\Staffusers;
use Carbon\CarbonImmutable;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EnumColumnOrder;
use Tests\TestCase;

/**
 * Prompt §14's sorting control, on the academic term list — the twenty-eighth table wired to
 * `App\Support\ListSort` (§28.3 G-40; the owed list is printed by `lane140-measure-wired-screens.php` and
 * cross-checked page for page by `lane215-plan-remaining-screens.php`, never counted from memory).
 *
 * Three things make this screen its own case rather than a copy of the terms-adjacent catalogues.
 *
 * The School Year header is a hop, and INNER is earned by asking both the schema and the rows: `academicYearId` is
 * NOT NULL with a foreign key and live `ems` answers 9 terms, 0 nulls and 0 orphans.
 *
 * The Semester header is an ENUM, so its sequence belongs to the connection rather than to this file — MySQL orders
 * `1st, 2nd, Summer` by declaration and the suite's varchar-with-CHECK sorts the word. `EnumColumnOrder` is the one
 * parse that answers it.
 *
 * The Status column is refused a header because it is not a column at all: `academicterms` holds no status, and the
 * cell computes Active/Inactive from the row's own dates against the school's civil day. A header there would
 * promise an order the query cannot answer, which is the same reason the block roster refuses its Term label.
 */
class TermListSortTest extends TestCase
{
    use RefreshDatabase;

    private const SEMESTERS = ['1st', '2nd', 'Summer'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function year(string $label, ?string $start = null, ?string $end = null): Academicyears
    {
        return Academicyears::create([
            'yearLabel' => $label,
            'startDate' => $start ?? '2000-06-01',
            'endDate' => $end ?? '2001-03-31',
        ]);
    }

    private function term(int $yearId, string $semester, string $start, string $end): Academicterms
    {
        return Academicterms::create([
            'academicYearId' => $yearId,
            'semester' => $semester,
            'startDate' => $start,
            'endDate' => $end,
        ]);
    }

    private function admin(): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => 7,
            'employeeNo' => 'EMP-TERM-'.uniqid(),
            'username' => 'term_'.uniqid(),
            'email' => 'term_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('SysAdmin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    /** @return array<string, mixed> */
    private function props(array $query = []): array
    {
        $response = $this->actingAs($this->admin())->get(route('admin.reference-data.terms', $query));
        // A refusal renders as a page too, so every comparison case asks for a 200 before it reads a sequence.
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    /** @return list<string> */
    private function years(array $props): array
    {
        return array_map(static fn (array $r): string => (string) ($r['academicYear']['yearLabel'] ?? ''), $props['terms']['data']);
    }

    /** @return list<string> */
    private function semesters(array $props): array
    {
        return array_map(static fn (array $r): string => (string) $r['semester'], $props['terms']['data']);
    }

    #[Test]
    public function the_list_opens_with_the_school_years_reading_oldest_first(): void
    {
        $y26 = $this->year('2026-2027');
        $y24 = $this->year('2024-2025');
        $y25 = $this->year('2025-2026');
        // Created newest-year-first so the retired `termId` descending order is the REVERSE of what the list now
        // opens with: a mutant that puts the old default back cannot satisfy this by accident.
        $this->term($y26->academicYearId, '1st', '2026-06-01', '2026-10-31');
        $this->term($y24->academicYearId, '1st', '2024-06-01', '2024-10-31');
        $this->term($y25->academicYearId, '1st', '2025-06-01', '2025-10-31');

        $props = $this->props();

        $this->assertCount(3, $props['terms']['data'], 'the fixture is not the whole population this claim is about');
        $this->assertSame('academicYear', $props['sort']['column'], 'the page was not told which order was applied');
        $this->assertSame('asc', $props['sort']['direction'], 'a list with no direction asked for opens ascending');
        $this->assertSame(['2024-2025', '2025-2026', '2026-2027'], $this->years($props),
            'the term list did not open with the oldest school year first');
    }

    #[Test]
    public function the_school_year_header_orders_through_the_hop_and_adds_nothing_to_the_row(): void
    {
        $this->term($this->year('2025-2026')->academicYearId, '1st', '2025-06-01', '2025-10-31');
        $this->term($this->year('2023-2024')->academicYearId, '1st', '2023-06-01', '2023-10-31');
        $this->term($this->year('2024-2025')->academicYearId, '1st', '2024-06-01', '2024-10-31');

        $ascending = $this->props(['sort' => 'academicYear', 'direction' => 'asc']);
        $descending = $this->props(['sort' => 'academicYear', 'direction' => 'desc']);

        $this->assertSame(['2023-2024', '2024-2025', '2025-2026'], $this->years($ascending),
            'the School Year header did not order by the label one hop away');
        $this->assertSame(['2025-2026', '2024-2025', '2023-2024'], $this->years($descending),
            'the second click did not reverse the hop');

        // §28.3 G-51: an unpinned builder emits `select *`, and once `academicyears` is joined that star covers the
        // year too, so the desk row would change shape depending on which header was clicked.
        $plainKeys = array_keys($this->props()['terms']['data'][0]);
        $joinedKeys = array_keys($ascending['terms']['data'][0]);
        sort($plainKeys);
        sort($joinedKeys);
        $this->assertSame($plainKeys, $joinedKeys,
            'ordering by School Year widened the row: '.implode(',', array_diff($joinedKeys, $plainKeys)).' arrived only with the hop');
        $this->assertArrayNotHasKey('yearLabel', $ascending['terms']['data'][0],
            'the hop leaked its own column onto the term row instead of staying inside the relation');
    }

    #[Test]
    public function the_semester_header_answers_the_order_this_connection_holds_for_the_enum(): void
    {
        $year = $this->year('2026-2027');
        // One term per declared value, inserted so neither id order nor the alphabetical reading is assumed correct:
        // the expectation is asked of the connection, which is the whole point of the owner's ruling.
        $this->term($year->academicYearId, 'Summer', '2027-06-01', '2027-10-31');
        $this->term($year->academicYearId, '1st', '2026-06-01', '2026-10-31');
        $this->term($year->academicYearId, '2nd', '2026-11-01', '2027-03-31');

        $expected = EnumColumnOrder::ascending('academicterms', 'semester', self::SEMESTERS);

        $ascending = $this->props(['sort' => 'semester', 'direction' => 'asc']);

        $this->assertSame($expected, $this->semesters($ascending),
            'the Semester header did not answer the order this connection holds for the column');
        $this->assertSame('semester', $ascending['sort']['column']);
    }

    #[Test]
    public function the_date_headers_order_by_the_stored_days(): void
    {
        $year = $this->year('2026-2027');
        $this->term($year->academicYearId, '1st', '2026-06-01', '2026-10-31');
        $this->term($year->academicYearId, '2nd', '2026-11-01', '2027-03-31');
        $this->term($year->academicYearId, 'Summer', '2027-06-01', '2027-10-31');

        $byStart = $this->props(['sort' => 'startDate', 'direction' => 'asc']);
        $byEndDesc = $this->props(['sort' => 'endDate', 'direction' => 'desc']);

        $this->assertSame(['1st', '2nd', 'Summer'], $this->semesters($byStart),
            'Start Date was not ordered by the stored day');
        $this->assertSame(['Summer', '2nd', '1st'], $this->semesters($byEndDesc),
            'End Date descending did not reverse the days');
    }

    #[Test]
    public function the_derived_status_column_is_not_offered_and_the_four_real_ones_are(): void
    {
        $this->term($this->year('2026-2027')->academicYearId, '1st', '2026-06-01', '2026-10-31');

        $available = $this->props()['sort']['available'];
        $sorted = $available;
        sort($sorted);

        // The named absents lead, so a plant that offers one of them fails on its own claim rather than on the set
        // arithmetic beside it.
        $this->assertNotContains('status', $available,
            'a header was offered for a value the screen computes from the row dates — academicterms holds no status column');
        $this->assertNotContains('termId', $available, 'the record key became an order on a screen that paints no id');
        $this->assertNotContains('academicYearId', $available, 'the raw foreign key was offered beside the year label the desk reads');
        $this->assertSame(['academicYear', 'endDate', 'semester', 'startDate'], $sorted,
            'an order was offered that the query cannot answer, or a painted column lost its header');
    }

    #[Test]
    public function the_day_filter_names_its_own_table_so_the_join_a_header_adds_cannot_answer_it(): void
    {
        // `academicyears` holds `startDate` and `endDate` too, and the School Year header joins that very table, so an
        // unqualified `where('startDate', …)` names two columns at once. Both drivers refuse it: live `ems` answered
        // 500 on MySQL for the Active tab beside a fallback order, and this suite's SQLite says "ambiguous column
        // name: startDate". The case therefore fails at `assertOk()` before it reads any sequence — the promise is
        // that a tab and a header can sit on one request at all. The two windows are set to disagree anyway, because
        // a driver that ever resolved the name silently would resolve it to the SCHOOL YEAR's window while the desk
        // reads the term's, and that population error is worse than the 500.
        $year = $this->year('2026-2027', '2026-01-01', '2027-12-31');
        $closed = $this->term($year->academicYearId, '1st', '2024-06-01', '2024-10-31');
        $open = $this->term($year->academicYearId, '2nd', '2026-06-01', '2026-10-31');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-15 04:00:00', 'UTC'));

        try {
            $active = $this->props(['status' => 'active', 'sort' => 'academicYear', 'direction' => 'asc']);
            $inactive = $this->props(['status' => 'inactive', 'sort' => 'academicYear', 'direction' => 'asc']);
        } finally {
            CarbonImmutable::setTestNow();
        }

        $activeIds = array_map(static fn (array $r): int => (int) $r['termId'], $active['terms']['data']);
        $this->assertContains($open->termId, $activeIds, 'the term the school is inside must survive the hop');
        $this->assertNotContains($closed->termId, $activeIds,
            'a term whose own window closed stayed on the Active list, which is the filter reading the school year instead');

        $inactiveIds = array_map(static fn (array $r): int => (int) $r['termId'], $inactive['terms']['data']);
        $this->assertContains($closed->termId, $inactiveIds, 'the Inactive tab is the same question inverted');
        $this->assertNotContains($open->termId, $inactiveIds, 'the term inside the school’s day belongs to neither tab');
    }

    #[Test]
    public function every_header_the_page_paints_is_a_key_the_action_obeys(): void
    {
        $available = $this->props()['sort']['available'];
        $jsx = (string) file_get_contents(base_path('resources/js/Pages/Admin/ReferenceData/Terms.jsx'));

        $found = preg_match_all("/sortKey: '([A-Za-z]+)'/", $jsx, $m) >= 1 ? $m[1] : [];

        $this->assertNotSame([], $found, 'the page lists no sortable header at all, so this case would prove nothing');
        foreach ($found as $key) {
            $this->assertContains($key, $available, "the page offers a header for [{$key}] and the action would not obey it");
        }
        $this->assertCount(4, $found, 'a painted column lost its header, so the desk cannot order what it can see');
        $this->assertStringNotContainsString("sortKey: 'status'", $jsx,
            'the derived status cell was given a header the server cannot obey');
    }

    #[Test]
    public function the_semester_filter_and_the_semester_choices_read_one_list_from_the_server(): void
    {
        $jsx = (string) file_get_contents(base_path('resources/js/Pages/Admin/ReferenceData/Terms.jsx'));

        // The page used to type the three semester values out beside a Select on the same field, while the handler
        // already answered `Semester::cases()`. Two copies of one enum drift silently: a fourth semester would reach
        // the create form and never appear in the filter.
        $this->assertStringNotContainsString('const semesterOptions = [', $jsx,
            'the page wrote the semester list out again, which is the two-copies-of-one-vocabulary defect');
        $this->assertStringContainsString('options={semesterChoices}', $jsx,
            'the picker is no longer built from the handler\'s own `semesters` prop');
        $this->assertStringContainsString('...semesters.map(', $jsx,
            'the choices are not derived from the server list');

        $payload = $this->props()['semesters'];
        $values = array_map(static fn (array $s): string => $s['value'], $payload);
        sort($values);
        $expected = self::SEMESTERS;
        sort($expected);
        $this->assertSame($expected, $values, 'the prop the picker reads is not the enum the model declares');
    }

    #[Test]
    public function a_column_the_desk_never_lists_falls_back_to_the_school_year_order(): void
    {
        $this->term($this->year('2025-2026')->academicYearId, '1st', '2025-06-01', '2025-10-31');
        $this->term($this->year('2024-2025')->academicYearId, '1st', '2024-06-01', '2024-10-31');

        $default = $this->props();
        $forged = $this->props(['sort' => 'passwordHash']);

        $this->assertSame('academicYear', $forged['sort']['column'],
            'an unlisted key did not fall back to the column the list is ordered by anyway');
        $this->assertSame($this->years($default), $this->years($forged),
            'the fallback answered a different sequence than the list opens with');
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_never_passed_through(): void
    {
        $this->term($this->year('2024-2025')->academicYearId, '1st', '2024-06-01', '2024-10-31');
        $this->term($this->year('2025-2026')->academicYearId, '1st', '2025-06-01', '2025-10-31');

        $props = $this->props(['sort' => 'academicYear', 'direction' => '; drop tables']);

        $this->assertSame('desc', $props['sort']['direction'], 'an unusable direction was answered as a direction');
        $this->assertSame(['2025-2026', '2024-2025'], $this->years($props), 'the list still came back, ordered by the real column');
    }

    #[Test]
    public function every_control_the_desk_set_comes_back_so_none_of_them_resets(): void
    {
        $year = $this->year('2026-2027');
        // Three terms so each control excludes at least one row: the search reaches both labels because
        // "2025-2026" contains "2026", the semester pick drops the 2nd, and the day drops the year that has
        // already closed. The day is frozen rather than inherited from the box, or this case would rot the
        // first time the suite ran outside the term it needs.
        $this->term($year->academicYearId, '1st', '2026-06-01', '2026-10-31');
        $this->term($year->academicYearId, '2nd', '2026-11-01', '2027-03-31');
        $prior = $this->year('2025-2026');
        $this->term($prior->academicYearId, '1st', '2025-06-01', '2025-10-31');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-15 04:00:00', 'UTC'));

        try {
            $props = $this->props([
                'search' => '2026',
                'semester' => '1st',
                'status' => 'active',
                'sort' => 'semester',
                'direction' => 'desc',
            ]);
        } finally {
            CarbonImmutable::setTestNow();
        }

        $this->assertSame('2026', $props['filters']['search']);
        $this->assertSame('1st', $props['filters']['semester']);
        $this->assertSame('active', $props['filters']['status']);
        $this->assertSame('semester', $props['filters']['sort'],
            'the order the server obeyed never came back, so the header resets while the list stays ordered');
        $this->assertSame('desc', $props['filters']['direction']);
        $this->assertCount(1, $props['terms']['data'], 'the three controls together did not narrow to the one term');
        $this->assertSame(['2026-2027'], $this->years($props));
        $this->assertSame(['1st'], $this->semesters($props));
    }

    #[Test]
    public function a_search_that_reaches_the_year_label_is_not_undone_by_the_order(): void
    {
        $this->term($this->year('2026-2027')->academicYearId, '1st', '2026-06-01', '2026-10-31');
        $this->term($this->year('2020-2021')->academicYearId, '1st', '2020-06-01', '2020-10-31');

        $props = $this->props(['search' => '2026', 'sort' => 'academicYear', 'direction' => 'asc']);

        $this->assertCount(1, $props['terms']['data'], 'a year-label search did not reach the term it belongs to');
        $this->assertSame('2026-2027', $this->years($props)[0]);
        $this->assertSame('academicYear', $props['filters']['sort'],
            'the order was dropped because the search joined the year for its own reason');
    }

    #[Test]
    public function the_action_reads_its_own_request_and_no_hard_coded_order_survives(): void
    {
        $src = (string) file_get_contents(base_path('app/Http/Controllers/Admin/ReferenceDataController.php'));
        $start = (int) strpos($src, 'public function terms(Request $request): Response');
        $this->assertGreaterThan(0, $start, 'the terms action was renamed or moved');
        $end = (int) strpos(substr($src, $start), "\n    public function ");
        $body = substr($src, $start, $end > 0 ? $end : 4000);

        $this->assertStringContainsString("\$request->input('sort', '')", $body);
        $this->assertStringContainsString("\$request->input('direction', 'asc')", $body,
            'a list that defaults to descending opens on its newest term, which is a queue answer');
        $this->assertStringContainsString('ListSort::apply(', $body);
        $this->assertStringNotContainsString("->orderByDesc('termId')", $body,
            'the hard-coded newest-first order the mechanism replaced is back, so a header would reset while the list stayed put');
        $this->assertMatchesRegularExpression('/\$today = SchoolTime::today\(\);/', $body,
            'the active/inactive filter stopped asking the school which day it is');
    }
}
