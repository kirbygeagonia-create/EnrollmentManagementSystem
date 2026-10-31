<?php

namespace Tests\Feature\Sorting;

use App\Models\Staffusers;
use App\Models\Subjects;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Prompt §14's sorting control on the subjects catalogue — the fourteenth table wired (§28.3 G-40 stays Open).
 *
 * This is the first wired screen that is not a queue, and the difference is the default rather than the
 * mechanism: `ListSort` answers `desc` when a request carries no direction because queues are read newest-first,
 * so a catalogue states its own ascending default in the action. The other shape new here is that every ordered
 * column is already on `subjects` — no hop, no pinned select, no grouped-star risk on the tiles it does not have.
 *
 * The ENUM case does not name a sequence. `subjectType` is declared `enum('lecture','lab','both')`, MySQL orders
 * an ENUM by that declaration index, and the suite's `enum()` compiles to a varchar with a CHECK and orders the
 * stored word — so the expectation is read from the same connection the screen used, which is the only form that
 * is true on both surfaces.
 */
class SubjectListSortTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $desk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->desk = Staffusers::create([
            'employeeNo' => 'EMP-SUBJ-'.uniqid(),
            'firstName' => 'Reference',
            'middleName' => '',
            'lastName' => 'Clerk',
            'username' => 'subj_clerk_'.uniqid(),
            'passwordHash' => bcrypt('password'),
            'role' => 'admin',
            'email' => 'subj.clerk@seait.edu.ph',
            'contactNo' => '09170000000',
            'status' => 'active',
        ]);
        $this->desk->assignRole('SysAdmin');
    }

    private function subject(string $code, string $name, string $type, float $lec, float $lab): Subjects
    {
        return Subjects::create([
            'subjectCode' => $code,
            'subjectName' => $name,
            'subjectType' => $type,
            'lectureUnits' => $lec,
            'labUnits' => $lab,
        ]);
    }

    /** @return array<int, mixed> */
    private function props(mixed $response): array
    {
        return $response->viewData('page')['props'];
    }

    /** @return list<string> the codes in the order the screen listed them */
    private function codes(mixed $response): array
    {
        return collect($this->props($response)['subjects']['data'])->pluck('subjectCode')->all();
    }

    #[Test]
    public function the_catalogue_opens_on_the_code_ascending_because_it_is_read_not_worked(): void
    {
        $this->subject('CS-102', 'Discrete Structures', 'lecture', 3.0, 0.0);
        $this->subject('CS-101', 'Introduction to Computing', 'lecture', 3.0, 0.0);
        $this->subject('TL-101', 'Readings in Philippine History', 'both', 2.0, 1.0);

        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.subjects'));
        $response->assertOk();
        $props = $this->props($response);

        $this->assertSame('subjectCode', $props['sort']['column']);
        $this->assertSame('asc', $props['sort']['direction']);
        $this->assertSame('asc', $props['filters']['direction'], 'the screen is told the default it got');
        $this->assertSame(['CS-101', 'CS-102', 'TL-101'], $this->codes($response));
    }

    #[Test]
    public function the_title_column_orders_by_the_title_and_not_by_the_record_key(): void
    {
        $this->subject('GE-900', 'Zaragoza Ethics', 'lecture', 1.0, 0.0);
        $this->subject('CS-100', 'Algorithms Early', 'lecture', 2.0, 0.0);
        $this->subject('MS-500', 'Midwifery Practice', 'lab', 1.0, 2.0);

        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.subjects', [
            'sort' => 'subjectName', 'direction' => 'asc',
        ]));
        $response->assertOk();

        // Codes are 900, 100, 500 in this order while titles are A, M, Z — so a sort that ignored the
        // requested column cannot satisfy both this and the id orders by accident.
        $this->assertSame(['CS-100', 'MS-500', 'GE-900'], $this->codes($response));
        $this->assertSame('subjectName', $this->props($response)['sort']['column']);
    }

    #[Test]
    public function the_two_unit_columns_order_and_the_second_click_reverses(): void
    {
        $this->subject('A-100', 'One', 'lecture', 1.0, 5.0);
        $this->subject('B-200', 'Two', 'lecture', 5.0, 1.0);
        $this->subject('C-300', 'Three', 'lab', 3.0, 3.0);

        $lecDesc = $this->actingAs($this->desk)->get(route('admin.reference-data.subjects', [
            'sort' => 'lectureUnits', 'direction' => 'desc',
        ]));
        $lecDesc->assertOk();
        $this->assertSame(['B-200', 'C-300', 'A-100'], $this->codes($lecDesc));

        $labAsc = $this->actingAs($this->desk)->get(route('admin.reference-data.subjects', [
            'sort' => 'labUnits', 'direction' => 'asc',
        ]));
        $labAsc->assertOk();
        $this->assertSame(['B-200', 'C-300', 'A-100'], $this->codes($labAsc));
    }

    #[Test]
    public function an_enum_column_is_offered_and_orders_the_way_this_connection_orders_it(): void
    {
        $this->subject('L-100', 'Lab Course', 'lab', 0.0, 3.0);
        $this->subject('B-100', 'Both Course', 'both', 2.0, 2.0);
        $this->subject('G-100', 'Lecture Course', 'lecture', 3.0, 0.0);

        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.subjects', [
            'sort' => 'subjectType', 'direction' => 'asc',
        ]));
        $response->assertOk();
        $this->assertContains('subjectType', $this->props($response)['sort']['available']);

        $expected = Subjects::query()->orderBy('subjectType')->pluck('subjectCode')->all();
        $this->assertSame(
            $expected,
            $this->codes($response),
            'the screen must order an ENUM the way the same connection does, not a remembered sequence'
        );
    }

    #[Test]
    public function a_derived_column_offers_no_header_and_an_unlisted_key_falls_back_quietly(): void
    {
        $this->subject('D-100', 'Derived Check', 'lecture', 2.0, 1.0);

        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.subjects'));
        $response->assertOk();
        $available = $this->props($response)['sort']['available'];

        // `totalUnits` is added up in the row's own render, so the query holds nothing to order by it.
        $this->assertNotContains('totalUnits', $available);
        $this->assertNotContains('subjectDesc', $available, 'the description is not painted in this list');
        $this->assertSame(
            ['subjectCode', 'subjectName', 'subjectType', 'lectureUnits', 'labUnits', 'subjectId'],
            $available
        );

        $askedElsewhere = $this->actingAs($this->desk)->get(route('admin.reference-data.subjects', [
            'sort' => 'passwordHash', 'direction' => 'desc',
        ]));
        $askedElsewhere->assertOk();
        $this->assertSame('subjectCode', $this->props($askedElsewhere)['sort']['column']);

        // The fallback answers exactly what the fallback column asked for openly — an equality against the
        // queue's own behaviour, never arithmetic done here.
        $askedPlainly = $this->actingAs($this->desk)->get(route('admin.reference-data.subjects', [
            'sort' => 'subjectCode', 'direction' => 'desc',
        ]));
        $askedPlainly->assertOk();
        $this->assertSame($this->codes($askedPlainly), $this->codes($askedElsewhere));
    }

    #[Test]
    public function a_filter_and_an_order_are_both_echoed_so_neither_control_resets(): void
    {
        $this->subject('E-100', 'Engineering Math', 'lecture', 3.0, 0.0);
        $this->subject('E-200', 'Electives Intro', 'lab', 1.0, 3.0);
        $this->subject('F-100', 'Filtered Out', 'both', 2.0, 2.0);

        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.subjects', [
            'search' => 'E-', 'sort' => 'subjectName', 'direction' => 'desc',
        ]));
        $response->assertOk();
        $props = $this->props($response);

        $this->assertSame('E-', $props['filters']['search']);
        $this->assertSame('subjectName', $props['filters']['sort']);
        $this->assertSame('desc', $props['filters']['direction']);
        // The filter still narrows while the order is applied: three keys in, three keys back, two rows shown.
        $this->assertSame(2, $props['subjects']['total']);
        // "Engineering Math" outranks "Electives Intro" because the third letter is n against l — the first cut
        // of this expectation read the two titles in the order they were typed and the code was right.
        $this->assertSame(['E-100', 'E-200'], $this->codes($response));

        $flipped = $this->actingAs($this->desk)->get(route('admin.reference-data.subjects', [
            'search' => 'E-', 'sort' => 'subjectName', 'direction' => 'asc',
        ]));
        $flipped->assertOk();
        $this->assertSame(['E-200', 'E-100'], $this->codes($flipped));
    }

    #[Test]
    public function the_listed_row_carries_no_credential_column(): void
    {
        $this->subject('H-100', 'Health Sciences', 'lecture', 2.0, 0.0);

        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.subjects', [
            'sort' => 'subjectName', 'direction' => 'asc',
        ]));
        $response->assertOk();
        $row = $this->props($response)['subjects']['data'][0];

        // §28.3 G-51's discipline, even where no hop is involved: the listed row is one shape whatever the
        // header clicked. `subjects` holds no credential and the order adds none.
        $this->assertSame('H-100', $row['subjectCode']);
        $this->assertArrayNotHasKey('passwordHash', $row);
    }
}
