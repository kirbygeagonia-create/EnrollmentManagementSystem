<?php

namespace Tests\Feature\Sorting;

use App\Enums\ClearanceOverallStatus;
use App\Enums\StaffRole;
use App\Models\Academicterms;
use App\Models\Academicyears;
use App\Models\Clearanceperiods;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentclearances;
use App\Models\Students;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EnumColumnOrder;
use Tests\TestCase;

/**
 * Prompt §14's sorting control on the clearance queue — the fourth table wired (G-40 stays open on purpose).
 *
 * The queue had one order: newest clearance first. A desk hunting for one student among the released records paged.
 * The order belongs in the query because the list paginates on the server, and this file also pins the two shapes
 * that queue carries and the directories do not: its tiles are cloned from the builder BEFORE any join is added,
 * and three of its columns — Academic Term, Student Type, Standing — cannot be ordered in SQL at all, because the
 * standing pair is derived per page from the enrollment map rather than stored on the clearance row. A header the
 * handler would not obey is the defect §28.3 owns for filters, so those keys are absent from `sort.available`
 * rather than quietly ignored.
 */
class ClearanceQueueSortTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $officer;

    private Clearanceperiods $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        foreach ([1, 2, 3, 4, 5, 11, 22] as $officeId) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => 'Office '.$officeId]);
        }
        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $year = Academicyears::create([
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-03-31',
        ]);
        $term = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);
        $this->period = Clearanceperiods::create([
            'termId' => $term->termId,
            'clearanceStartDate' => '2026-09-01',
            'clearanceEndDate' => '2026-10-31',
            'periodStatus' => 'open',
        ]);

        $staff = Staffusers::factory()->create([
            'officeId' => 1,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-CSORT-'.uniqid(),
            'username' => 'csort_'.uniqid(),
            'email' => 'csort_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('OfficeHead');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->officer = $staff;
    }

    private function clearance(string $lastName, string $schoolId, string $status, ?string $received, ?int $periodId = null): Studentclearances
    {
        $student = Students::create([
            'schoolIdNumber' => $schoolId,
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
            'email' => 'csort_'.uniqid().'@example.com',
            'username' => 'csort_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        return Studentclearances::create([
            'studentId' => $student->studentId,
            'clearancePeriodId' => $periodId ?? $this->period->clearancePeriodId,
            'overallStatus' => $status,
            'receivedDate' => $received,
        ]);
    }

    /**
     * Created in one order, numbered in another, and received in a third.
     *
     * Three mutually different sequences, so no mutant that ignores the requested column can satisfy an assertion
     * by accident — the lesson lane 88 bought when a surviving leg turned out to be the fixture.
     */
    private function trio(): void
    {
        $this->clearance('Zulueta', 'CS-002', ClearanceOverallStatus::Pending->value, '2026-09-03 08:00:00');
        $this->clearance('Bautista', 'CS-003', ClearanceOverallStatus::Approved->value, '2026-09-01 08:00:00');
        $this->clearance('Magsaysay', 'CS-001', ClearanceOverallStatus::Rejected->value, '2026-09-02 08:00:00');
    }

    /** @return list<array<string, mixed>> */
    private function rows($response): array
    {
        return $response->viewData('page')['props']['clearances']['data'];
    }

    /** @return list<string> */
    private function surnames($response): array
    {
        return array_map(fn (array $row): string => (string) ($row['student']['lastName'] ?? ''), $this->rows($response));
    }

    /** @return list<string> */
    private function schoolIds($response): array
    {
        return array_map(fn (array $row): string => (string) ($row['student']['schoolIdNumber'] ?? ''), $this->rows($response));
    }

    /** @return list<string> */
    private function statuses($response): array
    {
        return array_map(fn (array $row): string => (string) $row['overallStatus'], $this->rows($response));
    }

    private function sort($response): array
    {
        return $response->viewData('page')['props']['sort'];
    }

    #[Test]
    public function the_queue_opens_newest_first_when_nothing_is_asked(): void
    {
        $this->trio();

        $response = $this->actingAs($this->officer)->get(route('clearance.index'));

        // The order the queue has always had, now stated rather than assumed: a request that names no column keeps
        // the handler's own fallback and its own direction.
        $this->assertSame(['Magsaysay', 'Bautista', 'Zulueta'], $this->surnames($response));
        $this->assertSame('studentClearanceId', $this->sort($response)['column']);
        $this->assertSame('desc', $this->sort($response)['direction']);
    }

    #[Test]
    public function the_queue_can_be_ordered_by_the_students_surname(): void
    {
        $this->trio();

        $response = $this->actingAs($this->officer)
            ->get(route('clearance.index', ['sort' => 'name', 'direction' => 'asc']));

        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($response));
        // A surname sort joins the student table; the tiles are cloned before that join exists, so a status count
        // cannot start depending on a table the desk only asked to order by. One row of each of these three
        // statuses exists, and each tile still says one.
        $stats = $response->viewData('page')['props']['stats'];
        $this->assertSame(1, $stats['pending']);
        $this->assertSame(1, $stats['approved']);
        $this->assertSame(1, $stats['rejected']);
    }

    #[Test]
    public function a_second_click_on_the_same_header_reverses_it(): void
    {
        $this->trio();

        $down = $this->actingAs($this->officer)
            ->get(route('clearance.index', ['sort' => 'name', 'direction' => 'desc']));

        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($down));
        $this->assertSame('name', $this->sort($down)['column']);
        $this->assertSame('desc', $this->sort($down)['direction']);
    }

    #[Test]
    public function the_school_number_column_orders_by_the_number_and_not_by_the_name(): void
    {
        $this->trio();

        $response = $this->actingAs($this->officer)
            ->get(route('clearance.index', ['sort' => 'schoolId', 'direction' => 'asc']));

        $this->assertSame(['CS-001', 'CS-002', 'CS-003'], $this->schoolIds($response));
        $this->assertSame(['Magsaysay', 'Zulueta', 'Bautista'], $this->surnames($response));
    }

    #[Test]
    public function a_status_sort_orders_the_way_the_column_sorts_and_the_sequence_is_asked_of_the_server(): void
    {
        // Created Pending, then Rejected, then Approved, so that FOUR orders answer with four different sequences:
        //   id ascending  = pending, rejected, approved  (the creation order)
        //   id descending = approved, rejected, pending  (the queue's own default)
        //   MySQL         = pending, approved, rejected  (an ENUM sorts by its declaration index)
        //   SQLite suite  = approved, pending, rejected  (`enum()` compiles to a varchar with a CHECK, so the
        //                                                  stored STRING sorts)
        // This box is green and CI's MySQL job was red on the sequence this case remembered, which is lane 88's
        // fixture lesson reached from the driver side: the code orders by the column, the column's order belongs to
        // whichever server stated it, and a literal sequence in a test is neither.
        $this->clearance('Bautista', 'CS-010', ClearanceOverallStatus::Pending->value, '2026-09-05 08:00:00');
        $this->clearance('Zulueta', 'CS-011', ClearanceOverallStatus::Rejected->value, '2026-09-06 08:00:00');
        $this->clearance('Magsaysay', 'CS-012', ClearanceOverallStatus::Approved->value, '2026-09-07 08:00:00');

        $response = $this->actingAs($this->officer)
            ->get(route('clearance.index', ['sort' => 'status', 'direction' => 'asc']));

        $expected = EnumColumnOrder::ascending('studentclearances', 'overallStatus', [
            ClearanceOverallStatus::Pending->value,
            ClearanceOverallStatus::Rejected->value,
            ClearanceOverallStatus::Approved->value,
        ]);
        $this->assertSame($expected, $this->statuses($response));

        // Reversing the same header reverses exactly that sequence — the direction is the other half of the promise.
        $down = $this->actingAs($this->officer)
            ->get(route('clearance.index', ['sort' => 'status', 'direction' => 'desc']));
        $this->assertSame(array_reverse($expected), $this->statuses($down));

        // And on neither driver does the status order coincide with either id order, which is what makes the two
        // assertions above able to fail: a mutant that ignores the asked column returns one of these two.
        $newest = $this->actingAs($this->officer)->get(route('clearance.index'));
        $oldest = $this->actingAs($this->officer)
            ->get(route('clearance.index', ['sort' => 'studentClearanceId', 'direction' => 'asc']));
        $this->assertNotSame($this->statuses($newest), $this->statuses($response));
        $this->assertNotSame($this->statuses($oldest), $this->statuses($response));
    }

    #[Test]
    public function a_column_the_handler_never_listed_falls_back_and_never_reaches_the_query(): void
    {
        $this->trio();

        $asked = $this->actingAs($this->officer)
            ->get(route('clearance.index', ['sort' => 'passwordHash', 'direction' => 'asc']));

        $sort = $this->sort($asked);
        $this->assertSame('studentClearanceId', $sort['column']);
        $this->assertNotContains('passwordHash', $sort['available']);

        // The fallback is about the COLUMN; a direction the desk did ask for is still honoured, so the comparison
        // is against the same request spelled with the real key — not against the unsorted page, which runs the
        // queue's own newest-first default. Asserting equality with THAT was written from arithmetic here and is
        // the mistake lane 91 recorded when a fallback case asked `asc` and then expected the default order.
        $explicit = $this->actingAs($this->officer)
            ->get(route('clearance.index', ['sort' => 'studentClearanceId', 'direction' => 'asc']));
        $this->assertSame($this->schoolIds($explicit), $this->schoolIds($asked));
        // The queue's own key ascending is the creation order, which is what `trio()` documents.
        $this->assertSame(['Zulueta', 'Bautista', 'Magsaysay'], $this->surnames($explicit));

        // And with nothing asked at all the queue keeps its own order.
        $plain = $this->actingAs($this->officer)->get(route('clearance.index'));
        $this->assertSame($this->schoolIds($asked), array_reverse($this->schoolIds($plain)));
    }

    #[Test]
    public function a_derived_column_is_not_offered_because_the_query_cannot_answer_it(): void
    {
        $this->trio();

        $available = $this->sort($this->actingAs($this->officer)->get(route('clearance.index')))['available'];

        // Student Type and Standing are set per page from the enrollment standing map, and Academic Term reads two
        // relations away: none is a column of `studentclearances`, so the header renders no control for them.
        foreach (['studentType', 'academicStanding', 'term'] as $derived) {
            $this->assertNotContains($derived, $available, "{$derived} is derived for the page, not ordered by the query");
        }
        $this->assertSame(['name', 'schoolId', 'status', 'received', 'studentClearanceId'], $available);
    }

    #[Test]
    public function a_period_filter_and_an_order_are_both_echoed_so_neither_control_resets(): void
    {
        $this->trio();
        $later = Clearanceperiods::create([
            'termId' => $this->period->termId,
            'clearanceStartDate' => '2026-11-01',
            'clearanceEndDate' => '2026-12-31',
            'periodStatus' => 'closed',
        ]);
        // Filed under the OTHER period, so the filter below has something to exclude.
        $this->clearance('Aquino', 'CS-004', ClearanceOverallStatus::Pending->value, '2026-09-04 08:00:00', $later->clearancePeriodId);

        $response = $this->actingAs($this->officer)
            ->get(route('clearance.index', [
                'periodId' => $this->period->clearancePeriodId,
                'sort' => 'received',
                'direction' => 'asc',
            ]));
        $props = $response->viewData('page')['props'];

        $this->assertSame('received', $props['filters']['sort']);
        $this->assertSame('asc', $props['filters']['direction']);
        $this->assertSame('received', $this->sort($response)['column']);
        // The filter still narrows the ordered set — three of the four records — and the oldest receipt leads it,
        // which is what the other period's row could not do while it was excluded.
        $this->assertCount(3, $props['clearances']['data']);
        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($response));
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_not_passed_through(): void
    {
        $this->trio();

        $response = $this->actingAs($this->officer)
            ->get(route('clearance.index', ['sort' => 'name', 'direction' => 'asc; drop table studentclearances']));

        $this->assertSame('desc', $this->sort($response)['direction']);
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($response));
    }

    #[Test]
    public function the_queue_loads_the_student_for_the_identity_columns_and_nothing_else(): void
    {
        $this->trio();

        $plain = $this->actingAs($this->officer)->get(route('clearance.index'));
        $ordered = $this->actingAs($this->officer)->get(route('clearance.index', ['sort' => 'name', 'direction' => 'asc']));
        $plain->assertOk();
        $ordered->assertOk();

        foreach ([[$plain, 'unsorted'], [$ordered, 'ordered by surname']] as [$response, $label]) {
            $row = $this->rows($response)[0] ?? null;
            $this->assertNotNull($row, "the {$label} clearance queue answered no row, so the payload says nothing");

            $keys = array_keys($row['student'] ?? []);
            sort($keys);
            $this->assertSame(
                ['firstName', 'lastName', 'middleName', 'schoolIdNumber', 'studentId'],
                $keys,
                "the campus clearance list paints a name and a school number on the {$label} page, so those five are the whole student payload"
            );
        }
    }
}
