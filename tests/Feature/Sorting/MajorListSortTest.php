<?php

namespace Tests\Feature\Sorting;

use App\Models\Academicunits;
use App\Models\Courses;
use App\Models\Majors;
use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Prompt §14's sorting control on the majors list — the seventeenth table wired (§28.3 G-40 stays Open).
 *
 * This is the smallest screen in the family: three painted columns, one search, one filter, and a hop over a key the
 * migration declares NOT NULL with no missing value on live `ems`, so the INNER spelling is the measured answer here
 * rather than the LEFT one the staff directory needed. The fixture is picked so the six orders it can produce — id up,
 * id down, title up, title down, course up, course down — are six different sequences, which is what lets an assertion
 * fail on either surface instead of agreeing with a mutant by accident.
 */
class MajorListSortTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $desk;

    private Courses $midschool;

    private Courses $ace;

    private Courses $zed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $unit = Academicunits::create(['unitName' => 'Colleges', 'unitType' => 'college']);
        $this->midschool = Courses::create([
            'unitId' => $unit->unitId, 'courseCode' => 'MID-1', 'courseName' => 'Midschool',
            'requiresEntranceExam' => false, 'requiresCourseSpecificExam' => false,
            'entranceExamExemptsTransferee' => false, 'requiresRetentionExam' => false,
        ]);
        $this->ace = Courses::create([
            'unitId' => $unit->unitId, 'courseCode' => 'ACE-1', 'courseName' => 'Ace',
            'requiresEntranceExam' => false, 'requiresCourseSpecificExam' => false,
            'entranceExamExemptsTransferee' => false, 'requiresRetentionExam' => false,
        ]);
        $this->zed = Courses::create([
            'unitId' => $unit->unitId, 'courseCode' => 'ZED-1', 'courseName' => 'Zed',
            'requiresEntranceExam' => false, 'requiresCourseSpecificExam' => false,
            'entranceExamExemptsTransferee' => false, 'requiresRetentionExam' => false,
        ]);

        $this->desk = Staffusers::create([
            'employeeNo' => 'EMP-MAJOR-'.uniqid(),
            'firstName' => 'Major',
            'middleName' => '',
            'lastName' => 'Clerk',
            'username' => 'major_clerk_'.uniqid(),
            'passwordHash' => bcrypt('password'),
            'role' => 'admin',
            'email' => 'major.clerk@seait.edu.ph',
            'contactNo' => '09170000000',
            'status' => 'active',
        ]);
        $this->desk->assignRole('SysAdmin');
    }

    private function major(string $name, Courses $course): Majors
    {
        return Majors::create(['courseId' => $course->courseId, 'majorName' => $name]);
    }

    /**
     * Bravo/Zed, Alpha/Ace, Delta/Midschool in that creation order: id up, id down, title up, title down, course up
     * and course down answer six different sequences.
     */
    private function threeMajors(): void
    {
        $this->major('Bravo', $this->zed);
        $this->major('Alpha', $this->ace);
        $this->major('Delta', $this->midschool);
    }

    /** @return array<int, mixed> */
    private function props(mixed $response): array
    {
        return $response->viewData('page')['props'];
    }

    /** @return list<string> the majors in the order the screen listed them */
    private function names(mixed $response): array
    {
        return collect($this->props($response)['majors']['data'])->pluck('majorName')->all();
    }

    #[Test]
    public function the_list_opens_on_the_title_ascending_and_not_on_the_newest_record(): void
    {
        $this->threeMajors();

        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.majors'));
        $response->assertOk();
        $props = $this->props($response);

        $this->assertSame('majorName', $props['sort']['column']);
        $this->assertSame('asc', $props['sort']['direction']);
        $this->assertSame('asc', $props['filters']['direction'], 'the screen is told the default it got');
        $this->assertSame(['Alpha', 'Bravo', 'Delta'], $this->names($response));
    }

    #[Test]
    public function the_second_click_on_the_title_header_reverses_the_sequence(): void
    {
        $this->threeMajors();

        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.majors', [
            'sort' => 'majorName', 'direction' => 'desc',
        ]));
        $response->assertOk();

        $this->assertSame(['Delta', 'Bravo', 'Alpha'], $this->names($response));
        $this->assertSame('majorName', $this->props($response)['filters']['sort']);
    }

    #[Test]
    public function the_course_header_orders_through_the_hop_and_the_row_keeps_one_shape(): void
    {
        $this->threeMajors();

        $asc = $this->actingAs($this->desk)->get(route('admin.reference-data.majors', [
            'sort' => 'course', 'direction' => 'asc',
        ]));
        $asc->assertOk();
        $this->assertSame(['Alpha', 'Delta', 'Bravo'], $this->names($asc));

        $desc = $this->actingAs($this->desk)->get(route('admin.reference-data.majors', [
            'sort' => 'course', 'direction' => 'desc',
        ]));
        $desc->assertOk();
        $this->assertSame(['Bravo', 'Delta', 'Alpha'], $this->names($desc));

        // §28.3 G-51: the hop exists to order, so the select is pinned to `majors`. A column of `courses` must not
        // ride on the row, and the course name reaches the screen through the eager load it already reads.
        $row = $this->props($asc)['majors']['data'][0];
        $this->assertArrayNotHasKey('courseName', $row);
        $this->assertSame('Alpha', $row['majorName']);
        $this->assertSame('Ace', $row['course']['courseName'] ?? null);
    }

    #[Test]
    public function the_id_header_orders_by_the_record_key_when_the_desk_asks_for_it(): void
    {
        $this->threeMajors();

        $asc = $this->actingAs($this->desk)->get(route('admin.reference-data.majors', [
            'sort' => 'majorId', 'direction' => 'asc',
        ]));
        $asc->assertOk();
        // Creation order — and no other header answers this sequence, which is why the fixture was picked this way.
        $this->assertSame(['Bravo', 'Alpha', 'Delta'], $this->names($asc));

        $desc = $this->actingAs($this->desk)->get(route('admin.reference-data.majors', [
            'sort' => 'majorId', 'direction' => 'desc',
        ]));
        $desc->assertOk();
        $this->assertSame(['Delta', 'Alpha', 'Bravo'], $this->names($desc));
    }

    #[Test]
    public function an_unlisted_key_falls_back_to_the_title_without_saying_it_was_an_error(): void
    {
        $this->threeMajors();

        $askedElsewhere = $this->actingAs($this->desk)->get(route('admin.reference-data.majors', [
            'sort' => 'passwordHash',
        ]));
        $askedElsewhere->assertOk();
        $this->assertSame('majorName', $this->props($askedElsewhere)['sort']['column']);

        $askedPlainly = $this->actingAs($this->desk)->get(route('admin.reference-data.majors', [
            'sort' => 'majorName',
        ]));
        $askedPlainly->assertOk();
        $this->assertSame($this->names($askedPlainly), $this->names($askedElsewhere));
    }

    #[Test]
    public function the_three_painted_columns_are_the_only_ones_offered(): void
    {
        $this->threeMajors();

        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.majors'));
        $response->assertOk();

        $this->assertSame(['majorName', 'course', 'majorId'], $this->props($response)['sort']['available']);
    }

    #[Test]
    public function a_course_filter_a_search_and_an_order_are_all_echoed_and_still_narrow(): void
    {
        $this->threeMajors();

        $response = $this->actingAs($this->desk)->get(route('admin.reference-data.majors', [
            'courseId' => $this->zed->courseId, 'search' => 'r', 'sort' => 'majorName', 'direction' => 'desc',
        ]));
        $response->assertOk();
        $props = $this->props($response);

        $this->assertSame((string) $this->zed->courseId, (string) $props['filters']['courseId']);
        $this->assertSame('r', $props['filters']['search']);
        $this->assertSame('majorName', $props['filters']['sort']);
        $this->assertSame('desc', $props['filters']['direction']);
        $this->assertSame('majorName', $props['sort']['column']);
        // Only Bravo sits in the Zed course and carries an r; Alpha and Delta stay out of the list.
        $this->assertSame(['Bravo'], $this->names($response));
    }
}
