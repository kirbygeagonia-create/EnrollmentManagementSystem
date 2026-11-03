<?php

namespace Tests\Feature\Sorting;

use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Blocks;
use App\Models\Courses;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Models\Subjects;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Prompt §14's sorting control on the Blocking desk — the eleventh table wired (§28.3 G-40 stays open on purpose).
 *
 * This screen is the one where the G-51 select pin does NOT fire, and that is the reason the payload case exists
 * here rather than being copied from the other queues. `Blocks::query()->withCount(...)` leaves the builder holding
 * `["blocks.*", "(select …) as enrolled_subjects_count"]` — measured, not recalled, by
 * `lane116b-probe-withcount-columns.php` — so `ListSort::apply()` finds a non-empty column list and pins nothing.
 * The row still arrives in one shape under every order, because the star Laravel writes is the QUALIFIED
 * `blocks.*`; a screen whose aggregate had left a bare `*` would widen the desk row by the joined table's columns
 * and no gate would notice. The case below asserts exactly that, and says which columns must NOT be there.
 *
 * Two visible columns are withheld and one case names them rather than leaving them to be rediscovered: Term is a
 * label assembled from `terms.semester` and its academic year's `yearLabel` (ordering by one half would not be the
 * order the desk reads), and Available is `maxStudents` less a per-row count, which is no column at all.
 *
 * Capacity is the enrollment places the block holds (`blocks.maxStudents`) — the number the desk decides from — and
 * the fixture gives the five offered orders five different sequences, so a mutant that ignores the asked column
 * cannot agree with the list's own default by accident.
 */
class BlockingQueueSortTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $desk;

    private Academicterms $termA;

    private Academicterms $termB;

    private Courses $accountancy;

    private Courses $informationTechnology;

    private Courses $nursing;

    private Subjects $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $unit = Academicunits::create(['unitName' => 'College of Computing', 'unitType' => 'college']);

        $year = Academicyears::create([
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-03-31',
        ]);

        $this->termA = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);
        $this->termB = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '2nd',
            'startDate' => '2026-11-01',
            'endDate' => '2027-03-31',
        ]);

        $this->accountancy = $this->course($unit->unitId, 'BSBA', 'Accountancy');
        $this->informationTechnology = $this->course($unit->unitId, 'BSIT', 'Information Technology');
        $this->nursing = $this->course($unit->unitId, 'BSN', 'Nursing');

        $this->subject = Subjects::create([
            'subjectCode' => 'CS-101',
            'subjectName' => 'Introduction to Computing',
            'lectureUnits' => 3,
            'labUnits' => 1,
            'subjectType' => 'both',
        ]);

        $staff = Staffusers::factory()->create([
            'officeId' => 5,
            'unitId' => null,
            'employeeNo' => 'EMP-BLKSORT-'.uniqid(),
            'username' => 'blksort_'.uniqid(),
            'email' => 'blksort_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('SysAdmin');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->desk = $staff;
    }

    private function course(int $unitId, string $code, string $name): Courses
    {
        return Courses::create([
            'unitId' => $unitId,
            'courseCode' => $code,
            'courseName' => $name,
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);
    }

    private function block(Courses $course, Academicterms $term, string $name, int $yearLevel, int $capacity): Blocks
    {
        return Blocks::create([
            'courseId' => $course->courseId,
            'termId' => $term->termId,
            'yearLevel' => $yearLevel,
            'blockName' => $name,
            'maxStudents' => $capacity,
            // `scheduleStatus` is an enum declared ('draft','final') with a column default, so the fixture leaves it
            // to the schema: a hand-spelled value is the CHECK constraint's business, not this file's.
        ]);
    }

    /**
     * One student seated in one block.
     *
     * The desk's places are students, not subject rows — `BlockingController::$seats` counts DISTINCT
     * `enrollmentId` and excludes dropped rows — so the tile this fills is the one a person decides from.
     */
    private function seat(Blocks $block, string $lastName): void
    {
        $student = Students::create([
            'schoolIdNumber' => 'BL-'.$lastName,
            'lastName' => $lastName,
            'firstName' => 'Given',
            'middleName' => 'M',
            'suffix' => 'N/A',
            'gender' => 'female',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'email' => 'blksort_'.uniqid().'@example.com',
            'username' => 'blksort_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $block->courseId,
            'termId' => $block->termId,
            'yearLevel' => $block->yearLevel,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'enrollmentStatus' => EnrollmentStatus::Enrolled,
            'evaluatedBy' => $this->desk->userId,
        ]);

        Enrolledsubjects::create([
            // The seat belongs to the BLOCK, not only to the enrollment: `enrolledsubjects.blockId` is nullable and
            // `Blocks::enrolledSubjects()` is a hasMany on it, which is what the roster's count reads. Without this
            // the row exists and the desk still sees an empty block — measured, not assumed.
            'blockId' => $block->blockId,
            'enrollmentId' => $enrollment->enrollmentId,
            'subjectId' => $this->subject->subjectId,
            'status' => EnrolledSubjectStatus::Confirmed,
        ]);
    }

    /**
     * Created BLK-C / Nursing / 1st / Year 3 / 40 places, then BLK-A / Accountancy / 1st / Year 1 / 30,
     * then BLK-B / Information Technology / 2nd / Year 2 / 35. Two of the three hold one seated student.
     */
    private function trio(): void
    {
        $this->seat($this->block($this->nursing, $this->termA, 'BLK-C', 3, 40), 'Reyes');
        $this->block($this->accountancy, $this->termA, 'BLK-A', 1, 30);
        $this->seat($this->block($this->informationTechnology, $this->termB, 'BLK-B', 2, 35), 'Santos');
    }

    /** @return list<array<string, mixed>> */
    private function rows($response): array
    {
        return $response->viewData('page')['props']['blocks']['data'];
    }

    /** @return list<string> */
    private function names($response): array
    {
        return array_map(fn (array $row): string => (string) $row['blockName'], $this->rows($response));
    }

    /** @return list<string> */
    private function courses($response): array
    {
        return array_map(fn (array $row): string => (string) ($row['course']['courseName'] ?? ''), $this->rows($response));
    }

    /** @return list<int> */
    private function column($response, string $key): array
    {
        return array_map(fn (array $row): int => (int) $row[$key], $this->rows($response));
    }

    private function sort($response): array
    {
        return $response->viewData('page')['props']['sort'];
    }

    #[Test]
    public function the_roster_opens_newest_block_first_when_nothing_is_asked(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)->get(route('blocking.index'));
        $response->assertOk();

        $this->assertSame(['BLK-B', 'BLK-A', 'BLK-C'], $this->names($response));
        $this->assertSame('blockId', $this->sort($response)['column']);
        $this->assertSame('desc', $this->sort($response)['direction']);
    }

    #[Test]
    public function the_block_name_column_orders_by_the_name_and_not_by_the_record_key(): void
    {
        $this->trio();

        $up = $this->actingAs($this->desk)
            ->get(route('blocking.index', ['sort' => 'name', 'direction' => 'asc']));
        $up->assertOk();
        $this->assertSame(['BLK-A', 'BLK-B', 'BLK-C'], $this->names($up));

        $down = $this->actingAs($this->desk)
            ->get(route('blocking.index', ['sort' => 'name', 'direction' => 'desc']));
        $down->assertOk();
        $this->assertSame(['BLK-C', 'BLK-B', 'BLK-A'], $this->names($down));
    }

    #[Test]
    public function the_course_column_orders_the_program_through_a_join_the_row_never_shows(): void
    {
        $this->trio();

        $up = $this->actingAs($this->desk)
            ->get(route('blocking.index', ['sort' => 'course', 'direction' => 'asc']));
        $up->assertOk();
        $this->assertSame(['Accountancy', 'Information Technology', 'Nursing'], $this->courses($up));
        $this->assertSame(['BLK-A', 'BLK-B', 'BLK-C'], $this->names($up));

        $down = $this->actingAs($this->desk)
            ->get(route('blocking.index', ['sort' => 'course', 'direction' => 'desc']));
        $down->assertOk();
        $this->assertSame(['Nursing', 'Information Technology', 'Accountancy'], $this->courses($down));
    }

    #[Test]
    public function the_year_level_column_orders_the_year(): void
    {
        $this->trio();

        $up = $this->actingAs($this->desk)
            ->get(route('blocking.index', ['sort' => 'yearLevel', 'direction' => 'asc']));
        $up->assertOk();
        $this->assertSame([1, 2, 3], $this->column($up, 'yearLevel'));
        $this->assertSame(['BLK-A', 'BLK-B', 'BLK-C'], $this->names($up));

        $down = $this->actingAs($this->desk)
            ->get(route('blocking.index', ['sort' => 'yearLevel', 'direction' => 'desc']));
        $down->assertOk();
        $this->assertSame([3, 2, 1], $this->column($down, 'yearLevel'));
    }

    #[Test]
    public function the_capacity_column_orders_the_places_the_block_holds(): void
    {
        $this->trio();

        $up = $this->actingAs($this->desk)
            ->get(route('blocking.index', ['sort' => 'capacity', 'direction' => 'asc']));
        $up->assertOk();
        $this->assertSame([30, 35, 40], $this->column($up, 'maxStudents'));
        $this->assertSame(['BLK-A', 'BLK-B', 'BLK-C'], $this->names($up));

        // And the seat arithmetic the desk reads survives the order: 105 places over three blocks, two filled.
        $stats = $up->viewData('page')['props']['stats'];
        $this->assertSame(3, $stats['blocks']);
        $this->assertSame(105, $stats['capacity']);
        $this->assertSame(2, $stats['enrolled']);
        $this->assertSame(103, $stats['available']);
    }

    #[Test]
    public function the_join_that_orders_a_column_never_reaches_the_payload(): void
    {
        $this->trio();

        $plain = $this->actingAs($this->desk)->get(route('blocking.index'));
        $joined = $this->actingAs($this->desk)->get(route('blocking.index', ['sort' => 'course', 'direction' => 'asc']));
        $plain->assertOk();
        $joined->assertOk();

        // `blocks` holds seven columns and no timestamps — read off `information_schema`, not recalled — so the
        // expected set is those seven plus the seat count the roster itself adds. A joined `courses` column would
        // appear only if the order had been allowed to reach the payload.
        $plainKeys = array_keys($this->rows($plain)[0]);
        $joinedKeys = array_keys($this->rows($joined)[0]);
        sort($plainKeys);
        sort($joinedKeys);

        // The claim is one shape under every order, not a particular list: the row carries its own seven columns,
        // the seat count, and the eager-loaded relations it was always given. Enumerating them here would be a
        // second definition of the payload, and `blocks` has no timestamps while `courses` does — the joined table's
        // columns are the thing that must NOT appear.
        $this->assertSame($plainKeys, $joinedKeys, 'a block row on the ordered page must be the same shape as on the unsorted one (§28.3 G-51)');

        foreach (['courseName', 'courseCode', 'unitId', 'createdAt', 'updatedAt'] as $foreign) {
            $this->assertArrayNotHasKey($foreign, $this->rows($joined)[0], "the courses hop reached the payload as `{$foreign}` — the join exists to order rows only");
        }
    }

    #[Test]
    public function the_two_labels_that_are_no_column_offer_no_header(): void
    {
        $this->trio();

        $available = $this->sort($this->actingAs($this->desk)->get(route('blocking.index')))['available'];

        // Name, Course, Year Level and Capacity are columns the handler can order in SQL; Term is a label built from
        // the term's semester plus its academic year, and Available is `maxStudents` less a per-row count.
        $this->assertSame(['name', 'course', 'yearLevel', 'capacity', 'blockId'], $available);
        $this->assertNotContains('term', $available, 'the term column prints a two-part label no single column holds');
        $this->assertNotContains('available', $available, 'available is computed from the seat count, not stored');
    }

    #[Test]
    public function a_column_the_handler_never_listed_falls_back_and_never_reaches_the_query(): void
    {
        $this->trio();

        $asked = $this->actingAs($this->desk)
            ->get(route('blocking.index', ['sort' => 'passwordHash', 'direction' => 'asc']));
        $asked->assertOk();

        $sort = $this->sort($asked);
        $this->assertSame('blockId', $sort['column']);
        $this->assertNotContains('passwordHash', $sort['available']);

        $explicit = $this->actingAs($this->desk)
            ->get(route('blocking.index', ['sort' => 'blockId', 'direction' => 'asc']));
        $explicit->assertOk();
        $this->assertSame($this->names($explicit), $this->names($asked));
        $this->assertSame(['BLK-C', 'BLK-A', 'BLK-B'], $this->names($explicit));
    }

    #[Test]
    public function a_filter_and_an_order_are_both_echoed_so_neither_control_resets(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)
            ->get(route('blocking.index', ['courseId' => $this->nursing->courseId, 'sort' => 'name', 'direction' => 'asc']));
        $props = $response->viewData('page')['props'];
        $response->assertOk();

        // The echo is the request's own spelling — `$request->only()` answers a query string, so a filter id comes
        // back as text. The claim under test is that the key TRAVELS, not that PHP keeps its type.
        $this->assertSame((string) $this->nursing->courseId, (string) $props['filters']['courseId']);
        $this->assertSame('name', $props['filters']['sort']);
        $this->assertSame('asc', $props['filters']['direction']);
        $this->assertSame('name', $this->sort($response)['column']);
        $this->assertSame(['BLK-C'], $this->names($response));

        // The narrowed set still orders: `BLK-` reaches all three, so the same order over the whole roster holds.
        $all = $this->actingAs($this->desk)
            ->get(route('blocking.index', ['search' => 'BLK-', 'sort' => 'capacity', 'direction' => 'asc']));
        $all->assertOk();
        $this->assertSame(['BLK-A', 'BLK-B', 'BLK-C'], $this->names($all));
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_not_passed_through(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)
            ->get(route('blocking.index', ['sort' => 'name', 'direction' => 'asc; drop table blocks']));
        $response->assertOk();

        $this->assertSame('desc', $this->sort($response)['direction']);
        $this->assertSame(['BLK-C', 'BLK-B', 'BLK-A'], $this->names($response));
    }

    #[Test]
    public function the_course_filter_and_the_course_header_are_one_request_because_both_name_the_same_key(): void
    {
        $this->block($this->accountancy, $this->termA, 'BLK-A', 1, 30);
        $this->block($this->nursing, $this->termA, 'BLK-N', 1, 30);

        // The Course header joins `courses`, whose primary key is the very column the program filter asks with, so a
        // bare `where('courseId', …)` was ambiguous on that one request: MySQL answers 500 and SQLite refuses with
        // "ambiguous column name: courseId". This class sent filters and orders together plenty of times — always
        // beside the NAME header, which joins nothing — which is how a desk-visible refusal survived its own screen.
        $response = $this->actingAs($this->desk)->get(route('blocking.index', [
            'courseId' => $this->accountancy->courseId, 'sort' => 'course', 'direction' => 'asc',
        ]));
        $response->assertOk();

        $this->assertSame(['BLK-A'], $this->names($response), 'the program filter did not survive the hop it pairs with');
        $this->assertSame('course', $this->sort($response)['column']);
    }
}
