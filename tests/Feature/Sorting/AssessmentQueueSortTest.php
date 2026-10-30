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
    private function sheet(string $lastName, string $schoolId, float $assessed, float $owing): Studentassessments
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
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => 'regular',
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

        // Payment Status is painted on the screen from the balance against the total, Course is a name the desk
        // reads but does not work the queue by, and Student Type and Standing belong to the enrollment rather than
        // to the sheet. A header the handler would not obey is the defect §28.3 owns for filters, so the keys are
        // absent rather than quietly ignored.
        foreach (['paymentStatus', 'course', 'studentType', 'academicStanding'] as $notOffered) {
            $this->assertNotContains($notOffered, $available, "{$notOffered} is not a column this query orders");
        }
        $this->assertSame(['name', 'schoolId', 'amount', 'balance', 'assessmentId'], $available);
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
}
