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
 * Prompt §14's sorting control on the assessment desk's fee sheets — the fifth table wired (G-40 stays open on
 * purpose), and the first whose column is two hops away.
 *
 * The queue had one order: newest sheet first. A scholarship officer looking for one student among the Evaluated
 * accounts paged through them. Sorting belongs in the query because the list paginates on the server, and a fee
 * sheet carries neither a surname nor a school number: `studentassessments` points at an `enrollments` row, which
 * points at a `students` row, so a surname order is a two-join chain rather than a column.
 *
 * The fixture is built so that FOUR orders answer with four different sequences — surname, school number, the
 * assessed amount, and the remaining balance — because lane 88 bought the lesson that a fixture whose target order
 * agrees with the list's own default lets a mutant that ignores the requested column pass by accident.
 */
class AssessmentQueueSortTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $officer;

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
            'unitName' => 'College of Computer Science',
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
            'officeId' => OfficeId::Scholarship->value,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-ASORT-'.uniqid(),
            'username' => 'asort_'.uniqid(),
            'email' => 'asort_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('ScholarshipOfficer');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->officer = $staff;
    }

    /**
     * One student, one Evaluated enrollment, one fee sheet with its own two amounts.
     */
    private function sheet(
        string $lastName,
        string $schoolId,
        float $assessed,
        float $owing,
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
            'email' => 'asort_'.uniqid().'@example.com',
            'username' => 'asort_'.uniqid(),
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
            'enrollmentStatus' => EnrollmentStatus::Evaluated,
            'evaluatedBy' => $this->officer->userId,
        ]);

        return Studentassessments::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'totalAssessedAmount' => $assessed,
            'totalScholarshipCoverage' => 0,
            'totalWaived' => 0,
            'remainingBalance' => $owing,
            'assessmentDate' => '2026-09-01',
        ]);
    }

    /**
     * Created Zulueta, Bautista, Magsaysay; numbered 002, 003, 001; assessed 7000, 5000, 6000; owing 1000, 3000, 2000.
     *
     * Four orders, four sequences: surname ascending is Bautista, Magsaysay, Zulueta; the school number ascending is
     * Magsaysay, Zulueta, Bautista; the amount ascending is Bautista, Magsaysay, Zulueta-with-the-largest-bill… which
     * is a different pair from the surname order because Zulueta and Magsaysay swap; and the balance ascending puts
     * Zulueta first. No order that ignores the requested column can satisfy all of these by coincidence.
     */
    private function trio(): void
    {
        $this->sheet('Zulueta', 'AS-002', 7000, 1000);
        $this->sheet('Bautista', 'AS-003', 5000, 3000);
        $this->sheet('Magsaysay', 'AS-001', 6000, 2000);
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

    private function sort($response): array
    {
        return $response->viewData('page')['props']['sort'];
    }

    #[Test]
    public function the_queue_opens_newest_sheet_first_when_nothing_is_asked(): void
    {
        $this->trio();

        $response = $this->actingAs($this->officer)->get(route('assessment.index'));

        // The order the queue has always had, now stated rather than assumed: no column asked keeps the handler's
        // own fallback and its own descending direction.
        $this->assertSame(['Magsaysay', 'Bautista', 'Zulueta'], $this->surnames($response));
        $this->assertSame('assessmentId', $this->sort($response)['column']);
        $this->assertSame('desc', $this->sort($response)['direction']);
    }

    #[Test]
    public function the_queue_can_be_ordered_by_the_students_surname_across_two_hops(): void
    {
        $this->trio();

        $response = $this->actingAs($this->officer)
            ->get(route('assessment.index', ['sort' => 'name', 'direction' => 'asc']));

        // Not "differently from the default" — the actual surname order, reached through `enrollments` to `students`.
        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($response));

        // The fee tiles are cloned from the builder BEFORE this join exists, so an aggregate cannot start reading a
        // table the desk only asked to order by. Three sheets, one sequence of amounts: ₱18,000 assessed, ₱6,000
        // still owing, every one of them part-paid, none settled.
        $summary = $response->viewData('page')['props']['summary'];
        $this->assertSame(18000.0, (float) $summary['total']);
        $this->assertSame(6000.0, (float) $summary['balance']);
        $this->assertSame(3, $summary['partialCount']);
        $this->assertSame(0, $summary['settledCount']);
    }

    #[Test]
    public function a_second_click_on_the_same_header_reverses_it(): void
    {
        $this->trio();

        $down = $this->actingAs($this->officer)
            ->get(route('assessment.index', ['sort' => 'name', 'direction' => 'desc']));

        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($down));
        $this->assertSame('name', $this->sort($down)['column']);
        $this->assertSame('desc', $this->sort($down)['direction']);
    }

    #[Test]
    public function the_amount_column_orders_the_money_and_not_the_row_key(): void
    {
        $this->trio();

        $response = $this->actingAs($this->officer)
            ->get(route('assessment.index', ['sort' => 'amount', 'direction' => 'asc']));

        $this->assertSame([5000.0, 6000.0, 7000.0], $this->amounts($response));
        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($response));
    }

    #[Test]
    public function the_balance_column_is_a_third_sequence_that_neither_other_order_answers(): void
    {
        $this->trio();

        $up = $this->actingAs($this->officer)
            ->get(route('assessment.index', ['sort' => 'balance', 'direction' => 'asc']));

        // Zulueta owes the least though it was assessed the most, so this sequence is neither the amount order nor
        // the surname order — which is the point: a handler that read the wrong column out of its own list would
        // still satisfy one of those two assertions and fail this one.
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($up));
        $this->assertSame(['AS-002', 'AS-001', 'AS-003'], $this->schoolIds($up));
        $this->assertNotSame($this->amounts($up), [5000.0, 6000.0, 7000.0]);

        $schoolId = $this->actingAs($this->officer)
            ->get(route('assessment.index', ['sort' => 'schoolId', 'direction' => 'asc']));
        $this->assertSame(['AS-001', 'AS-002', 'AS-003'], $this->schoolIds($schoolId));
        $this->assertSame(['Magsaysay', 'Zulueta', 'Bautista'], $this->surnames($schoolId));
    }

    #[Test]
    public function a_column_the_handler_never_listed_falls_back_and_never_reaches_the_query(): void
    {
        $this->trio();

        $asked = $this->actingAs($this->officer)
            ->get(route('assessment.index', ['sort' => 'passwordHash', 'direction' => 'asc']));

        $sort = $this->sort($asked);
        $this->assertSame('assessmentId', $sort['column']);
        $this->assertNotContains('passwordHash', $sort['available']);

        // The fallback is about the COLUMN; a direction the desk asked for is still honoured, so the comparison is
        // against the same request spelled with the real key — not against the unsorted page, which runs the
        // queue's own newest-first default.
        $explicit = $this->actingAs($this->officer)
            ->get(route('assessment.index', ['sort' => 'assessmentId', 'direction' => 'asc']));
        $this->assertSame($this->schoolIds($explicit), $this->schoolIds($asked));
        $this->assertSame(['Zulueta', 'Bautista', 'Magsaysay'], $this->surnames($explicit));
    }

    #[Test]
    public function a_column_the_query_cannot_answer_is_not_offered(): void
    {
        $this->trio();

        $available = $this->sort($this->actingAs($this->officer)->get(route('assessment.index')))['available'];

        // Payment Status is painted on the screen from the balance against the total, so no order over the sheet can
        // answer it, and Course is a name the desk reads but does not work the queue by. Student Type and Standing
        // WERE in this list until the owner ruled 2026-10-31 that an ENUM may be sorted: they live on the enrollment
        // the sheet points at, which is one hop the surname sort already builds — reachability was the rule all
        // along, and the driver was never a good reason.
        foreach (['paymentStatus', 'course'] as $notOffered) {
            $this->assertNotContains($notOffered, $available, "{$notOffered} is not a column this query orders");
        }
        $this->assertSame(
            ['name', 'schoolId', 'amount', 'balance', 'studentType', 'academicStanding', 'assessmentId'],
            $available
        );
    }

    #[Test]
    public function the_enum_headers_reach_the_enrollment_through_the_same_hop_and_order_the_words_this_connection_holds(): void
    {
        // A bare column name with a hop is the shape here: ListSort qualifies it with the join's own alias, so this
        // case is also the only thing that proves the handler did not spell `enrollments.studentType` and ask the
        // query for a table it never joined — which would be a 500 at the desk, not a wrong sequence.
        $this->sheet('Zulueta', 'AS-010', 7000, 1000, 'transferee', 'irregular');
        $this->sheet('Bautista', 'AS-011', 5000, 3000, 'firstYear', 'regular');
        $this->sheet('Magsaysay', 'AS-012', 6000, 2000, 'continuing', 'irregular');

        $types = $this->actingAs($this->officer)
            ->get(route('assessment.index', ['sort' => 'studentType', 'direction' => 'asc']));
        $this->assertSame(
            EnumColumnOrder::ascending('enrollments', 'studentType', [
                StudentType::FirstYear->value,
                StudentType::Continuing->value,
                StudentType::Transferee->value,
            ]),
            array_map(fn (array $r): string => (string) ($r['enrollment']['studentType'] ?? ''), $this->rows($types))
        );

        $standings = $this->actingAs($this->officer)
            ->get(route('assessment.index', ['sort' => 'academicStanding', 'direction' => 'asc']));
        $this->assertSame(
            EnumColumnOrder::ascending('enrollments', 'academicStanding', ['regular', 'irregular', 'irregular']),
            array_map(fn (array $r): string => (string) ($r['enrollment']['academicStanding'] ?? ''), $this->rows($standings))
        );
    }

    #[Test]
    public function a_search_filter_and_an_order_are_both_echoed_so_neither_control_resets(): void
    {
        $this->trio();
        // A fourth sheet whose surname the search below will match, so the filter has something to narrow.
        $this->sheet('Zulueta', 'AS-004', 4000, 4000);

        $response = $this->actingAs($this->officer)
            ->get(route('assessment.index', ['search' => 'Zulueta', 'sort' => 'amount', 'direction' => 'asc']));
        $props = $response->viewData('page')['props'];

        $this->assertSame('amount', $props['filters']['sort']);
        $this->assertSame('asc', $props['filters']['direction']);
        $this->assertSame('Zulueta', $props['filters']['search']);
        $this->assertSame('amount', $this->sort($response)['column']);
        // The filter still narrows the ordered set — the two Zulueta sheets, the smaller bill first — which a
        // control that reset would have painted as the whole queue.
        $this->assertCount(2, $props['assessments']['data']);
        $this->assertSame([4000.0, 7000.0], $this->amounts($response));
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_not_passed_through(): void
    {
        $this->trio();

        $response = $this->actingAs($this->officer)
            ->get(route('assessment.index', ['sort' => 'name', 'direction' => 'asc; drop table studentassessments']));

        $this->assertSame('desc', $this->sort($response)['direction']);
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($response));
    }

    #[Test]
    public function an_order_that_joins_a_relation_ships_the_rows_own_columns_and_nothing_else(): void
    {
        // The fee sheet is the row and the student sits two hops away — the widest join this helper builds.
        // The join exists only to order the page, and a builder with no select list emits `select *`, which then
        // answers the hop's columns too: this row used to arrive carrying the student's passwordHash, birthdate
        // and contact number, and no $hidden on the Students model can withhold them, because the value is not
        // riding on a Students model — it is an attribute of the row being listed. The screen reads those fields
        // off its own eager load, so pinning the select changes nothing the desk asks for and everything it ships.
        $this->sheet('Zulueta', 'AS-020', 7000, 1000);
        $this->sheet('Bautista', 'AS-021', 5000, 3000);

        $plain = $this->rows($this->actingAs($this->officer)->get(route('assessment.index')));
        $twoHop = $this->rows($this->actingAs($this->officer)->get(route('assessment.index', ['sort' => 'name', 'direction' => 'asc'])));
        $oneHop = $this->rows($this->actingAs($this->officer)->get(route('assessment.index', ['sort' => 'studentType', 'direction' => 'asc'])));

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

        $this->actingAs($this->officer)->get(route('assessment.index'))->assertOk();
        $this->actingAs($this->officer)->get(route('assessment.index', ['sort' => 'name', 'direction' => 'asc']))->assertOk();
        $this->actingAs($this->officer)->get(route('assessment.index', ['sort' => 'studentType', 'direction' => 'asc']))->assertOk();
        $student = $twoHop[0]['enrollment']['student'] ?? [];
        $names = array_keys($student);
        sort($names);
        $this->assertSame(['firstName', 'lastName', 'middleName', 'schoolIdNumber', 'studentId'], $names,
            'the queue loads the student for a name and a school number, and for nothing else');
    }
}
