<?php

namespace Tests\Feature\Sorting;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\StaffRole;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Models\Students;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EnumColumnOrder;
use Tests\TestCase;

/**
 * Prompt §14's sorting control on the cashier's collection queue — the sixth table wired (G-40 stays open on
 * purpose), and the first of the two-hop screens that also carries a queue TAB beside its search and its order.
 *
 * The list had one order: newest sheet first. The desk works it by what is owed, so the money columns are the ones
 * a cashier reaches for, and the student's surname sits two joins away — `studentassessments → enrollments →
 * students`. Three controls now have to survive each other: the tab, the search and the order, and each must come
 * back echoed, because a control that resets while the list stays narrowed is the defect §28.3 owns for filters.
 *
 * The fixture gives SIX orders six different sequences (id up, id down, surname, school number, amount, balance,
 * assessment date) — lane 88's lesson that a fixture whose target order agrees with the list's own default lets a
 * mutant that ignores the requested column pass by accident.
 */
class AccountingQueueSortTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $cashier;

    private Academicterms $term;

    private Courses $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        foreach ([1, 2, 3, 4, 5, 11, 22] as $officeId) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => 'Office '.$officeId]);
        }

        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitName' => 'College of Computer Studies',
            'unitType' => 'college',
        ]);

        $year = Academicyears::create([
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-03-31',
        ]);

        $this->term = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);

        $this->course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSIT',
            'courseName' => 'Information Technology',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        $staff = Staffusers::factory()->create([
            'officeId' => OfficeId::Accounting->value,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-ACCTSORT-'.uniqid(),
            'username' => 'acsort_'.uniqid(),
            'email' => 'acsort_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('AccountingStaff');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->cashier = $staff;
    }

    /**
     * One student, one Assessed enrollment, one sheet owing `balance` of an `assessed` total, dated `date`.
     *
     * `paid` moves the account out of the desk's `remainingBalance > 0` queue, so a zero-balance row is what the
     * other tab holds.
     */
    private function sheet(
        string $lastName,
        string $schoolId,
        float $assessed,
        float $balance,
        string $date,
        float $covered = 0,
        string $studentType = 'firstYear',
        string $academicStanding = 'regular',
    ): Studentassessments {
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
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'email' => 'acsort_'.uniqid().'@example.com',
            'username' => 'acsort_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $this->course->courseId,
            'termId' => $this->term->termId,
            'yearLevel' => 1,
            'studentType' => $studentType,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => $academicStanding,
            'enrollmentStatus' => EnrollmentStatus::Assessed,
            'evaluatedBy' => $this->cashier->userId,
        ]);

        return Studentassessments::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'totalAssessedAmount' => $assessed,
            'totalScholarshipCoverage' => $covered,
            'totalWaived' => 0,
            'remainingBalance' => $balance,
            'assessmentDate' => $date,
        ]);
    }

    /**
     * Created Zulueta, Bautista, Magsaysay; numbered 002, 003, 001; assessed 7000, 5000, 6000; owing 1000, 3000,
     * 2000; dated the 5th, the 3rd, the 4th. Six orders, six sequences, and the queue's own default is a seventh.
     */
    private function trio(): void
    {
        $this->sheet('Zulueta', 'AC-002', 7000, 1000, '2026-09-05');
        $this->sheet('Bautista', 'AC-003', 5000, 3000, '2026-09-03');
        $this->sheet('Magsaysay', 'AC-001', 6000, 2000, '2026-09-04');
    }

    /** @return list<array<string, mixed>> */
    private function rows($response): array
    {
        return $response->viewData('page')['props']['assessments']['data'];
    }

    /** @return list<string> */
    private function surnames($response): array
    {
        return array_map(fn (array $row): string => (string) ($row['enrollment']['student']['lastName'] ?? ''), $this->rows($response));
    }

    /** @return list<string> */
    private function schoolIds($response): array
    {
        return array_map(fn (array $row): string => (string) ($row['enrollment']['student']['schoolIdNumber'] ?? ''), $this->rows($response));
    }

    /** @return list<float> */
    private function amounts($response): array
    {
        return array_map(fn (array $row): float => (float) $row['totalAssessedAmount'], $this->rows($response));
    }

    /** @return list<float> */
    private function balances($response): array
    {
        return array_map(fn (array $row): float => (float) $row['remainingBalance'], $this->rows($response));
    }

    /** @return list<string> */
    private function dates($response): array
    {
        // `assessmentDate` is a date cast, so the payload carries the day as an instant at UTC midnight (lane 50).
        // The order is read from the leading calendar day rather than from the whole string.
        return array_map(fn (array $row): string => substr((string) $row['assessmentDate'], 0, 10), $this->rows($response));
    }

    private function sort($response): array
    {
        return $response->viewData('page')['props']['sort'];
    }

    #[Test]
    public function the_queue_opens_newest_sheet_first_when_nothing_is_asked(): void
    {
        $this->trio();

        $response = $this->actingAs($this->cashier)->get(route('accounting.index'));

        $this->assertSame(['Magsaysay', 'Bautista', 'Zulueta'], $this->surnames($response));
        $this->assertSame('assessmentId', $this->sort($response)['column']);
        $this->assertSame('desc', $this->sort($response)['direction']);
    }

    #[Test]
    public function the_queue_can_be_ordered_by_the_students_surname_across_two_hops(): void
    {
        $this->trio();

        $response = $this->actingAs($this->cashier)
            ->get(route('accounting.index', ['sort' => 'name', 'direction' => 'asc']));

        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($response));

        // Both money tiles and both tab counts are taken from the builder BEFORE this join exists, so a surname
        // order cannot make a peso figure or a queue count depend on a table the desk only asked to order by.
        $props = $response->viewData('page')['props'];
        $this->assertSame(18000.0, (float) $props['summary']['totalAssessed']);
        $this->assertSame(6000.0, (float) $props['summary']['totalBalance']);
        $this->assertSame(3, $props['queueCounts']['due']);
        $this->assertSame(0, $props['queueCounts']['no_balance']);
    }

    #[Test]
    public function a_second_click_on_the_same_header_reverses_it(): void
    {
        $this->trio();

        $down = $this->actingAs($this->cashier)
            ->get(route('accounting.index', ['sort' => 'name', 'direction' => 'desc']));

        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($down));
        $this->assertSame('name', $this->sort($down)['column']);
        $this->assertSame('desc', $this->sort($down)['direction']);
    }

    #[Test]
    public function the_money_columns_order_the_amounts_and_the_two_of_them_are_different_sequences(): void
    {
        $this->trio();

        $byAmount = $this->actingAs($this->cashier)
            ->get(route('accounting.index', ['sort' => 'amount', 'direction' => 'asc']));
        $this->assertSame([5000.0, 6000.0, 7000.0], $this->amounts($byAmount));
        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($byAmount));

        // Zulueta owes the least although it was billed the most, so the balance order is neither the amount order
        // nor the surname order. A handler that read the wrong entry out of its own list could satisfy one of them.
        $byBalance = $this->actingAs($this->cashier)
            ->get(route('accounting.index', ['sort' => 'balance', 'direction' => 'asc']));
        $this->assertSame([1000.0, 2000.0, 3000.0], $this->balances($byBalance));
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($byBalance));
    }

    #[Test]
    public function the_assessment_date_column_orders_the_day_the_sheet_was_raised(): void
    {
        $this->trio();

        $response = $this->actingAs($this->cashier)
            ->get(route('accounting.index', ['sort' => 'date', 'direction' => 'asc']));

        $this->assertSame(['2026-09-03', '2026-09-04', '2026-09-05'], $this->dates($response));
        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($response));
    }

    #[Test]
    public function the_school_number_column_orders_by_the_number_and_not_by_the_name(): void
    {
        $this->trio();

        $response = $this->actingAs($this->cashier)
            ->get(route('accounting.index', ['sort' => 'schoolId', 'direction' => 'asc']));

        $this->assertSame(['AC-001', 'AC-002', 'AC-003'], $this->schoolIds($response));
        $this->assertSame(['Magsaysay', 'Zulueta', 'Bautista'], $this->surnames($response));
    }

    #[Test]
    public function a_column_the_handler_never_listed_falls_back_and_never_reaches_the_query(): void
    {
        $this->trio();

        $asked = $this->actingAs($this->cashier)
            ->get(route('accounting.index', ['sort' => 'passwordHash', 'direction' => 'asc']));

        $sort = $this->sort($asked);
        $this->assertSame('assessmentId', $sort['column']);
        $this->assertNotContains('passwordHash', $sort['available']);

        // The fallback is about the COLUMN; a direction the desk asked for is still honoured, so the comparison is
        // against the same request spelled with the real key — not against arithmetic done in this file.
        $explicit = $this->actingAs($this->cashier)
            ->get(route('accounting.index', ['sort' => 'assessmentId', 'direction' => 'asc']));
        $this->assertSame($this->schoolIds($explicit), $this->schoolIds($asked));
        $this->assertSame(['Zulueta', 'Bautista', 'Magsaysay'], $this->surnames($explicit));
    }

    #[Test]
    public function a_derived_column_is_not_offered_because_the_query_cannot_answer_it(): void
    {
        $this->trio();

        $available = $this->sort($this->actingAs($this->cashier)->get(route('accounting.index')))['available'];

        // 'Covered By' prints a SUM of two columns and Course names a program the cashier does not work by, so the
        // query cannot answer either. Student Type and Standing left this list on 2026-10-31, when the owner ruled
        // that an ENUM may be sorted: they sit on the enrollment one hop away, and the hop is what the surname sort
        // already builds. Absent rather than offered-and-ignored is still the rule for what remains here.
        foreach (['coveredBy', 'course'] as $derived) {
            $this->assertNotContains($derived, $available, "{$derived} is not a column this query orders");
        }
        $this->assertSame(
            ['name', 'schoolId', 'amount', 'balance', 'date', 'studentType', 'academicStanding', 'assessmentId'],
            $available
        );
    }

    #[Test]
    public function the_enum_headers_order_the_words_this_connection_sorts_them_in(): void
    {
        // Same shape as the assessment desk: a bare column plus the enrollments hop, so the sequence the screen
        // shows is the one this connection holds rather than the one whoever wrote the test had in mind.
        $this->sheet('Zulueta', 'AC-010', 7000, 1000, '2026-09-01', 0, 'transferee', 'irregular');
        $this->sheet('Bautista', 'AC-011', 5000, 3000, '2026-09-02', 0, 'firstYear', 'regular');
        $this->sheet('Magsaysay', 'AC-012', 6000, 2000, '2026-09-03', 0, 'continuing', 'irregular');

        $types = $this->actingAs($this->cashier)
            ->get(route('accounting.index', ['sort' => 'studentType', 'direction' => 'asc']));
        $this->assertSame(
            EnumColumnOrder::ascending('enrollments', 'studentType', [
                StudentType::FirstYear->value,
                StudentType::Continuing->value,
                StudentType::Transferee->value,
            ]),
            array_map(fn (array $r): string => (string) ($r['enrollment']['studentType'] ?? ''), $this->rows($types))
        );

        $standings = $this->actingAs($this->cashier)
            ->get(route('accounting.index', ['sort' => 'academicStanding', 'direction' => 'asc']));
        $this->assertSame(
            EnumColumnOrder::ascending('enrollments', 'academicStanding', ['regular', 'irregular', 'irregular']),
            array_map(fn (array $r): string => (string) ($r['enrollment']['academicStanding'] ?? ''), $this->rows($standings))
        );
    }

    #[Test]
    public function a_tab_a_search_and_an_order_are_all_echoed_so_no_control_resets(): void
    {
        $this->trio();
        // A covered account that owes nothing, which only the other tab holds.
        $this->sheet('Aquino', 'AC-004', 5000, 0, '2026-09-02', 5000);

        $response = $this->actingAs($this->cashier)
            ->get(route('accounting.index', [
                'queue' => 'no_balance',
                'search' => 'Aquino',
                'sort' => 'amount',
                'direction' => 'asc',
            ]));
        $props = $response->viewData('page')['props'];

        $this->assertSame('no_balance', $props['filters']['queue']);
        $this->assertSame('Aquino', $props['filters']['search']);
        $this->assertSame('amount', $props['filters']['sort']);
        $this->assertSame('asc', $props['filters']['direction']);
        $this->assertSame('amount', $this->sort($response)['column']);
        $this->assertSame(['Aquino'], $this->surnames($response));

        // And the same order applied to the other tab keeps its own rows first — the tab narrows, the order arranges.
        $due = $this->actingAs($this->cashier)
            ->get(route('accounting.index', ['sort' => 'amount', 'direction' => 'asc']));
        $this->assertCount(3, $this->rows($due));
        $this->assertSame([5000.0, 6000.0, 7000.0], $this->amounts($due));
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_not_passed_through(): void
    {
        $this->trio();

        $response = $this->actingAs($this->cashier)
            ->get(route('accounting.index', ['sort' => 'name', 'direction' => 'asc; drop table studentassessments']));

        $this->assertSame('desc', $this->sort($response)['direction']);
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($response));
    }

    #[Test]
    public function an_order_that_joins_a_relation_ships_the_rows_own_columns_and_nothing_else(): void
    {
        // The cashier queue joins the same two hops under a Name order, over the row a receipt is written against.
        // The join exists only to order the page, and a builder with no select list emits `select *`, which then
        // answers the hop's columns too: this row used to arrive carrying the student's passwordHash, birthdate
        // and contact number, and no $hidden on the Students model can withhold them, because the value is not
        // riding on a Students model — it is an attribute of the row being listed. The screen reads those fields
        // off its own eager load, so pinning the select changes nothing the desk asks for and everything it ships.
        $this->sheet('Zulueta', 'AC-020', 7000, 1000, '2026-09-01');
        $this->sheet('Bautista', 'AC-021', 5000, 3000, '2026-09-02');

        $plain = $this->rows($this->actingAs($this->cashier)->get(route('accounting.index')));
        $twoHop = $this->rows($this->actingAs($this->cashier)->get(route('accounting.index', ['sort' => 'name', 'direction' => 'asc'])));
        $oneHop = $this->rows($this->actingAs($this->cashier)->get(route('accounting.index', ['sort' => 'studentType', 'direction' => 'asc'])));

        $keys = function (array $rows): array {
            $out = array_keys($rows[0]);
            sort($out);

            return $out;
        };

        $this->assertSame($keys($plain), $keys($twoHop), 'a Name order must not change which columns a fee-sheet row carries');
        $this->assertSame($keys($plain), $keys($oneHop), 'a Student Type order must not change which columns a fee-sheet row carries');
        foreach (['passwordHash', 'birthdate', 'contactNumber'] as $leaked) {
            $this->assertArrayNotHasKey($leaked, $twoHop[0], "{$leaked} reached the payload through the join");
        }

        $this->actingAs($this->cashier)->get(route('accounting.index'))->assertOk();
        $this->actingAs($this->cashier)->get(route('accounting.index', ['sort' => 'name', 'direction' => 'asc']))->assertOk();
        $this->actingAs($this->cashier)->get(route('accounting.index', ['sort' => 'studentType', 'direction' => 'asc']))->assertOk();
        $student = $twoHop[0]['enrollment']['student'] ?? [];
        $names = array_keys($student);
        sort($names);
        $this->assertSame(['firstName', 'lastName', 'middleName', 'schoolIdNumber', 'studentId'], $names,
            'the cashier queue loads the student for a name and a school number, and for nothing else');
    }
}
