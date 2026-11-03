<?php

namespace Tests\Feature\Admin;

use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Blocks;
use App\Models\Courses;
use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Prompt §14's sorting control, on the block roster — the twenty-fourth table wired to `App\Support\ListSort`
 * (§28.3 G-40, which stays leading `Open` because the owed list is printed by `lane140-measure-wired-screens.php`
 * and cross-checked page-for-page by `lane215-plan-remaining-screens.php`, not counted from memory).
 *
 * This screen carries the family's two hardest claims at once. The Course header is a real order that lives one hop
 * away, so the join must both produce the sequence and add nothing to the row it orders (§28.3 G-51 — an unpinned
 * `select *` over a hop once shipped a credential hash on a desk row). And the Term header is refused: that cell
 * prints the academic-year label and the semester joined into one string, so there is no column an order could
 * answer, and a header would promise a sequence the desk cannot read back off the label it sees.
 */
class BlockRosterSortTest extends TestCase
{
    use RefreshDatabase;

    private int $termId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $year = Academicyears::create([
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-03-31',
        ]);
        $this->termId = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ])->termId;
    }

    private function unit(string $name): Academicunits
    {
        return Academicunits::create(['unitName' => $name, 'unitType' => 'college']);
    }

    private function course(string $code, string $name): Courses
    {
        static $n = 0;
        $n++;

        return Courses::create([
            'unitId' => $this->unit('College of '.ucfirst($code).' '.$n)->unitId,
            'courseCode' => $code,
            'courseName' => $name,
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);
    }

    private function block(string $name, int $courseId, int $yearLevel = 1, int $maxStudents = 40): Blocks
    {
        return Blocks::create([
            'courseId' => $courseId,
            'termId' => $this->termId,
            'yearLevel' => $yearLevel,
            'blockName' => $name,
            'maxStudents' => $maxStudents,
        ]);
    }

    private function admin(): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => 5,
            'employeeNo' => 'EMP-BLK-'.uniqid(), 'username' => 'blk_'.uniqid(), 'email' => 'blk_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('SysAdmin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    /** @return array<string, mixed> */
    private function props(array $query = []): array
    {
        $response = $this->actingAs($this->admin())->get(route('admin.reference-data.blocks', $query));
        // A refusal renders as a page too, so every comparison case asks for a 200 before it reads a sequence.
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    /** @return list<string> */
    private function names(array $props): array
    {
        return array_map(static fn (array $r): string => (string) $r['blockName'], $props['blocks']['data']);
    }

    /** @return list<string> */
    private function courses(array $props): array
    {
        return array_map(
            static fn (array $r): string => (string) ($r['course']['courseName'] ?? ''),
            $props['blocks']['data']
        );
    }

    #[Test]
    public function the_roster_opens_with_the_block_names_reading_top_to_bottom(): void
    {
        $c = $this->course('BSIT', 'Information Technology');
        // Out of alphabetical order and out of reverse order, so neither id direction satisfies this.
        $this->block('Zeta Section', $c->courseId);
        $this->block('Alpha Section', $c->courseId);
        $this->block('Mabini Section', $c->courseId);

        $props = $this->props();

        // The payload claims lead so a plant against one cannot fail on the other and leave the harness reading a
        // defect it did not name.
        $this->assertCount(3, $props['blocks']['data'], 'the fixture is not the whole population this claim is about');
        $this->assertSame('blockName', $props['sort']['column'], 'the page was not told which order was applied');
        $this->assertSame('asc', $props['sort']['direction'], 'a roster with no direction asked for opens ascending');
        $this->assertSame(['Alpha Section', 'Mabini Section', 'Zeta Section'], $this->names($props),
            'the roster did not open in block-name order');
    }

    #[Test]
    public function the_course_header_orders_through_the_hop_it_names_and_adds_nothing_to_the_row(): void
    {
        $auto = $this->course('BSME', 'Automotive Engineering');
        $info = $this->course('BSIT', 'Information Technology');
        $nurs = $this->course('BSN', 'Nursing');

        // Inserted so the course order differs from both the name order and both id orders.
        $this->block('Bayanihan', $nurs->courseId);
        $first = $this->block('Calambas', $auto->courseId);
        $this->block('Dakila', $info->courseId);
        unset($first);

        $ascending = $this->props(['sort' => 'course', 'direction' => 'asc']);
        $descending = $this->props(['sort' => 'course', 'direction' => 'desc']);

        $this->assertSame(
            ['Automotive Engineering', 'Information Technology', 'Nursing'],
            $this->courses($ascending),
            'the Course header did not order by the name one hop away'
        );
        $this->assertSame(
            ['Nursing', 'Information Technology', 'Automotive Engineering'],
            $this->courses($descending),
            'the second click did not reverse the hop'
        );

        // §28.3 G-51: the join exists to order rows and may not reach the payload. A builder with no select list
        // emits `select *`, and once `courses` is joined that star covers the course too — so a desk row would
        // change shape depending on which header was clicked.
        $plainKeys = array_keys($this->props()['blocks']['data'][0]);
        $joinedKeys = array_keys($ascending['blocks']['data'][0]);
        sort($plainKeys);
        sort($joinedKeys);
        $this->assertSame($plainKeys, $joinedKeys,
            'ordering by Course widened the row: '.implode(',', array_diff($joinedKeys, $plainKeys)).' arrived only with the hop');
        $this->assertArrayNotHasKey('courseName', $ascending['blocks']['data'][0],
            'the hop leaked its own column onto the block row instead of staying inside the relation');
        $this->assertArrayNotHasKey('courseCode', $ascending['blocks']['data'][0],
            'the hop leaked its own column onto the block row instead of staying inside the relation');
    }

    #[Test]
    public function the_max_students_header_orders_by_the_number_and_not_by_the_digit(): void
    {
        $c = $this->course('BSCR', 'Criminology');
        $small = $this->block('Section Nine', $c->courseId, 1, 9);
        $mid = $this->block('Section Twenty', $c->courseId, 1, 20);
        $large = $this->block('Section Hundred', $c->courseId, 1, 100);

        $ascending = $this->props(['sort' => 'maxStudents', 'direction' => 'asc']);

        $this->assertSame([$small->maxStudents, $mid->maxStudents, $large->maxStudents],
            array_map(static fn (array $r): int => (int) $r['maxStudents'], $ascending['blocks']['data']),
            'Max Students was not ordered as a number');
        // Read as a string, 100 would land before 20 and 20 before 9 — so the expectation above is a measurement of
        // the column's type, and the block names differ from the number order again.
        $this->assertSame(['Section Nine', 'Section Twenty', 'Section Hundred'], $this->names($ascending),
            'the numeric order is not the name order, which is what makes the claim above mean something');
    }

    #[Test]
    public function only_the_columns_the_query_holds_are_offered_and_the_compound_term_label_is_not(): void
    {
        $c = $this->course('BSED', 'Education');
        $this->block('One Section', $c->courseId);

        $available = $this->props()['sort']['available'];
        $sorted = $available;
        sort($sorted);

        // The named withholdings lead, so a plant that adds one of them fails on its own claim rather than on the
        // set arithmetic beside it — the difference between a harness reading "an offer appeared" and reading which
        // offer it was.
        $this->assertNotContains('term', $available, 'a header was offered for a compound label with no column behind it');
        $this->assertNotContains('academicYear', $available, 'a header was offered for a two-hop label the roster does not order by');
        $this->assertNotContains('blockId', $available, 'the record key became an order on a screen that paints no id');
        $this->assertNotContains('scheduleStatus', $available, 'a column this screen never paints gained a header');
        $this->assertSame(['blockName', 'course', 'maxStudents', 'yearLevel'], $sorted,
            'an order was offered that the query cannot answer, or a real column lost its header');
    }

    #[Test]
    public function every_header_the_page_paints_is_a_key_the_action_obeys(): void
    {
        $available = $this->props()['sort']['available'];
        $jsx = (string) file_get_contents(base_path('resources/js/Pages/Admin/ReferenceData/Blocks.jsx'));

        // `DataTable` renders a header only for a key in `sort.available`, so a `sortKey` the handler does not list
        // is a control that never appears and every figure on the page stays correct — no backend case sees the loss.
        $found = preg_match_all("/sortKey: '([A-Za-z]+)'/", $jsx, $m) >= 1 ? $m[1] : [];

        $this->assertNotSame([], $found, 'the page lists no sortable header at all, so this case would prove nothing');
        foreach ($found as $key) {
            $this->assertContains($key, $available, "the page offers a header for [{$key}] and the action would not obey it");
        }
        $this->assertCount(4, $found, 'a painted column lost its header, so the desk cannot order what it can see');
        $this->assertNotContains('term', $found, 'the compound year-plus-semester label was given a header the query cannot answer');
    }

    #[Test]
    public function a_column_the_desk_never_lists_falls_back_to_the_roster_order(): void
    {
        $c = $this->course('BSCS', 'Computer Science');
        $this->block('Zeta Section', $c->courseId);
        $this->block('Alpha Section', $c->courseId);

        $default = $this->props();
        // No direction beside the unlisted key: an unlisted key WITH a direction is a legitimate order the desk could
        // ask for, and comparing it to the unsorted request is how one fallback case in this family wrote a wrong
        // expectation over right code.
        $forged = $this->props(['sort' => 'passwordHash']);

        $this->assertSame('blockName', $forged['sort']['column'],
            'an unlisted key did not fall back to the column the roster is ordered by anyway');
        $this->assertSame($this->names($default), $this->names($forged),
            'the fallback answered a different sequence than the roster opens with');
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_never_passed_through(): void
    {
        $c = $this->course('BSMA', 'Management Accounting');
        $this->block('Alpha Section', $c->courseId);
        $this->block('Zeta Section', $c->courseId);

        $props = $this->props(['sort' => 'blockName', 'direction' => '; drop tables']);

        $this->assertSame('desc', $props['sort']['direction'], 'an unusable direction was answered as a direction');
        $this->assertSame(['Zeta Section', 'Alpha Section'], $this->names($props), 'the list still came back, ordered by the real column');
    }

    #[Test]
    public function a_filter_and_an_order_are_both_echoed_so_neither_control_resets(): void
    {
        $intro = $this->course('BSCS', 'Computer Science');
        $nurs = $this->course('BSN', 'Nursing');
        $this->block('CS 101 Section', $intro->courseId);
        $this->block('CS 102 Section', $intro->courseId);
        $this->block('Nursing 1 Section', $nurs->courseId);

        $props = $this->props(['search' => 'CS 10', 'sort' => 'blockName', 'direction' => 'desc']);

        $this->assertSame('blockName', $props['filters']['sort'],
            'the order the server obeyed never came back, so the header resets while the list stays ordered');
        $this->assertSame('desc', $props['filters']['direction']);
        $this->assertSame('CS 10', $props['filters']['search']);
        $this->assertSame(['CS 102 Section', 'CS 101 Section'], $this->names($props),
            'the search narrowed the set the order arranges');
    }

    #[Test]
    public function a_search_that_reaches_the_course_is_not_undone_by_the_order(): void
    {
        // The roster's search already groups its alternatives inside one `where`, and the Course header joins the
        // same table for a different reason. Both must survive together: a grouped OR and a private join alias are
        // two separate ways the filter could stop meaning the desk's words.
        $info = $this->course('BSIT', 'Information Technology');
        $auto = $this->course('BSME', 'Automotive Engineering');
        $this->block('Section One', $info->courseId);
        $this->block('Section Two', $auto->courseId);

        $props = $this->props(['search' => 'Information', 'sort' => 'blockName', 'direction' => 'asc']);

        $this->assertCount(1, $props['blocks']['data'], 'a course-name search did not reach the block it belongs to');
        $this->assertSame('Section One', $this->names($props)[0]);
        $this->assertSame('blockName', $props['filters']['sort'], 'the order was dropped because the search joined the course');
    }

    #[Test]
    public function the_action_reads_its_own_request_and_no_hard_coded_order_survives(): void
    {
        // `lane46-filter-contract.php` judges a key on the action's own line: one consumed a call deeper reads as
        // DEAD-SEND, so the two request values are named here rather than hidden inside the helper.
        $src = (string) file_get_contents(base_path('app/Http/Controllers/Admin/ReferenceDataController.php'));
        $start = (int) strpos($src, 'public function blocks(Request $request): Response');
        $this->assertGreaterThan(0, $start, 'the blocks action was renamed or moved');
        $end = (int) strpos(substr($src, $start), "\n    public function ");
        $body = substr($src, $start, $end > 0 ? $end : 4000);

        $this->assertStringContainsString("\$request->input('sort', '')", $body);
        $this->assertStringContainsString("\$request->input('direction', 'asc')", $body,
            'a roster that defaults to descending opens on the newest block, which is a queue answer');
        $this->assertStringContainsString('ListSort::apply(', $body);
        $this->assertStringNotContainsString("->orderByDesc('blockId')", $body,
            'the hard-coded order the mechanism replaced is back, so a header would reset while the list stayed put');
    }
}
