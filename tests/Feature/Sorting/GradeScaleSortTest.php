<?php

namespace Tests\Feature\Sorting;

use App\Models\Gradescale;
use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Prompt §14's sorting control on the grade-scale bands — the eighteenth table wired (§28.3 G-40 stays Open).
 *
 * This screen arrived in the right order already: its old `orderBy('minGrade')` is the same sequence the mechanism
 * now answers, so wiring it changes nothing a person sees and only adds the headers. That is worth pinning, because
 * the honest default is the point of the whole family — a grade scale read from the floor up is a scale, and one
 * read newest-id-first is an accident of insertion.
 *
 * The 'range' cell paints `minGrade – maxGrade`, so ordering it means ordering the floor; `maxGrade` is never
 * painted alone and therefore carries no header. The fixture's four bands are named and numbered so the six orders
 * it can produce are six different sequences — the check that lets a mutant fail rather than agree by accident.
 */
class GradeScaleSortTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $desk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->desk = Staffusers::create([
            'employeeNo' => 'EMP-GS-'.uniqid(),
            'firstName' => 'Grade',
            'middleName' => '',
            'lastName' => 'Clerk',
            'username' => 'gs_clerk_'.uniqid(),
            'passwordHash' => bcrypt('password'),
            'role' => 'admin',
            'email' => 'gs.clerk@seait.edu.ph',
            'contactNo' => '09170000000',
            'status' => 'active',
        ]);
        $this->desk->assignRole('SysAdmin');

        // floor, passing, description — created A, B, C, D in that order. The floors are GRADE POINTS, not
        // percentages: `gradescale.minGrade` and `maxGrade` are `decimal(3,2)`, so the column holds at most 9.99.
        // The first draft wrote 75 and 79.99 here, SQLite accepted them silently and every case was green on this
        // box while CI's MySQL job refused the insert with error 1264 — a fixture whose values are outside the
        // school's own column is true for the wrong reason, the spent-counter lesson reached from the type side.
        $this->band(2.00, 2.49, true, 'zeta');
        $this->band(1.50, 1.99, false, 'alpha');
        $this->band(2.50, 4.00, true, 'mike');
        $this->band(1.00, 1.49, false, 'bravo');
    }

    private function band(float $min, float $max, bool $passing, string $description): Gradescale
    {
        return Gradescale::create([
            'minGrade' => $min,
            'maxGrade' => $max,
            'isPassing' => $passing,
            'description' => $description,
        ]);
    }

    /** @return array<int, mixed> */
    private function props(mixed $response): array
    {
        return $response->viewData('page')['props'];
    }

    /** @return list<string> the descriptions in the order the screen listed them */
    private function names(mixed $response): array
    {
        return collect($this->props($response)['bands']['data'])->pluck('description')->all();
    }

    #[Test]
    public function the_bands_open_on_the_floor_ascending_which_is_how_a_scale_is_read(): void
    {
        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.grade-scale'));
        $response->assertOk();
        $props = $this->props($response);

        $this->assertSame('range', $props['sort']['column']);
        $this->assertSame('asc', $props['sort']['direction']);
        $this->assertSame('asc', $props['filters']['direction'], 'the screen is told the default it got');
        $this->assertSame(['bravo', 'alpha', 'zeta', 'mike'], $this->names($response));
    }

    #[Test]
    public function the_second_click_on_the_range_header_reverses_the_scale(): void
    {
        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.grade-scale', [
            'sort' => 'range', 'direction' => 'desc',
        ]));
        $response->assertOk();

        $this->assertSame(['mike', 'zeta', 'alpha', 'bravo'], $this->names($response));
        $this->assertSame('range', $this->props($response)['filters']['sort']);
    }

    #[Test]
    public function the_result_header_groups_the_passing_bands_apart_from_the_failing_ones(): void
    {
        $failingFirst = $this->actingAs($this->desk)->get(route('admin.reference-data.grade-scale', [
            'sort' => 'isPassing', 'direction' => 'asc',
        ]));
        $failingFirst->assertOk();
        $this->assertSame(['alpha', 'bravo', 'zeta', 'mike'], $this->names($failingFirst));

        $passingFirst = $this->actingAs($this->desk)->get(route('admin.reference-data.grade-scale', [
            'sort' => 'isPassing', 'direction' => 'desc',
        ]));
        $passingFirst->assertOk();
        $this->assertSame(['zeta', 'mike', 'alpha', 'bravo'], $this->names($passingFirst));
    }

    #[Test]
    public function the_description_header_orders_by_the_word_the_desk_reads(): void
    {
        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.grade-scale', [
            'sort' => 'description', 'direction' => 'asc',
        ]));
        $response->assertOk();

        $this->assertSame(['alpha', 'bravo', 'mike', 'zeta'], $this->names($response));
    }

    #[Test]
    public function only_the_three_painted_columns_are_offered_and_the_ceiling_still_arrives(): void
    {
        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.grade-scale'));
        $response->assertOk();
        $props = $this->props($response);

        // `maxGrade` is painted inside the same cell as the floor and never on its own, so no header asks for it.
        $this->assertSame(['range', 'isPassing', 'description'], $props['sort']['available']);
        $this->assertNotNull($props['passingCeiling'], 'the band summary the page prints is still computed');
        $this->assertTrue($props['hasGradeScale']);
    }

    #[Test]
    public function an_unlisted_key_falls_back_to_the_floor_order(): void
    {
        $askedElsewhere = $this->actingAs($this->desk)->get(route('admin.reference-data.grade-scale', [
            'sort' => 'passwordHash',
        ]));
        $askedElsewhere->assertOk();
        $this->assertSame('range', $this->props($askedElsewhere)['sort']['column']);

        $askedPlainly = $this->actingAs($this->desk)->get(route('admin.reference-data.grade-scale', [
            'sort' => 'range',
        ]));
        $askedPlainly->assertOk();
        $this->assertSame($this->names($askedPlainly), $this->names($askedElsewhere));
    }

    #[Test]
    public function a_search_a_passing_filter_and_an_order_are_all_echoed_and_still_narrow(): void
    {
        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.grade-scale', [
            'search' => 'a', 'isPassing' => '1', 'sort' => 'range', 'direction' => 'desc',
        ]));
        $response->assertOk();
        $props = $this->props($response);

        $this->assertSame('a', $props['filters']['search']);
        $this->assertSame('1', (string) $props['filters']['isPassing']);
        $this->assertSame('range', $props['filters']['sort']);
        $this->assertSame('desc', $props['filters']['direction']);
        // Three descriptions carry an 'a' and two of those are passing; the order asks the ceiling band first.
        $this->assertSame(['zeta'], $this->names($response));
    }

    #[Test]
    public function the_floor_is_the_ordered_thing_so_a_tie_break_cannot_shuffle_a_page_boundary(): void
    {
        // Two bands sharing a floor would arrive in either order without the stable key; the handler names the
        // row's own id, so the listing is reproducible across pages.
        $this->band(2.00, 2.25, false, 'kilo');

        $first = $this->actingAs($this->desk)->get(route('admin.reference-data.grade-scale', [
            'sort' => 'range', 'direction' => 'asc', 'search' => 'a',
        ]));
        $second = $this->actingAs($this->desk)->get(route('admin.reference-data.grade-scale', [
            'sort' => 'range', 'direction' => 'asc', 'search' => 'a',
        ]));
        $first->assertOk();
        $second->assertOk();

        $this->assertSame($this->names($first), $this->names($second));
        $this->assertSame(['bravo', 'alpha', 'zeta'], $this->names($first));
    }
}
