<?php

namespace Tests\Feature\Admin;

use App\Enums\CoverageType;
use App\Models\Scholarshiptypes;
use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EnumColumnOrder;
use Tests\TestCase;

/**
 * Prompt §14's sorting control, on the scholarship catalogue — the twenty-first table wired to
 * `App\Support\ListSort` (§28.3 G-40, which stays leading `Open` because the owed list is printed by
 * `lane140-measure-wired-screens.php` rather than counted from memory).
 *
 * The board maintained the catalogue newest-id-first, so a scholarship officer reading for the partial-coverage
 * grants scanned the whole list. All three columns sit on the row the desk already paints, so the screen writes no
 * hop: an order here can neither widen a payload (G-51) nor drop a record (the nullable-key lesson from the audit
 * log). What these cases pin is the DIRECTION of each promise, not that something moved.
 *
 * Every fixture is built so that neither id order nor the catalogue's own name order can satisfy an assertion by
 * accident — the trap this family recorded twice, when a mutant survived and the fixture was the defect.
 */
class ScholarshipTypeSortTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function type(string $name, float $percent, CoverageType $coverage): Scholarshiptypes
    {
        return Scholarshiptypes::create([
            'scholarshipName' => $name,
            'coverageType' => $coverage,
            'coveragePercent' => $percent,
        ]);
    }

    private function admin(): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => 3,
            'employeeNo' => 'EMP-STS-'.uniqid(), 'username' => 'sts_'.uniqid(), 'email' => 'sts_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('SysAdmin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    /** @return array<string, mixed> */
    private function props(array $query = []): array
    {
        $response = $this->actingAs($this->admin())->get(route('admin.reference-data.scholarship-types', $query));
        // A refusal renders as a page too, so every comparison case asks for a 200 before it reads a sequence.
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    /** @return list<string> */
    private function names(array $props): array
    {
        return array_map(static fn (array $r): string => (string) $r['scholarshipName'], $props['types']['data']);
    }

    #[Test]
    public function the_catalogue_opens_with_the_names_reading_top_to_bottom(): void
    {
        // Inserted so that name order differs from id ascending AND from id descending, the order the board used.
        $this->type('Zeta Endowment', 100.00, CoverageType::Full);
        $this->type('Alpha Grant', 25.00, CoverageType::Partial);
        $this->type('Merit Scholarship', 50.00, CoverageType::Full);

        $props = $this->props();

        $this->assertSame(['Alpha Grant', 'Merit Scholarship', 'Zeta Endowment'], $this->names($props),
            'the catalogue did not open in name order, which is how a board reads a list of grants');
        $this->assertSame('scholarshipName', $props['sort']['column'], 'the page was not told which order was applied');
        $this->assertSame('asc', $props['sort']['direction'], 'a catalogue with no direction asked for opens ascending');
    }

    #[Test]
    public function the_coverage_percent_header_orders_by_the_figure_and_not_by_the_record_key(): void
    {
        // Alphabetical here is [Diligence 90, Merit 10, Need 50]; the two id directions give two more sequences, so
        // each of the two expectations below can only be met by reading the column the header named.
        $this->type('Diligence Grant', 90.00, CoverageType::Partial);
        $this->type('Merit Grant', 10.00, CoverageType::Full);
        $this->type('Need Aid', 50.00, CoverageType::Partial);

        $ascending = $this->props(['sort' => 'coveragePercent', 'direction' => 'asc']);
        $descending = $this->props(['sort' => 'coveragePercent', 'direction' => 'desc']);

        $percents = static fn (array $p): array => array_map(
            static fn (array $r): float => (float) $r['coveragePercent'],
            $p['types']['data']
        );

        $this->assertSame([10.00, 50.00, 90.00], $percents($ascending), 'the percent header did not order by percent');
        $this->assertSame([90.00, 50.00, 10.00], $percents($descending), 'the second click did not reverse the percent');
        $this->assertSame(['Merit Grant', 'Need Aid', 'Diligence Grant'], $this->names($ascending),
            'the order came from a column other than the one the header named');
    }

    #[Test]
    public function the_coverage_header_orders_the_enum_by_the_order_this_connection_gives_it(): void
    {
        $values = [CoverageType::Full->value, CoverageType::Partial->value];
        // Three rows, and the first-listed one is the only full grant. A withheld column falls back to the name
        // order, which yields [partial, full, partial] — a sequence the real enum order cannot produce, because that
        // order groups the two partial rows together behind the full one.
        $this->type('Alpha Scholarship', 100.00, CoverageType::Partial);
        $this->type('Bravo Scholarship', 50.00, CoverageType::Full);
        $this->type('Charlie Scholarship', 75.00, CoverageType::Partial);

        // `scholarshiptypes.coverageType` is `enum('full','partial')` on the school's server, which sorts by the
        // declaration the migration wrote; the suite compiles the same `enum()` to a varchar and sorts the stored
        // word. Asking the connection is the only claim true on both.
        $order = EnumColumnOrder::ascending('scholarshiptypes', 'coverageType', $values);

        // The helper orders the DISTINCT values; the list repeats each one as many times as the fixture holds rows
        // with that coverage, ties broken on the row's own key. Comparing a two-element distinct list to a three-row
        // sequence is the mistake this block exists to prevent.
        $counts = ['full' => 0, 'partial' => 0];
        foreach ([CoverageType::Full->value, CoverageType::Partial->value] as $v) {
            $counts[$v] += Scholarshiptypes::where('coverageType', $v)->count();
        }
        $expected = [];
        foreach ($order as $value) {
            $expected = [...$expected, ...array_fill(0, $counts[$value], $value)];
        }

        $seen = array_map(
            static fn (array $r): string => (string) $r['coverageType'],
            $this->props(['sort' => 'coverageType', 'direction' => 'asc'])['types']['data']
        );

        $this->assertSame($expected, $seen, 'the coverage header did not order the enum the way this server orders it');
        $this->assertCount(3, $seen, 'the group order is only observable across all three rows');
        $this->assertSame('coverageType', $this->props(['sort' => 'coverageType', 'direction' => 'asc'])['sort']['column'],
            'the ENUM column is offered, so the echo must name it rather than the fallback');
    }

    #[Test]
    public function a_column_the_board_never_lists_falls_back_to_the_catalogue_order(): void
    {
        $this->type('Zeta Endowment', 100.00, CoverageType::Full);
        $this->type('Alpha Grant', 25.00, CoverageType::Partial);

        $default = $this->props();
        // No direction beside the unlisted key: an unlisted key WITH a direction is a legitimate order the desk
        // could ask for, and comparing it to the unsorted request is how one fallback case in this family wrote a
        // wrong expectation over right code.
        $forged = $this->props(['sort' => 'passwordHash']);

        $this->assertSame($this->names($default), $this->names($forged));
        $this->assertSame('scholarshipName', $forged['sort']['column'],
            'an unlisted key did not fall back to the column the catalogue is ordered by anyway');
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_never_passed_through(): void
    {
        $this->type('Athletic Fund', 25.00, CoverageType::Partial);
        $this->type('Bravo Fund', 75.00, CoverageType::Full);

        $props = $this->props(['sort' => 'coveragePercent', 'direction' => '; drop tables']);

        $this->assertSame('desc', $props['sort']['direction'], 'an unusable direction was answered as a direction');
        $this->assertSame(['Bravo Fund', 'Athletic Fund'], $this->names($props), 'the list still came back, ordered by the real column');
    }

    #[Test]
    public function a_filter_and_an_order_are_both_echoed_so_neither_control_resets(): void
    {
        $this->type('Diligence Grant', 90.00, CoverageType::Partial);
        $this->type('Merit Grant', 10.00, CoverageType::Full);
        $this->type('Zebra Scholarship', 50.00, CoverageType::Partial);

        $props = $this->props(['search' => 'Grant', 'sort' => 'coveragePercent', 'direction' => 'desc']);

        $this->assertSame('coveragePercent', $props['filters']['sort'],
            'the order the server obeyed never came back, so the header resets while the list stays ordered');
        $this->assertSame('desc', $props['filters']['direction']);
        $this->assertSame('Grant', $props['filters']['search']);
        $this->assertSame(['Diligence Grant', 'Merit Grant'], $this->names($props),
            'the search narrowed the set the order arranges');
    }

    #[Test]
    public function every_header_the_page_paints_is_a_key_the_action_obeys(): void
    {
        $available = $this->props()['sort']['available'];

        // The shared control renders a clickable header only for a key in `sort.available`, so a `sortKey` spelled on
        // the page that the handler does not list is a header that resets while the list stays put — the same defect
        // lane 46 owns for a filter. The pair is read across the runtimes because there is no JavaScript suite.
        $jsx = (string) file_get_contents(base_path('resources/js/Pages/Admin/ReferenceData/ScholarshipTypes.jsx'));
        // `preg_match_all` answers the COUNT, not the matches, so the guard is on the count and the rows come from $m[1].
        $found = preg_match_all("/sortKey: '([A-Za-z]+)'/", $jsx, $m) >= 1 ? $m[1] : [];

        $this->assertNotSame([], $found, 'the page lists no sortable header at all, so this case would prove nothing');
        foreach ($found as $key) {
            $this->assertContains($key, $available,
                "the page offers a header for [{$key}] and the action would not obey it");
        }
        $this->assertContains('scholarshipName', $found, 'the name column, which the catalogue is ordered by, carries no header');
        $this->assertNotContains('passwordHash', $available, 'a column no desk paints is offered as an order');
    }

    #[Test]
    public function the_action_reads_its_own_request_and_no_hard_coded_order_survives(): void
    {
        // `lane46-filter-contract.php` judges a key on the action's own line: one consumed a call deeper reads as
        // DEAD-SEND, so the two request values are named here rather than hidden inside the helper.
        $src = (string) file_get_contents(base_path('app/Http/Controllers/Admin/ReferenceDataController.php'));
        $start = (int) strpos($src, 'public function scholarshipTypes(Request $request): Response');
        $this->assertGreaterThan(0, $start, 'the scholarshipTypes action was renamed or moved');
        $end = (int) strpos(substr($src, $start), "\n    public function ");
        $body = substr($src, $start, $end > 0 ? $end : 4000);

        $this->assertStringContainsString("\$request->input('sort', '')", $body);
        $this->assertStringContainsString("\$request->input('direction', 'asc')", $body,
            'a catalogue that defaults to descending hands the board the newest row first, which is a queue answer');
        $this->assertStringContainsString('ListSort::apply(', $body);
        $this->assertStringNotContainsString("->orderByDesc('scholarshipTypeId')", $body,
            'the hard-coded order the mechanism replaced is back, so a header would reset while the list stayed put');
    }
}
