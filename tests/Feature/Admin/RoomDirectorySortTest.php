<?php

namespace Tests\Feature\Admin;

use App\Models\Rooms;
use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Prompt §14's sorting control, on the rooms directory — the twenty-third table wired to
 * `App\Support\ListSort` (§28.3 G-40, which stays leading `Open` because the owed list is printed by
 * `lane140-measure-wired-screens.php` rather than counted from memory).
 *
 * The office directory next to this one is the screen where the interesting claim is what may NOT be ordered. This
 * one is its mirror: every column the desk paints is a column `rooms` stores, so nothing is withheld and the two
 * claims worth pinning are that Capacity is ordered as a number rather than as a digit, and that a room whose
 * building is blank still arrives on the list the header was clicked to arrange.
 */
class RoomDirectorySortTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function room(string $name, int $capacity, string $building = 'Main Building'): Rooms
    {
        return Rooms::create([
            'roomName' => $name,
            // rooms.capacity is an integer and the desk's own validation refuses a blank or a zero, so a fixture
            // that wrote a string here would be testing a row the screen cannot make.
            'capacity' => $capacity,
            'building' => $building,
        ]);
    }

    private function admin(): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => 1,
            'employeeNo' => 'EMP-RM-'.uniqid(), 'username' => 'rm_'.uniqid(), 'email' => 'rm_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('SysAdmin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    /** @return array<string, mixed> */
    private function props(array $query = []): array
    {
        $response = $this->actingAs($this->admin())->get(route('admin.reference-data.rooms', $query));
        // A refusal renders as a page too, so every comparison case asks for a 200 before it reads a sequence.
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    /** @return list<string> */
    private function names(array $props): array
    {
        return array_map(static fn (array $r): string => (string) $r['roomName'], $props['rooms']['data']);
    }

    /** @return list<int> */
    private function capacities(array $props): array
    {
        return array_map(static fn (array $r): int => (int) $r['capacity'], $props['rooms']['data']);
    }

    #[Test]
    public function the_directory_opens_with_the_rooms_reading_top_to_bottom(): void
    {
        // Inserted out of alphabetical order and out of reverse order too, so neither id direction satisfies this.
        $this->room('Zeta Laboratory', 30);
        $this->room('Arts Room', 18);
        $this->room('Mabini Hall', 40);

        $props = $this->props();

        // The payload claims lead and the sequence follows, so a plant against one cannot fail on the other and leave
        // the harness reading a defect it did not name.
        $this->assertCount(3, $props['rooms']['data'], 'the fixture is not the whole population this claim is about');
        $this->assertSame('roomName', $props['sort']['column'], 'the page was not told which order was applied');
        $this->assertSame('asc', $props['sort']['direction'], 'a directory with no direction asked for opens ascending');
        $this->assertSame(['Arts Room', 'Mabini Hall', 'Zeta Laboratory'], $this->names($props),
            'the directory did not open in name order, which is how a desk reads a list of rooms');
    }

    #[Test]
    public function the_capacity_header_orders_by_the_number_and_not_by_the_digit(): void
    {
        // Deliberately inserted so that the five possible sequences each differ: capacity ascending, capacity
        // descending, the name order the directory opens on, and both id orders. A handler that obeyed nothing, or
        // ordered by the key instead of the header, cannot land on the expectation below by accident.
        $large = $this->room('Mabini Hall', 100);
        $small = $this->room('Zeta Laboratory', 9);
        $mid = $this->room('Arts Room', 20);

        $ascending = $this->props(['sort' => 'capacity', 'direction' => 'asc']);
        $descending = $this->props(['sort' => 'capacity', 'direction' => 'desc']);

        // Read as a string, 100 lands before 20 and 20 before 9 — so this pair is a measurement of the column's
        // type, not of the helper. Both drivers hold `rooms.capacity` as an integer, and neither may be allowed to
        // answer the digit order.
        $this->assertSame([$small->capacity, $mid->capacity, $large->capacity], $this->capacities($ascending),
            'Capacity was not ordered as a number');
        $this->assertSame([$large->capacity, $mid->capacity, $small->capacity], $this->capacities($descending),
            'the second click did not reverse the number');
        $this->assertSame([$mid->capacity, $large->capacity, $small->capacity], $this->capacities($this->props()),
            'the directory default already answers this sequence, so the two claims above would prove nothing');
    }

    #[Test]
    public function a_room_with_no_building_stays_on_the_list_and_reads_first(): void
    {
        // rooms.building is NOT NULL, and the desk writes an empty string when nobody types one — which the screen
        // paints as an em dash. Ordered by that column the blank is the smallest value, and the room must still be
        // listed: a header that quietly drops the rows it cannot name is the absence the screen exists to paint.
        $this->room('Zeta Laboratory', 30, 'Annex');
        $blank = $this->room('Arts Room', 18, '');
        $this->room('Mabini Hall', 40, 'Main Building');

        $ascending = $this->props(['sort' => 'building', 'direction' => 'asc']);

        $this->assertCount(3, $ascending['rooms']['data'], 'ordering by Building lost the room that has no building');
        $this->assertSame('Arts Room', $this->names($ascending)[0],
            'the blank building did not sort to the front of the ascending list');
        $this->assertSame('', $ascending['rooms']['data'][0]['building'] ?? null,
            "the screen paints its em dash from [{$blank->roomName}]'s own empty value, so the payload must carry it rather than a null");
    }

    #[Test]
    public function only_the_three_columns_the_query_holds_are_offered_as_orders(): void
    {
        $this->room('Any Room', 20);

        $available = $this->props()['sort']['available'];
        sort($available);

        // Exact membership, in the other directory's shape: here every painted column is a stored column, so a
        // fourth key would be an order for something the screen does not show.
        $this->assertSame(['building', 'capacity', 'roomName'], $available,
            'an order was offered that the rooms table does not hold, or a real column lost its header');
    }

    #[Test]
    public function every_header_the_page_paints_is_a_key_the_action_obeys(): void
    {
        $available = $this->props()['sort']['available'];
        $jsx = (string) file_get_contents(base_path('resources/js/Pages/Admin/ReferenceData/Rooms.jsx'));

        // `DataTable` renders a header only for a key in `sort.available`, so a `sortKey` the handler does not list
        // is a control that never appears and every figure on the page stays correct — no backend case sees the loss.
        $found = preg_match_all("/sortKey: '([A-Za-z]+)'/", $jsx, $m) >= 1 ? $m[1] : [];

        $this->assertNotSame([], $found, 'the page lists no sortable header at all, so this case would prove nothing');
        foreach ($found as $key) {
            $this->assertContains($key, $available, "the page offers a header for [{$key}] and the action would not obey it");
        }
        $this->assertCount(3, $found, 'a painted column lost its header, so the desk cannot order what it can see');
        // roomId is the tie-break the query holds but no column shows: offering it would put a header on nothing.
        $this->assertNotContains('roomId', $found, 'the record key gained a header on a screen that does not paint it');
    }

    #[Test]
    public function a_column_the_desk_never_lists_falls_back_to_the_directory_order(): void
    {
        $this->room('Zeta Laboratory', 30);
        $this->room('Arts Room', 18);

        $default = $this->props();
        // No direction beside the unlisted key: an unlisted key WITH a direction is a legitimate order the desk could
        // ask for, and comparing it to the unsorted request is how one fallback case in this family wrote a wrong
        // expectation over right code.
        $forged = $this->props(['sort' => 'passwordHash']);

        $this->assertSame('roomName', $forged['sort']['column'],
            'an unlisted key did not fall back to the column the directory is ordered by anyway');
        $this->assertSame($this->names($default), $this->names($forged),
            'the fallback answered a different sequence than the directory opens with');
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_never_passed_through(): void
    {
        $this->room('Arts Room', 18);
        $this->room('Zeta Laboratory', 30);

        $props = $this->props(['sort' => 'roomName', 'direction' => '; drop tables']);

        $this->assertSame('desc', $props['sort']['direction'], 'an unusable direction was answered as a direction');
        $this->assertSame(['Zeta Laboratory', 'Arts Room'], $this->names($props), 'the list still came back, ordered by the real column');
    }

    #[Test]
    public function a_filter_and_an_order_are_both_echoed_so_neither_control_resets(): void
    {
        $this->room('Clinic North', 9);
        $this->room('Clinic South', 20);
        $this->room('Mabini Hall', 40);

        $props = $this->props(['search' => 'Clinic', 'sort' => 'capacity', 'direction' => 'asc']);

        $this->assertSame('capacity', $props['filters']['sort'],
            'the order the server obeyed never came back, so the header resets while the list stays ordered');
        $this->assertSame('asc', $props['filters']['direction']);
        $this->assertSame('Clinic', $props['filters']['search']);
        $this->assertSame(['Clinic North', 'Clinic South'], $this->names($props),
            'the search narrowed the set the order arranges');
    }

    #[Test]
    public function the_action_reads_its_own_request_and_no_hard_coded_order_survives(): void
    {
        // `lane46-filter-contract.php` judges a key on the action's own line: one consumed a call deeper reads as
        // DEAD-SEND, so the two request values are named here rather than hidden inside the helper.
        $src = (string) file_get_contents(base_path('app/Http/Controllers/Admin/ReferenceDataController.php'));
        $start = (int) strpos($src, 'public function rooms(Request $request): Response');
        $this->assertGreaterThan(0, $start, 'the rooms action was renamed or moved');
        $end = (int) strpos(substr($src, $start), "\n    public function ");
        $body = substr($src, $start, $end > 0 ? $end : 4000);

        $this->assertStringContainsString("\$request->input('sort', '')", $body);
        $this->assertStringContainsString("\$request->input('direction', 'asc')", $body,
            'a directory that defaults to descending opens on the last room, which is a queue answer');
        $this->assertStringContainsString('ListSort::apply(', $body);
        $this->assertStringNotContainsString("->orderByDesc('roomId')", $body,
            'the hard-coded order the mechanism replaced is back, so a header would reset while the list stayed put');
    }
}
