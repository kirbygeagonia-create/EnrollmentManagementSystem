<?php

namespace Tests\Feature\Admin;

use App\Models\Academicunits;
use App\Models\Courses;
use App\Models\Curriculums;
use App\Models\Majors;
use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Prompt §14's sorting control, on the curriculum catalogue — the twenty-seventh table wired to
 * `App\Support\ListSort` (§28.3 G-40, whose owed list is printed by `lane140-measure-wired-screens.php` and
 * cross-checked page for page by `lane215-plan-remaining-screens.php`, never counted from memory).
 *
 * This screen is where the nullable-key rule stops being a preference. `curriculums.majorId` is nullable in the
 * migration AND null on every one of the 16 rows live `ems` holds, so an INNER hop on that header would not put the
 * list in a wrong order — it would return nothing at all, which is the audit log's lesson taken to its absolute
 * case. The Course hop is the opposite reading, proven rather than copied: `courseId` is NOT NULL with a foreign key
 * and the rows answer 0 nulls and 0 orphans.
 *
 * The other thing worth pinning here is the screen's own default. It used to open on `curriculumId` descending,
 * which is the newest-created row first — a queue answer on a catalogue — and both sequences were read off the live
 * table before this was written, so the case asserts the change rather than letting it arrive silently.
 */
class CurriculumCatalogueSortTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function course(string $code, string $name): Courses
    {
        static $n = 0;
        $n++;

        return Courses::create([
            'unitId' => Academicunits::create(['unitName' => 'College '.$n, 'unitType' => 'college'])->unitId,
            'courseCode' => $code,
            'courseName' => $name,
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);
    }

    private function major(int $courseId, string $name): Majors
    {
        return Majors::create(['courseId' => $courseId, 'majorName' => $name]);
    }

    private function curriculum(string $name, int $courseId, ?int $majorId = null, string $year = '2026-06-01'): Curriculums
    {
        return Curriculums::create([
            'curriculumName' => $name,
            'courseId' => $courseId,
            'majorId' => $majorId,
            'effectiveYear' => $year,
        ]);
    }

    private function admin(): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => 7,
            'employeeNo' => 'EMP-CUR-'.uniqid(),
            'username' => 'cur_'.uniqid(),
            'email' => 'cur_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('SysAdmin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    /** @return array<string, mixed> */
    private function props(array $query = []): array
    {
        $response = $this->actingAs($this->admin())->get(route('admin.reference-data.curriculums', $query));
        // A refusal renders as a page too, so every comparison case asks for a 200 before it reads a sequence.
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    /** @return list<string> */
    private function titles(array $props): array
    {
        return array_map(static fn (array $r): string => (string) $r['curriculumName'], $props['curriculums']['data']);
    }

    /** @return list<string> */
    private function courses(array $props): array
    {
        return array_map(static fn (array $r): string => (string) ($r['course']['courseName'] ?? ''), $props['curriculums']['data']);
    }

    #[Test]
    public function the_catalogue_opens_with_the_titles_reading_top_to_bottom(): void
    {
        $c = $this->course('BSIT', 'Information Technology');
        // Created so the id-descending order the screen used is the REVERSE of the title order: a mutant that puts
        // the old default back cannot satisfy this by accident.
        $this->curriculum('Alpha Curriculum', $c->courseId);
        $this->curriculum('Middle Curriculum', $c->courseId);
        $this->curriculum('Zeta Curriculum', $c->courseId);

        $props = $this->props();

        $this->assertCount(3, $props['curriculums']['data'], 'the fixture is not the whole population this claim is about');
        $this->assertSame('curriculumName', $props['sort']['column'], 'the page was not told which order was applied');
        $this->assertSame('asc', $props['sort']['direction'], 'a catalogue with no direction asked for opens ascending');
        $this->assertSame(['Alpha Curriculum', 'Middle Curriculum', 'Zeta Curriculum'], $this->titles($props),
            'the catalogue did not open in title order');
    }

    #[Test]
    public function the_course_header_orders_through_an_inner_hop_that_cannot_drop_a_row(): void
    {
        $auto = $this->course('BSIT', 'Information Technology');
        $info = $this->course('BSN', 'Nursing');
        $nurs = $this->course('BSME', 'Automotive Engineering');

        // Created so that the course KEY order is neither the course NAME order nor the title order. The first cut
        // inserted them Automotive, Information, Nursing — which made `courseId` ascending answer exactly the name
        // sequence the case asserts, and the falsifier's "point the header at the foreign key" leg survived. A
        // fixture whose retired reading agrees with the asserted one is the defect, not the guard.
        $this->curriculum('One', $info->courseId);
        $this->curriculum('Two', $auto->courseId);
        $this->curriculum('Three', $nurs->courseId);

        $ascending = $this->props(['sort' => 'course', 'direction' => 'asc']);
        $descending = $this->props(['sort' => 'course', 'direction' => 'desc']);

        $this->assertSame(['Automotive Engineering', 'Information Technology', 'Nursing'], $this->courses($ascending),
            'the Course header did not order by the name one hop away');
        $this->assertSame(['Nursing', 'Information Technology', 'Automotive Engineering'], $this->courses($descending),
            'the second click did not reverse the hop');
        $this->assertNotSame($this->courses($this->props()), $this->courses($ascending),
            'the course-key order equals the course-name order, so no case here can tell the two hops apart');

        // §28.3 G-51: a join exists to order rows and may not reach the payload.
        $plainKeys = array_keys($this->props()['curriculums']['data'][0]);
        $joinedKeys = array_keys($ascending['curriculums']['data'][0]);
        sort($plainKeys);
        sort($joinedKeys);
        $this->assertSame($plainKeys, $joinedKeys,
            'ordering by Course widened the row: '.implode(',', array_diff($joinedKeys, $plainKeys)).' arrived only with the hop');
        $this->assertArrayNotHasKey('courseName', $ascending['curriculums']['data'][0],
            'the hop leaked its own column onto the curriculum row instead of staying inside the relation');
    }

    #[Test]
    public function the_major_header_keeps_every_curriculum_because_the_key_is_null_on_all_of_them(): void
    {
        // The decisive case for this screen. `curriculums.majorId` is nullable AND null on every row live `ems`
        // holds, so an INNER hop here is not a wrong sequence — it is an empty catalogue. The rows must survive the
        // header that reads a column they do not carry.
        $c = $this->course('BSED', 'Education');
        $this->curriculum('No Major One', $c->courseId);
        $this->curriculum('No Major Two', $c->courseId);
        $with = $this->curriculum('Has A Major', $c->courseId, $this->major($c->courseId, 'Mathematics')->majorId);

        $ascending = $this->props(['sort' => 'major', 'direction' => 'asc']);

        $this->assertCount(3, $ascending['curriculums']['data'],
            'the Major header dropped the curriculums that have no major, which is every row on the school today');
        $this->assertSame($with->curriculumId, $ascending['curriculums']['data'][2]['curriculumId'],
            'the one row that does hold a major is not last under an ascending major-name order');
        $this->assertSame('major', $ascending['sort']['column'], 'the payload did not report the hop it applied');
    }

    #[Test]
    public function the_effective_year_header_orders_by_the_stored_date_and_says_so(): void
    {
        $c = $this->course('BSWA', 'Work Animation');
        // Titles chosen so the DATE order is neither the title order nor either id order: the first cut named these
        // rows Alpha/Middle/Zeta against 2024/2025/2026, which made the two sequences coincide and the case could
        // not tell an ordered read from an unordered one.
        $this->curriculum('AAA Newest', $c->courseId, null, '2026-06-01');
        $this->curriculum('ZZZ Oldest', $c->courseId, null, '2024-06-01');
        $this->curriculum('MMM Middle', $c->courseId, null, '2025-06-01');

        $ascending = $this->props(['sort' => 'effectiveYear', 'direction' => 'asc']);

        $this->assertSame(['ZZZ Oldest', 'MMM Middle', 'AAA Newest'], $this->titles($ascending),
            'Effective Year was not ordered by the stored date — the unit the column holds, not the year the cell prints');
        $this->assertNotSame($this->titles($this->props()), $this->titles($ascending),
            'the date order equals the title order, so this case cannot tell the two apart');
    }

    #[Test]
    public function only_the_five_columns_the_page_paints_are_offered_as_orders(): void
    {
        $c = $this->course('BSIT', 'Information Technology');
        $this->curriculum('Any Curriculum', $c->courseId);

        $available = $this->props()['sort']['available'];
        $sorted = $available;
        sort($sorted);

        // The named absents lead, so a plant that offers a raw key fails on its own claim first.
        $this->assertNotContains('courseId', $available, 'the raw foreign key was offered beside the Course name the desk reads');
        $this->assertNotContains('majorId', $available, 'the raw foreign key was offered beside the Major name the desk reads');
        $this->assertSame(['course', 'curriculumId', 'curriculumName', 'effectiveYear', 'major'], $sorted,
            'an order was offered that the query cannot answer, or a painted column lost its header');
    }

    #[Test]
    public function every_header_the_page_paints_is_a_key_the_action_obeys(): void
    {
        $available = $this->props()['sort']['available'];
        $jsx = (string) file_get_contents(base_path('resources/js/Pages/Admin/ReferenceData/Curriculums.jsx'));

        // `DataTable` renders a header only for a key in `sort.available`, so a `sortKey` the handler does not list
        // is a control that never appears and every figure on the page stays correct.
        $found = preg_match_all("/sortKey: '([A-Za-z]+)'/", $jsx, $m) >= 1 ? $m[1] : [];

        $this->assertNotSame([], $found, 'the page lists no sortable header at all, so this case would prove nothing');
        foreach ($found as $key) {
            $this->assertContains($key, $available, "the page offers a header for [{$key}] and the action would not obey it");
        }
        $this->assertCount(5, $found, 'a painted column lost its header, so the desk cannot order what it can see');
    }

    #[Test]
    public function a_column_the_desk_never_lists_falls_back_to_the_catalogue_order(): void
    {
        $c = $this->course('BSIT', 'Information Technology');
        $this->curriculum('Zeta Curriculum', $c->courseId);
        $this->curriculum('Alpha Curriculum', $c->courseId);

        $default = $this->props();
        $forged = $this->props(['sort' => 'passwordHash']);

        $this->assertSame('curriculumName', $forged['sort']['column'],
            'an unlisted key did not fall back to the column the catalogue is ordered by anyway');
        $this->assertSame($this->titles($default), $this->titles($forged),
            'the fallback answered a different sequence than the catalogue opens with');
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_never_passed_through(): void
    {
        $c = $this->course('BSIT', 'Information Technology');
        $this->curriculum('Alpha Curriculum', $c->courseId);
        $this->curriculum('Zeta Curriculum', $c->courseId);

        $props = $this->props(['sort' => 'curriculumName', 'direction' => '; drop tables']);

        $this->assertSame('desc', $props['sort']['direction'], 'an unusable direction was answered as a direction');
        $this->assertSame(['Zeta Curriculum', 'Alpha Curriculum'], $this->titles($props),
            'the list still came back, ordered by the real column');
    }

    #[Test]
    public function a_search_and_an_order_are_both_echoed_so_neither_control_resets(): void
    {
        $it = $this->course('BSIT', 'Information Technology');
        $nurs = $this->course('BSN', 'Nursing');
        $this->curriculum('IT Core Curriculum', $it->courseId);
        $this->curriculum('IT Capstone Curriculum', $it->courseId);
        $this->curriculum('Nursing Core', $nurs->courseId);

        // Two things this case learned by failing. The term carries no surrounding space because the first cut sent
        // 'IT ' and the echo came back 'IT' — measured, and not explained here beyond noting that the echo is what
        // the server filtered on, so a case must not assert a string the request did not keep. And the third row is
        // named WITHOUT the search term: an earlier cut called it 'Nursing Core Curriculum', which the search
        // correctly matched, so the case was charging the filter with a narrowing the fixture had never asked for.
        $props = $this->props(['search' => 'Curriculum', 'sort' => 'curriculumName', 'direction' => 'desc']);

        $this->assertSame('curriculumName', $props['filters']['sort'],
            'the order the server obeyed never came back, so the header resets while the list stays ordered');
        $this->assertSame('desc', $props['filters']['direction']);
        $this->assertSame('Curriculum', $props['filters']['search']);
        $this->assertSame(['IT Core Curriculum', 'IT Capstone Curriculum'], $this->titles($props),
            'the search did not narrow the set the order arranges');
    }

    #[Test]
    public function a_search_that_reaches_the_course_is_not_undone_by_the_order(): void
    {
        // The catalogue's search groups its alternatives inside one `where`, and the Course header joins the same
        // table for a different reason. Both must survive together.
        $info = $this->course('BSIT', 'Information Technology');
        $auto = $this->course('BSME', 'Automotive Engineering');
        $this->curriculum('Zeta Section', $info->courseId);
        $this->curriculum('Alpha Section', $auto->courseId);

        $props = $this->props(['search' => 'Information', 'sort' => 'curriculumName', 'direction' => 'asc']);

        $this->assertCount(1, $props['curriculums']['data'], 'a course-name search did not reach the curriculum it belongs to');
        $this->assertSame('Zeta Section', $this->titles($props)[0]);
        $this->assertSame('curriculumName', $props['filters']['sort'], 'the order was dropped because the search joined the course');
    }

    #[Test]
    public function the_action_reads_its_own_request_and_no_hard_coded_order_survives(): void
    {
        $src = (string) file_get_contents(base_path('app/Http/Controllers/Admin/ReferenceDataController.php'));
        $start = (int) strpos($src, 'public function curriculums(Request $request): Response');
        $this->assertGreaterThan(0, $start, 'the curriculums action was renamed or moved');
        $end = (int) strpos(substr($src, $start), "\n    public function ");
        $body = substr($src, $start, $end > 0 ? $end : 4000);

        $this->assertStringContainsString("\$request->input('sort', '')", $body);
        $this->assertStringContainsString("\$request->input('direction', 'asc')", $body,
            'a catalogue that defaults to descending opens on its last title, which is a queue answer');
        $this->assertStringContainsString('ListSort::apply(', $body);
        $this->assertStringContainsString("'type' => 'left'", $body,
            'the Major hop lost its LEFT spelling, and with majorId null on every row that empties the catalogue');
        $this->assertStringNotContainsString("->orderByDesc('curriculumId')", $body,
            'the hard-coded newest-first order the mechanism replaced is back, so a header would reset while the list stayed put');
    }
}
