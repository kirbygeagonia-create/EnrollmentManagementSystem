<?php

namespace Tests\Feature\Sorting;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EnumColumnOrder;
use Tests\TestCase;

/**
 * Prompt §14's sorting control on the Department Evaluation queue — the ninth table wired (§28.3 G-40 stays open
 * on purpose: the reference-data and user-management screens still offer no order).
 *
 * This desk's list is a workflow population the handler no longer restates: `DeskQueues::evaluation()` holds the
 * enrollments in `pending` or `returnedToEvaluation` inside the desk's own college, so the order is asked of a
 * builder two states wide rather than one. That matters for the ENUM case: `enrollmentStatus` on this queue really
 * can answer two different words, and the school's server orders them by their declaration index while the suite's
 * `enum()` is a varchar with a CHECK and orders the stored word — so the expectation is asked of the connection
 * through `EnumColumnOrder` and never typed here.
 *
 * Every visible column is offered. None is withheld, and that is the finding worth naming: Year Level, Student
 * Type, Proposed Standing and Status are all columns of `enrollments`, Course is one hop, and Name and School ID
 * are one hop to `students`. The two derived numbers on this page (`phaseBadge` and the per-row workflow text) are
 * not table columns and carry no header.
 *
 * The fixture gives the eight orders eight different sequences — lane 88's rule that a target order coinciding with
 * the list's own default lets a mutant which ignores the requested column pass by accident — and one row already
 * left the desk, so the "Evaluated" tile is a non-zero number a late join could have disturbed had the tiles been
 * cloned after the order instead of before it.
 */
class EvaluationQueueSortTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $desk;

    private Academicterms $term;

    private Courses $accountancy;

    private Courses $informationTechnology;

    private Courses $nursing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitName' => 'College of Computing',
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

        $this->accountancy = $this->course($unit->unitId, 'BSBA', 'Accountancy');
        $this->informationTechnology = $this->course($unit->unitId, 'BSIT', 'Information Technology');
        $this->nursing = $this->course($unit->unitId, 'BSN', 'Nursing');

        // An account bounded by no college, so every case below judges the order and not the boundary — the
        // scoping half of this queue is pinned by DashboardQueueCountMatchesDeskTest and CollegeDeskScopeTest.
        $staff = Staffusers::factory()->create([
            'officeId' => 7,
            'unitId' => null,
            'employeeNo' => 'EMP-EVSORT-'.uniqid(),
            'username' => 'evsort_'.uniqid(),
            'email' => 'evsort_'.uniqid().'@example.com',
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

    /**
     * One student and one enrollment on this desk's list.
     *
     * `enrollments.evaluatedBy` is NOT NULL with no default, so a queue row must name the account it is credited
     * to from its first breath.
     */
    private function waiting(Courses $course, string $lastName, string $schoolId, int $yearLevel, string $studentType, string $academicStanding, EnrollmentStatus $status): Enrollments
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
            'email' => 'evsort_'.uniqid().'@example.com',
            'username' => 'evsort_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        return Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $course->courseId,
            'termId' => $this->term->termId,
            'yearLevel' => $yearLevel,
            'studentType' => $studentType,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => $academicStanding,
            'enrollmentStatus' => $status,
            'evaluatedBy' => $this->desk->userId,
        ]);
    }

    /**
     * Created Zulueta / EV-002 / Accountancy / Year 2 / transferee / irregular / Pending, then Bautista / EV-003 /
     * Nursing / Year 3 / shifter / regular / ReturnedToEvaluation, then Magsaysay / EV-001 / Information Technology /
     * Year 1 / firstYear / irregular / Pending.
     *
     * Three surnames, three school numbers, three programs, three year levels, three types, two standings and two
     * queue states — so no two of the eight orders answer the same sequence.
     */
    private function trio(): void
    {
        $this->waiting($this->accountancy, 'Zulueta', 'EV-002', 2, StudentType::Transferee->value, 'irregular', EnrollmentStatus::Pending);
        $this->waiting($this->nursing, 'Bautista', 'EV-003', 3, StudentType::Shifter->value, 'regular', EnrollmentStatus::ReturnedToEvaluation);
        $this->waiting($this->informationTechnology, 'Magsaysay', 'EV-001', 1, StudentType::FirstYear->value, 'irregular', EnrollmentStatus::Pending);
    }

    /**
     * A fourth enrollment that already left this desk. It is not on the list — the queue is two states wide — and
     * it is what makes the "Evaluated" tile a non-zero number rather than a 0 a join could not be seen changing.
     */
    private function handled(): Enrollments
    {
        return $this->waiting($this->accountancy, 'Aquino', 'EV-004', 2, StudentType::Continuing->value, 'regular', EnrollmentStatus::Evaluated);
    }

    /** @return list<array<string, mixed>> */
    private function rows($response): array
    {
        return $response->viewData('page')['props']['enrollments']['data'];
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
    private function courses($response): array
    {
        return array_map(fn (array $row): string => (string) ($row['course']['courseName'] ?? ''), $this->rows($response));
    }

    /** @return list<int> */
    private function years($response): array
    {
        return array_map(fn (array $row): int => (int) $row['yearLevel'], $this->rows($response));
    }

    /** @return list<string> */
    private function column($response, string $key): array
    {
        return array_map(fn (array $row): string => (string) ($row[$key] ?? ''), $this->rows($response));
    }

    private function sort($response): array
    {
        return $response->viewData('page')['props']['sort'];
    }

    #[Test]
    public function the_queue_opens_newest_enrollment_first_when_nothing_is_asked(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)->get(route('evaluation.index'));
        $response->assertOk();

        $this->assertSame(['Magsaysay', 'Bautista', 'Zulueta'], $this->surnames($response));
        $this->assertSame('enrollmentId', $this->sort($response)['column']);
        $this->assertSame('desc', $this->sort($response)['direction']);
    }

    #[Test]
    public function the_queue_can_be_ordered_by_the_students_surname_one_hop_away(): void
    {
        $this->trio();
        $this->handled();

        $response = $this->actingAs($this->desk)
            ->get(route('evaluation.index', ['sort' => 'name', 'direction' => 'asc']));
        $response->assertOk();

        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($response));

        // The five tiles are cloned from the builder BEFORE this join exists, and one of the three listed rows is a
        // record the Registrar sent back while a fourth already left the desk — so the 3 / 2 / 1 / 1 / 2 below are
        // numbers a late join over `students` could have changed. The last pair is two, not one: the tile is
        // "Transferees / Shifters" and this fixture seats one of each, which is the label's own meaning.
        $stats = $response->viewData('page')['props']['stats'];
        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['pending']);
        $this->assertSame(1, $stats['returned']);
        $this->assertSame(1, $stats['evaluated']);
        $this->assertSame(2, $stats['transferees']);
    }

    #[Test]
    public function a_second_click_on_the_same_header_reverses_it(): void
    {
        $this->trio();

        $down = $this->actingAs($this->desk)
            ->get(route('evaluation.index', ['sort' => 'name', 'direction' => 'desc']));
        $down->assertOk();

        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($down));
        $this->assertSame('name', $this->sort($down)['column']);
        $this->assertSame('desc', $this->sort($down)['direction']);
    }

    #[Test]
    public function the_course_column_orders_the_program_and_not_the_student(): void
    {
        $this->trio();

        $up = $this->actingAs($this->desk)
            ->get(route('evaluation.index', ['sort' => 'course', 'direction' => 'asc']));
        $up->assertOk();
        $this->assertSame(['Accountancy', 'Information Technology', 'Nursing'], $this->courses($up));
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($up));

        $down = $this->actingAs($this->desk)
            ->get(route('evaluation.index', ['sort' => 'course', 'direction' => 'desc']));
        $down->assertOk();
        $this->assertSame(['Nursing', 'Information Technology', 'Accountancy'], $this->courses($down));
    }

    #[Test]
    public function the_school_number_column_orders_by_the_number_and_not_by_the_name(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)
            ->get(route('evaluation.index', ['sort' => 'schoolId', 'direction' => 'asc']));
        $response->assertOk();

        $this->assertSame(['EV-001', 'EV-002', 'EV-003'], $this->schoolIds($response));
        $this->assertSame(['Magsaysay', 'Zulueta', 'Bautista'], $this->surnames($response));
    }

    #[Test]
    public function the_year_level_column_orders_the_year_and_not_the_record_key(): void
    {
        $this->trio();

        $up = $this->actingAs($this->desk)
            ->get(route('evaluation.index', ['sort' => 'yearLevel', 'direction' => 'asc']));
        $up->assertOk();
        $this->assertSame([1, 2, 3], $this->years($up));
        $this->assertSame(['Magsaysay', 'Zulueta', 'Bautista'], $this->surnames($up));

        $down = $this->actingAs($this->desk)
            ->get(route('evaluation.index', ['sort' => 'yearLevel', 'direction' => 'desc']));
        $down->assertOk();
        $this->assertSame([3, 2, 1], $this->years($down));
    }

    #[Test]
    public function the_enum_headers_order_the_words_this_connection_sorts_them_in(): void
    {
        // Three types across four declaration positions, two standings, and both of this queue's own states. The
        // sequence is asked of the connection: MySQL reads an ENUM by its declaration index and the suite's
        // varchar-with-CHECK reads the stored word, so a literal typed here would be a reading of whichever server
        // answered it — the defect CI named on the clearance queue.
        $this->trio();

        $types = $this->actingAs($this->desk)
            ->get(route('evaluation.index', ['sort' => 'studentType', 'direction' => 'asc']));
        $types->assertOk();
        $this->assertSame(
            EnumColumnOrder::ascending('enrollments', 'studentType', [
                StudentType::FirstYear->value,
                StudentType::Transferee->value,
                StudentType::Shifter->value,
            ]),
            $this->column($types, 'studentType')
        );

        $standings = $this->actingAs($this->desk)
            ->get(route('evaluation.index', ['sort' => 'academicStanding', 'direction' => 'asc']));
        $standings->assertOk();
        $this->assertSame(
            EnumColumnOrder::ascending('enrollments', 'academicStanding', ['irregular', 'regular', 'irregular']),
            $this->column($standings, 'academicStanding')
        );

        // And reversing the same header reverses exactly that sequence.
        $typesDown = $this->actingAs($this->desk)
            ->get(route('evaluation.index', ['sort' => 'studentType', 'direction' => 'desc']));
        $typesDown->assertOk();
        $this->assertSame(
            array_reverse($this->column($types, 'studentType')),
            $this->column($typesDown, 'studentType')
        );
    }

    #[Test]
    public function the_status_header_orders_both_states_this_queue_lists(): void
    {
        // The point of this desk's list being two states wide: `pending` and `returnedToEvaluation` are both on the
        // page, so the header is not decoration — an order over it separates the records the Registrar sent back
        // from the fresh ones. Asked of the connection, for the same reason as the pair above.
        $this->trio();

        $up = $this->actingAs($this->desk)
            ->get(route('evaluation.index', ['sort' => 'enrollmentStatus', 'direction' => 'asc']));
        $up->assertOk();
        $this->assertSame(
            EnumColumnOrder::ascending('enrollments', 'enrollmentStatus', [
                EnrollmentStatus::Pending->value,
                EnrollmentStatus::Pending->value,
                EnrollmentStatus::ReturnedToEvaluation->value,
            ]),
            $this->column($up, 'enrollmentStatus')
        );
        $this->assertCount(3, $this->rows($up));

        $down = $this->actingAs($this->desk)
            ->get(route('evaluation.index', ['sort' => 'enrollmentStatus', 'direction' => 'desc']));
        $down->assertOk();
        $this->assertSame(array_reverse($this->column($up, 'enrollmentStatus')), $this->column($down, 'enrollmentStatus'));
    }

    #[Test]
    public function every_visible_column_is_offered_and_nothing_else_is(): void
    {
        $this->trio();

        $available = $this->sort($this->actingAs($this->desk)->get(route('evaluation.index')))['available'];

        // Seven headers for seven columns: two reach `students`, one reaches `courses`, four are the enrollment's
        // own. The page's derived numbers — the phase badge and the workflow line — name no column and stay out.
        $this->assertSame(
            ['schoolId', 'name', 'course', 'yearLevel', 'studentType', 'academicStanding', 'enrollmentStatus', 'enrollmentId'],
            $available
        );
        $this->assertNotContains('passwordHash', $available);
        $this->assertNotContains('birthdate', $available);
    }

    #[Test]
    public function a_column_the_handler_never_listed_falls_back_and_never_reaches_the_query(): void
    {
        $this->trio();

        $asked = $this->actingAs($this->desk)
            ->get(route('evaluation.index', ['sort' => 'passwordHash', 'direction' => 'asc']));
        $asked->assertOk();

        $sort = $this->sort($asked);
        $this->assertSame('enrollmentId', $sort['column']);
        $this->assertNotContains('passwordHash', $sort['available']);

        // The fallback is about the COLUMN; a direction the desk asked for is still honoured, so the comparison is
        // against the same request spelled with the real key — not against arithmetic done in this file.
        $explicit = $this->actingAs($this->desk)
            ->get(route('evaluation.index', ['sort' => 'enrollmentId', 'direction' => 'asc']));
        $explicit->assertOk();
        $this->assertSame($this->schoolIds($explicit), $this->schoolIds($asked));
        $this->assertSame(['Zulueta', 'Bautista', 'Magsaysay'], $this->surnames($explicit));
    }

    #[Test]
    public function a_search_and_an_order_are_both_echoed_so_neither_control_resets(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)
            ->get(route('evaluation.index', ['search' => 'Zulueta', 'sort' => 'course', 'direction' => 'asc']));
        $props = $response->viewData('page')['props'];
        $response->assertOk();

        $this->assertSame('Zulueta', $props['filters']['search']);
        $this->assertSame('course', $props['filters']['sort']);
        $this->assertSame('asc', $props['filters']['direction']);
        $this->assertSame('course', $this->sort($response)['column']);
        $this->assertSame(['Zulueta'], $this->surnames($response));

        // The order still arranges the narrowed set. `s` is the letter the search reads in a surname or a given
        // name, so it reaches Bautista and Magsaysay and not Zulueta — and the row that already left this desk
        // (Aquino, `evaluated`) holds no `s` at all, which is why the pair below is two names rather than three:
        // a sort may not pull a record back onto a queue its own state retired.
        $this->handled();
        $both = $this->actingAs($this->desk)
            ->get(route('evaluation.index', ['search' => 's', 'sort' => 'name', 'direction' => 'asc']));
        $both->assertOk();
        $this->assertSame(['Bautista', 'Magsaysay'], $this->surnames($both));
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_not_passed_through(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)
            ->get(route('evaluation.index', ['sort' => 'name', 'direction' => 'asc; drop table students']));
        $response->assertOk();

        $this->assertSame('desc', $this->sort($response)['direction']);
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($response));
    }

    #[Test]
    public function the_queue_loads_the_student_for_the_identity_columns_and_nothing_else(): void
    {
        $this->trio();

        $plain = $this->actingAs($this->desk)->get(route('evaluation.index'));
        $ordered = $this->actingAs($this->desk)
            ->get(route('evaluation.index', ['sort' => 'name', 'direction' => 'asc']));
        $plain->assertOk();
        $ordered->assertOk();

        foreach ([[$plain, 'unsorted'], [$ordered, 'ordered by surname']] as [$response, $label]) {
            $row = $this->rows($response)[0] ?? null;
            $this->assertNotNull($row, "the {$label} evaluation queue answered no row, so the payload says nothing");

            $keys = array_keys($row['student'] ?? []);
            sort($keys);
            $this->assertSame(
                ['firstName', 'lastName', 'middleName', 'schoolIdNumber', 'studentId'],
                $keys,
                "the evaluation desk lists a name and a school number on the {$label} page; birthdate and contact number are the profile's record, not this row's"
            );
        }
    }
}
