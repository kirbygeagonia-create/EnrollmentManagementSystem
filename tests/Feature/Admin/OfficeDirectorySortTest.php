<?php

namespace Tests\Feature\Admin;

use App\Models\Offices;
use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Prompt §14's sorting control, on the office directory — the twenty-second table wired to
 * `App\Support\ListSort` (§28.3 G-40, which stays leading `Open` because the owed list is printed by
 * `lane140-measure-wired-screens.php` rather than counted from memory).
 *
 * This is the first screen in the tranche where the interesting claim is what may NOT be ordered. The page paints
 * four columns and only two of them are columns the query holds: the desk name comes from `App\Enums\OfficeId` and
 * the three "Used by" figures are grouped counts over other tables. A header for either would be the defect §28.3
 * owns for filters — the desk clicks, the server obeys nothing, the arrow resets — so the offered set is asserted
 * exact rather than merely present, and the page's own `sortKey` list is paired against it.
 */
class OfficeDirectorySortTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function office(string $name): Offices
    {
        return Offices::create(['officeName' => $name]);
    }

    private function admin(): Staffusers
    {
        // `officeId` is null on purpose: this is the account bounded by nothing, and any other value makes the
        // factory reach into `offices` to satisfy the key — which would add a row to the very table the cases below
        // count. SQLite hid that (a rolled-back test rewinds the counter, so the id was always already taken);
        // MySQL, whose counter stays spent, showed the extra office.
        $staff = Staffusers::factory()->create([
            'officeId' => null,
            'employeeNo' => 'EMP-OFS-'.uniqid(), 'username' => 'ofs_'.uniqid(), 'email' => 'ofs_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('SysAdmin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    /** @return array<string, mixed> */
    private function props(array $query = []): array
    {
        $response = $this->actingAs($this->admin())->get(route('admin.reference-data.offices', $query));
        // A refusal renders as a page too, so every comparison case asks for a 200 before it reads a sequence.
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    /** @return list<string> */
    private function names(array $props): array
    {
        return array_map(static fn (array $r): string => (string) $r['officeName'], $props['offices']['data']);
    }

    #[Test]
    public function the_directory_opens_with_the_names_reading_top_to_bottom(): void
    {
        // Inserted out of alphabetical order and out of reverse order too, so neither id direction satisfies this.
        $this->office('Zonal Clinic');
        $this->office('Accounting Office');
        $this->office('Registrar Central');

        $props = $this->props();

        $this->assertCount(3, $props['offices']['data'], 'the fixture is not the whole population this claim is about');
        $this->assertSame(['Accounting Office', 'Registrar Central', 'Zonal Clinic'], $this->names($props),
            'the directory did not open in name order, which is how a board reads a list of offices');
        $this->assertSame('officeName', $props['sort']['column'], 'the page was not told which order was applied');
        $this->assertSame('asc', $props['sort']['direction'], 'a directory with no direction asked for opens ascending');
    }

    #[Test]
    public function the_id_header_orders_by_the_record_key_both_ways(): void
    {
        // The three rows are taken from the models the fixture created, never from an arithmetic id: a rolled-back
        // test spends its counter on MySQL and rewinds it on SQLite, so a literal id is a reading of one surface.
        $alpha = $this->office('Alpha Office');
        $zulu = $this->office('Zulu Office');
        $mike = $this->office('Mike Office');

        $ascending = $this->props(['sort' => 'officeId', 'direction' => 'asc']);
        $descending = $this->props(['sort' => 'officeId', 'direction' => 'desc']);

        $ids = static fn (array $p): array => array_map(
            static fn (array $r): int => (int) $r['officeId'],
            $p['offices']['data']
        );

        $this->assertSame([$alpha->officeId, $zulu->officeId, $mike->officeId], $ids($ascending),
            'the id header did not order by the record key ascending');
        $this->assertSame([$mike->officeId, $zulu->officeId, $alpha->officeId], $ids($descending),
            'the second click did not reverse the record key');
        // The name order is Alpha, Mike, Zulu — a different sequence in both directions — so neither expectation
        // above can be met by a handler that ignored the key the desk clicked.
        $this->assertSame([$alpha->officeId, $mike->officeId, $zulu->officeId],
            array_map(static fn (array $r): int => (int) $r['officeId'], $this->props()['offices']['data']),
            'the directory default is not the record-key order, which is what makes the two assertions above mean something');
    }

    #[Test]
    public function only_the_two_columns_the_query_holds_are_offered_as_orders(): void
    {
        $this->office('Any Office');

        $available = $this->props()['sort']['available'];
        sort($available);

        // The membership claim, exact: the desk name and the usage figures are built per row after the query, so a
        // header for them would be an order the handler cannot answer.
        $this->assertSame(['officeId', 'officeName'], $available,
            'an order was offered for something the query does not hold, or a real column lost its header');
    }

    #[Test]
    public function every_header_the_page_paints_is_a_key_the_action_obeys(): void
    {
        $available = $this->props()['sort']['available'];
        $jsx = (string) file_get_contents(base_path('resources/js/Pages/Admin/ReferenceData/Offices.jsx'));

        // `DataTable` renders a header only for a key in `sort.available`, so a `sortKey` the handler does not list
        // is a control that never appears and every figure on the page stays correct — no backend case sees the loss.
        $found = preg_match_all("/sortKey: '([A-Za-z]+)'/", $jsx, $m) >= 1 ? $m[1] : [];

        $this->assertNotSame([], $found, 'the page lists no sortable header at all, so this case would prove nothing');
        foreach ($found as $key) {
            $this->assertContains($key, $available, "the page offers a header for [{$key}] and the action would not obey it");
        }
        $this->assertNotContains('workflowName', $found, 'the derived desk label gained a header the query cannot answer');
        $this->assertNotContains('usage', $found, 'the grouped counts gained a header the query cannot answer');
    }

    #[Test]
    public function a_column_the_board_never_lists_falls_back_to_the_directory_order(): void
    {
        $this->office('Zonal Clinic');
        $this->office('Accounting Office');

        $default = $this->props();
        // No direction beside the unlisted key: an unlisted key WITH a direction is a legitimate order the desk could
        // ask for, and comparing it to the unsorted request is how one fallback case in this family wrote a wrong
        // expectation over right code.
        $forged = $this->props(['sort' => 'passwordHash']);

        $this->assertSame($this->names($default), $this->names($forged));
        $this->assertSame('officeName', $forged['sort']['column'],
            'an unlisted key did not fall back to the column the directory is ordered by anyway');
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_never_passed_through(): void
    {
        $this->office('Alpha Office');
        $this->office('Bravo Office');

        $props = $this->props(['sort' => 'officeName', 'direction' => '; drop tables']);

        $this->assertSame('desc', $props['sort']['direction'], 'an unusable direction was answered as a direction');
        $this->assertSame(['Bravo Office', 'Alpha Office'], $this->names($props), 'the list still came back, ordered by the real column');
    }

    #[Test]
    public function a_filter_and_an_order_are_both_echoed_so_neither_control_resets(): void
    {
        $this->office('Clinic North');
        $this->office('Clinic South');
        $this->office('Registrar Central');

        $props = $this->props(['search' => 'Clinic', 'sort' => 'officeName', 'direction' => 'desc']);

        $this->assertSame('officeName', $props['filters']['sort'],
            'the order the server obeyed never came back, so the header resets while the list stays ordered');
        $this->assertSame('desc', $props['filters']['direction']);
        $this->assertSame('Clinic', $props['filters']['search']);
        $this->assertSame(['Clinic South', 'Clinic North'], $this->names($props), 'the search narrowed the set the order arranges');
    }

    #[Test]
    public function the_action_reads_its_own_request_and_no_hard_coded_order_survives(): void
    {
        // `lane46-filter-contract.php` judges a key on the action's own line: one consumed a call deeper reads as
        // DEAD-SEND, so the two request values are named here rather than hidden inside the helper.
        $src = (string) file_get_contents(base_path('app/Http/Controllers/Admin/ReferenceDataController.php'));
        $start = (int) strpos($src, 'public function offices(Request $request): Response');
        $this->assertGreaterThan(0, $start, 'the offices action was renamed or moved');
        $end = (int) strpos(substr($src, $start), "\n    public function ");
        $body = substr($src, $start, $end > 0 ? $end : 4000);

        $this->assertStringContainsString("\$request->input('sort', '')", $body);
        $this->assertStringContainsString("\$request->input('direction', 'asc')", $body,
            'a directory that defaults to descending opens on the last office, which is a queue answer');
        $this->assertStringContainsString('ListSort::apply(', $body);
        $this->assertStringNotContainsString("->orderBy('officeId')", $body,
            'the hard-coded order the mechanism replaced is back, so a header would reset while the list stayed put');
    }
}
