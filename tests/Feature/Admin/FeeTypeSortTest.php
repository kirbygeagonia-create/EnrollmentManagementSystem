<?php

namespace Tests\Feature\Admin;

use App\Enums\FeeUnitBasis;
use App\Models\Feetypes;
use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EnumColumnOrder;
use Tests\TestCase;

/**
 * Prompt §14's sorting control, on the fee-sheet catalogue — the twentieth table wired to
 * `App\Support\ListSort` (§28.3 G-40, which stays leading `Open` because the owed list is printed by
 * `lane140-measure-wired-screens.php` rather than counted from memory).
 *
 * The board maintained the catalogue in one order — newest id first — so a bursar looking for the laboratory fee
 * read the whole list. Every column here sits on the row the desk already paints, so the screen writes no join and
 * an order cannot widen a payload or drop a row; what the cases below pin is the DIRECTION of each promise rather
 * than that something moved.
 *
 * The fixtures are built so an id order can never satisfy an assertion: a sequence the expected order shares with
 * either id direction would pass on a handler that ignored the request, which is how a sort case in this family once
 * went green while the sort was dead.
 */
class FeeTypeSortTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function fee(string $name, float $amount, FeeUnitBasis $basis): Feetypes
    {
        return Feetypes::create([
            'feeName' => $name,
            'defaultAmount' => $amount,
            'unitBasis' => $basis,
        ]);
    }

    private function admin(): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => 2,
            'employeeNo' => 'EMP-FTS-'.uniqid(), 'username' => 'fts_'.uniqid(), 'email' => 'fts_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('SysAdmin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    /** @return array<string, mixed> */
    private function props(array $query = []): array
    {
        $response = $this->actingAs($this->admin())->get(route('admin.reference-data.fee-types', $query));
        // A refusal renders as a page too, so every comparison case asks for a 200 before it reads a sequence.
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    /** @return list<string> */
    private function names(array $props): array
    {
        return array_map(static fn (array $r): string => (string) $r['feeName'], $props['feeTypes']['data']);
    }

    #[Test]
    public function the_catalogue_opens_with_the_names_reading_top_to_bottom(): void
    {
        // Inserted out of alphabetical order, and out of reverse order too, so the expectation cannot be met by a
        // key sort in either direction.
        $this->fee('Zebra Permit', 100.00, FeeUnitBasis::Flat);
        $this->fee('Laboratory Fee', 200.00, FeeUnitBasis::PerUnit);
        $this->fee('Assessment Fee', 300.00, FeeUnitBasis::PerUnit);

        $props = $this->props();

        $this->assertSame(['Assessment Fee', 'Laboratory Fee', 'Zebra Permit'], $this->names($props),
            'the catalogue did not open in name order, which is how a board reads a fee list');
        $this->assertSame('feeName', $props['sort']['column'], 'the page was not told which order was applied');
        $this->assertSame('asc', $props['sort']['direction'], 'a catalogue with no direction asked for opens ascending');
    }

    #[Test]
    public function the_amount_header_orders_by_the_amount_and_not_by_the_record_key(): void
    {
        $this->fee('Five Hundred', 500.00, FeeUnitBasis::Flat);
        $this->fee('One Hundred', 100.00, FeeUnitBasis::Flat);
        $this->fee('Nine Hundred', 900.00, FeeUnitBasis::Flat);

        $ascending = $this->props(['sort' => 'defaultAmount', 'direction' => 'asc']);
        $descending = $this->props(['sort' => 'defaultAmount', 'direction' => 'desc']);

        $amounts = static fn (array $p): array => array_map(
            static fn (array $r): float => (float) $r['defaultAmount'],
            $p['feeTypes']['data']
        );

        $this->assertSame([100.00, 500.00, 900.00], $amounts($ascending), 'the amount header did not order by amount');
        $this->assertSame([900.00, 500.00, 100.00], $amounts($descending), 'the second click did not reverse the amount');
    }

    #[Test]
    public function the_unit_basis_header_orders_the_enum_by_the_order_this_connection_gives_it(): void
    {
        $values = [FeeUnitBasis::PerUnit->value, FeeUnitBasis::Flat->value];
        // Three rows, and the middle one is the only flat fee. A withheld column falls back to the name order,
        // which yields [perUnit, flat, perUnit] — a sequence neither driver's real answer can produce, because
        // MySQL groups by the declaration and the suite's varchar groups alphabetically. A first draft of this
        // case used two rows, and on SQLite the name fallback equalled the expected order, so the harness leg
        // survived and the fixture was the defect rather than the guard.
        $this->fee('Alpha Fee', 100.00, FeeUnitBasis::PerUnit);
        $this->fee('Bravo Fee', 200.00, FeeUnitBasis::Flat);
        $this->fee('Charlie Fee', 300.00, FeeUnitBasis::PerUnit);

        // `feetypes.unitBasis` is `enum('perUnit','flat')` on the school's server, which sorts by the declaration the
        // school wrote into the migration; the suite compiles the same `enum()` to a varchar and sorts the stored
        // word. Asking the connection is the only claim true on both.
        $order = EnumColumnOrder::ascending('feetypes', 'unitBasis', $values);

        // The helper orders the DISTINCT values; the list repeats each one as many times as the fixture holds rows
        // with that basis, ties broken on the row's own key. Comparing the two-element distinct list to a
        // three-row sequence is the mistake this line exists to prevent.
        $counts = ['flat' => 0, 'perUnit' => 0];
        foreach ([FeeUnitBasis::Flat->value, FeeUnitBasis::PerUnit->value] as $v) {
            $counts[$v] += Feetypes::where('unitBasis', $v)->count();
        }
        $expected = [];
        foreach ($order as $value) {
            $expected = [...$expected, ...array_fill(0, $counts[$value], $value)];
        }

        $seen = array_map(
            static fn (array $r): string => (string) $r['unitBasis'],
            $this->props(['sort' => 'unitBasis', 'direction' => 'asc'])['feeTypes']['data']
        );

        $this->assertSame($expected, $seen, 'the basis header did not order the enum the way this server orders it');
        $this->assertCount(3, $seen, 'the group-by order is only observable across all three rows');
    }

    #[Test]
    public function a_column_the_board_never_lists_falls_back_to_the_catalogue_order(): void
    {
        $this->fee('Zebra Permit', 100.00, FeeUnitBasis::Flat);
        $this->fee('Assessment Fee', 200.00, FeeUnitBasis::PerUnit);

        $default = $this->props();
        // No direction beside the unlisted key: an unlisted key WITH a direction is a legitimate order the desk
        // could ask for, and comparing it to the unsorted request is how one fallback case in this family wrote a
        // wrong expectation over right code.
        $forged = $this->props(['sort' => 'passwordHash']);

        $this->assertSame($this->names($default), $this->names($forged));
        $this->assertSame('feeName', $forged['sort']['column'],
            'an unlisted key did not fall back to the column the catalogue is ordered by anyway');
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_never_passed_through(): void
    {
        $this->fee('One Fee', 100.00, FeeUnitBasis::Flat);
        $this->fee('Two Fee', 200.00, FeeUnitBasis::Flat);

        $props = $this->props(['sort' => 'defaultAmount', 'direction' => '; drop tables']);

        $this->assertSame('desc', $props['sort']['direction'], 'an unusable direction was answered as a direction');
        $this->assertSame(['Two Fee', 'One Fee'], $this->names($props), 'the list still came back, ordered by the real column');
    }

    #[Test]
    public function a_filter_and_an_order_are_both_echoed_so_neither_control_resets(): void
    {
        $this->fee('Library Fee', 100.00, FeeUnitBasis::Flat);
        $this->fee('Library Fee Two', 900.00, FeeUnitBasis::Flat);
        $this->fee('Zebra Permit', 500.00, FeeUnitBasis::PerUnit);

        $props = $this->props(['search' => 'Library', 'sort' => 'defaultAmount', 'direction' => 'desc']);

        $this->assertSame('defaultAmount', $props['filters']['sort'],
            'the order the server obeyed never came back, so the header resets while the list stays ordered');
        $this->assertSame('desc', $props['filters']['direction']);
        $this->assertSame('Library', $props['filters']['search']);
        $this->assertSame(['Library Fee Two', 'Library Fee'], $this->names($props),
            'the search narrowed the set the order arranges');
    }

    #[Test]
    public function the_action_reads_its_own_request_and_no_hard_coded_order_survives(): void
    {
        // `lane46-filter-contract.php` judges a key on the action's own line: one consumed a call deeper reads as
        // DEAD-SEND, so the two request values are named here rather than hidden inside the helper.
        $src = (string) file_get_contents(base_path('app/Http/Controllers/Admin/ReferenceDataController.php'));
        $start = (int) strpos($src, 'public function feeTypes(Request $request): Response');
        $this->assertGreaterThan(0, $start, 'the feeTypes action was renamed or moved');
        $end = (int) strpos(substr($src, $start), "\n    public function ");
        $body = substr($src, $start, $end > 0 ? $end : 4000);

        $this->assertStringContainsString("\$request->input('sort', '')", $body);
        $this->assertStringContainsString("\$request->input('direction', 'asc')", $body,
            'a catalogue that defaults to descending hands the board the newest row first, which is a queue answer');
        $this->assertStringContainsString('ListSort::apply(', $body);
        $this->assertStringNotContainsString("->orderByDesc('feeTypeId')", $body,
            'the hard-coded order the mechanism replaced is back, so a header would reset while the list stayed put');
    }
}
