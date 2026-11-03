<?php

namespace Tests\Feature\Sorting;

use App\Models\Academicunits;
use App\Models\Courses;
use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Prompt §14's sorting control on the courses catalogue — the sixteenth table wired (§28.3 G-40 stays Open).
 *
 * Two things are new here rather than repeats of the subjects catalogue. Four of the seven painted columns are
 * boolean flags, so the desk can group the programs an office has flagged without reading sixteen rows; a flag is a
 * column the query answers, so it carries a header. And the Unit column is one hop away over a key the migration
 * declares NOT NULL — the check that decides INNER against LEFT is asked of the schema and the rows, not inherited
 * from the screen before it, and the hop is therefore the plain kind with the select pinned by `ListSort`.
 *
 * The fixture is picked so the code order, the id order and the flag order are three different sequences: a mutant
 * that ignores the requested column has to be caught, not accommodated.
 */
class CourseListSortTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $desk;

    private Academicunits $ctic;

    private Academicunits $cte;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->ctic = Academicunits::create(['unitName' => 'Computer Instruction', 'unitType' => 'college']);
        $this->cte = Academicunits::create(['unitName' => 'Teacher Education', 'unitType' => 'college']);

        $this->desk = Staffusers::create([
            'employeeNo' => 'EMP-COURSE-'.uniqid(),
            'firstName' => 'Program',
            'middleName' => '',
            'lastName' => 'Clerk',
            'username' => 'course_clerk_'.uniqid(),
            'passwordHash' => bcrypt('password'),
            'role' => 'admin',
            'email' => 'course.clerk@seait.edu.ph',
            'contactNo' => '09170000000',
            'status' => 'active',
        ]);
        $this->desk->assignRole('SysAdmin');
    }

    /**
     * @param  array<string, bool>  $flags
     */
    private function course(string $code, string $name, Academicunits $unit, array $flags = []): Courses
    {
        return Courses::create(array_merge([
            'unitId' => $unit->unitId,
            'courseCode' => $code,
            'courseName' => $name,
            'requiresEntranceExam' => false,
            'requiresCourseSpecificExam' => false,
            'entranceExamExemptsTransferee' => false,
            'requiresRetentionExam' => false,
        ], $flags));
    }

    /** @return array<int, mixed> */
    private function props(mixed $response): array
    {
        return $response->viewData('page')['props'];
    }

    /** @return list<string> the codes in the order the screen listed them */
    private function codes(mixed $response): array
    {
        return collect($this->props($response)['courses']['data'])->pluck('courseCode')->all();
    }

    #[Test]
    public function the_catalogue_opens_on_the_code_ascending_and_not_on_the_newest_record(): void
    {
        $this->course('BSIT-202', 'Systems Integration', $this->ctic);
        $this->course('BSIT-101', 'Foundations', $this->ctic);
        $this->course('BED-301', 'Curriculum Study', $this->cte);

        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.courses'));
        $response->assertOk();
        $props = $this->props($response);

        $this->assertSame('courseCode', $props['sort']['column']);
        $this->assertSame('asc', $props['sort']['direction']);
        $this->assertSame('asc', $props['filters']['direction'], 'the screen is told the default it got');
        $this->assertSame(['BED-301', 'BSIT-101', 'BSIT-202'], $this->codes($response));
    }

    #[Test]
    public function a_flagged_column_orders_the_rows_the_desk_wants_together(): void
    {
        // Created so that the flag order is neither id order nor code order: the newest row is unflagged, the
        // oldest is flagged, and the middle code is flagged too.
        $this->course('Z-900', 'Unflagged Late', $this->cte);
        $this->course('A-100', 'Flagged First', $this->ctic, ['requiresEntranceExam' => true]);
        $this->course('M-500', 'Flagged Middle', $this->ctic, ['requiresEntranceExam' => true]);

        $flagged = $this->actingAs($this->desk)->get(route('admin.reference-data.courses', [
            'sort' => 'requiresEntranceExam', 'direction' => 'desc',
        ]));
        $flagged->assertOk();
        $this->assertSame(['A-100', 'M-500', 'Z-900'], $this->codes($flagged));

        $flipped = $this->actingAs($this->desk)->get(route('admin.reference-data.courses', [
            'sort' => 'requiresEntranceExam', 'direction' => 'asc',
        ]));
        $flipped->assertOk();
        $this->assertSame(['Z-900', 'A-100', 'M-500'], $this->codes($flipped));
    }

    #[Test]
    public function the_unit_header_orders_through_the_hop_and_the_row_keeps_one_shape(): void
    {
        $this->course('C-100', 'Computer Science', $this->ctic);
        $this->course('T-100', 'Teaching Methods', $this->cte);
        $this->course('B-100', 'Basics', $this->ctic);

        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.courses', [
            'sort' => 'unit', 'direction' => 'asc',
        ]));
        $response->assertOk();

        $order = collect($this->props($response)['courses']['data'])
            ->map(fn (array $r): string => $r['unit']['unitName'].'/'.$r['courseCode'])
            ->all();
        // 'Computer Instruction' precedes 'Teacher Education'; the two rows sharing the first unit are then
        // settled by the tie-break the handler names (the row's own key), which is why the code order inside that
        // pair is the creation order and not an accident of the join.
        $this->assertSame([
            'Computer Instruction/C-100',
            'Computer Instruction/B-100',
            'Teacher Education/T-100',
        ], $order);

        // §28.3 G-51: the hop exists to order, so the select is pinned to `courses`. A column of the unit table
        // must not ride on the row the desk is handed, whichever header was clicked.
        $row = $this->props($response)['courses']['data'][0];
        $this->assertArrayNotHasKey('unitName', $row);
        $this->assertArrayNotHasKey('unitType', $row);
        $this->assertSame('C-100', $row['courseCode']);
    }

    #[Test]
    public function every_flag_and_both_name_columns_carry_headers_and_nothing_else_does(): void
    {
        $this->course('Q-100', 'Queue Check', $this->ctic);

        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.courses'));
        $response->assertOk();

        $this->assertSame([
            'courseCode', 'courseName', 'unit',
            'requiresEntranceExam', 'requiresCourseSpecificExam',
            'entranceExamExemptsTransferee', 'requiresRetentionExam', 'courseId',
        ], $this->props($response)['sort']['available']);
    }

    #[Test]
    public function an_unlisted_key_falls_back_to_the_code_without_saying_it_was_an_error(): void
    {
        $this->course('R-100', 'Fallback One', $this->ctic);
        $this->course('S-200', 'Fallback Two', $this->cte);

        $askedElsewhere = $this->actingAs($this->desk)->get(route('admin.reference-data.courses', [
            'sort' => 'passwordHash',
        ]));
        $askedElsewhere->assertOk();
        $this->assertSame('courseCode', $this->props($askedElsewhere)['sort']['column']);

        // Compared against the fallback asked for openly, with nothing about the direction mixed in — the same
        // equality that caught a case written from arithmetic instead of behaviour.
        $askedPlainly = $this->actingAs($this->desk)->get(route('admin.reference-data.courses', [
            'sort' => 'courseCode',
        ]));
        $askedPlainly->assertOk();
        $this->assertSame($this->codes($askedPlainly), $this->codes($askedElsewhere));
    }

    #[Test]
    public function a_unit_filter_a_search_and_an_order_are_all_three_echoed(): void
    {
        $this->course('U-100', 'Urban Studies', $this->ctic);
        $this->course('U-200', 'Unrelated Science', $this->cte);
        $this->course('V-100', 'Visual Culture', $this->ctic);

        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.courses', [
            'unit' => $this->ctic->unitId, 'search' => 'U', 'sort' => 'courseName', 'direction' => 'desc',
        ]));
        $response->assertOk();
        $props = $this->props($response);

        $this->assertSame((string) $this->ctic->unitId, (string) $props['filters']['unit']);
        $this->assertSame('U', $props['filters']['search']);
        $this->assertSame('courseName', $props['filters']['sort']);
        $this->assertSame('desc', $props['filters']['direction']);
        // The filter still narrows while the order is applied: only the two ctic programs survive, and the cte
        // program U-200 is absent. `Visual Culture` answers too, because LIKE is case-insensitive on both
        // surfaces here — `%U%` matches the lowercase u in 'Culture'. The term discriminates the unit filter,
        // which is the claim this case owns; the first cut of it asserted one row from arithmetic on the letter
        // and the code was right.
        $this->assertSame(
            ['Visual Culture', 'Urban Studies'],
            collect($props['courses']['data'])->pluck('courseName')->all()
        );
        $this->assertNotContains('Unrelated Science', collect($props['courses']['data'])->pluck('courseName')->all());
    }

    #[Test]
    public function the_title_column_orders_by_the_title_and_not_by_the_record_key(): void
    {
        $this->course('K-300', 'Zebra Economics', $this->ctic);
        $this->course('K-100', 'Apple Agriculture', $this->cte);
        $this->course('K-200', 'Mango Mechanics', $this->ctic);

        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.courses', [
            'sort' => 'courseName', 'direction' => 'asc',
        ]));
        $response->assertOk();

        // Codes come out 300, 100, 200 while titles come out A, M, Z — so a list that ignored the requested
        // column cannot satisfy this and either id order at once.
        $this->assertSame(['K-100', 'K-200', 'K-300'], $this->codes($response));
        $this->assertSame('courseName', $this->props($response)['sort']['column']);
    }

    #[Test]
    public function the_second_click_on_a_header_reverses_the_sequence(): void
    {
        $this->course('W-100', 'Water Systems', $this->ctic);
        $this->course('W-200', 'Waste Systems', $this->cte);

        $asc = $this->actingAs($this->desk)->get(route('admin.reference-data.courses', [
            'sort' => 'courseCode', 'direction' => 'asc',
        ]));
        $asc->assertOk();
        $desc = $this->actingAs($this->desk)->get(route('admin.reference-data.courses', [
            'sort' => 'courseCode', 'direction' => 'desc',
        ]));
        $desc->assertOk();

        $this->assertSame(array_reverse($this->codes($asc)), $this->codes($desc));
    }

    #[Test]
    public function the_unit_filter_and_the_unit_header_are_one_request_because_both_name_the_same_key(): void
    {
        $this->course('U-100', 'Urban Studies', $this->ctic);
        $this->course('U-200', 'Unrelated Science', $this->cte);

        // `academicunits` is joined by the Unit header and its primary key is the very column the unit filter
        // asks, so a bare `where('unitId', …)` was ambiguous on that request: MySQL 500, SQLite
        // "ambiguous column name: unitId". The class already sent a unit filter and a sort together — beside the
        // TITLE header, which joins nothing — so the screen's own suite never made the two meet.
        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.courses', [
            'unit' => $this->ctic->unitId, 'sort' => 'unit', 'direction' => 'asc',
        ]));
        $response->assertOk();

        $this->assertSame(['U-100'], $this->codes($response), 'the unit filter did not survive the hop it pairs with');
        $this->assertSame('unit', $this->props($response)['filters']['sort']);
    }
}
