<?php

namespace Tests\Feature\Sorting;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\IdRequestReason;
use App\Enums\IdRequestStatus;
use App\Enums\OfficeId;
use App\Enums\StaffRole;
use App\Enums\StudentType;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowStepStatus;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Idrequests;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Models\Workflowsteps;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Prompt §14's sorting control on the ID desk — the eighth table wired (G-40 stays open on purpose).
 *
 * The screen carries the widest echo contract of any desk list: a TAB (queue against issued), a search, and now an
 * order — four keys the desk sent that all four must come back, because a control that resets while the list stays
 * narrowed is the defect §28.3 owns for filters. The tab is not decoration either: it swaps which base the list is
 * built from, so the order has to be applied to the clone the tab chose and never to the bases the two tiles are
 * counted from.
 *
 * Three visible columns are withheld, for two different reasons recorded in the handler: Student Type and Standing
 * are ENUM columns whose sequence depends on which server answered, and ID Status is the status of the row's FIRST
 * request — no order over `enrollments` can answer it without deciding what two requests on one enrollment mean.
 *
 * The fixture gives EIGHT orders eight different sequences, and one enrollment sits on the other tab so a tab switch
 * is seen to keep the order rather than lose it.
 */
class IDQueueSortTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $officer;

    private Academicterms $term;

    private Courses $accountancy;

    private Courses $informationTechnology;

    private Courses $nursing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        foreach ([1, 2, 3, 4, 5, 11, 22] as $officeId) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => 'Office '.$officeId]);
        }

        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitName' => 'College of Engineering',
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

        $staff = Staffusers::factory()->create([
            'officeId' => OfficeId::IdOffice->value,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-IDSORT-'.uniqid(),
            'username' => 'idsort_'.uniqid(),
            'email' => 'idsort_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('IdOfficer');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->officer = $staff;
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
     * One student, one Enrolled enrollment, its workflow walked up to the ID box — signed or still pending.
     *
     * `$idSigned = false` leaves ID the FIRST open box, which is the queue tab; `true` signs it, which is the
     * Issued tab. The seven boxes are the firstYear order the workflow service itself lays out.
     */
    private function deskRow(Courses $course, string $lastName, string $schoolId, bool $idSigned = false): Enrollments
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
            'email' => 'idsort_'.uniqid().'@example.com',
            'username' => 'idsort_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $course->courseId,
            'termId' => $this->term->termId,
            'yearLevel' => 1,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Enrolled,
            'evaluatedBy' => $this->officer->userId,
        ]);

        $workflow = Enrollmentworkflow::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'currentStep' => $idSigned ? 7 : 6,
            'workflowStatus' => $idSigned ? WorkflowStatus::Completed : WorkflowStatus::InProgress,
        ]);

        $signer = Staffusers::where('officeId', OfficeId::Registrar->value)->first() ?? $this->officer;

        foreach ([[4, 1], [3, 2], [2, 3], [1, 4], [5, 5], [11, 6], [22, 7]] as [$officeId, $order]) {
            $done = $order < 7 || $idSigned;
            Workflowsteps::create([
                'workflowId' => $workflow->workflowId,
                'officeId' => $officeId,
                'stepOrder' => $order,
                'stepStatus' => $done ? WorkflowStepStatus::Completed : WorkflowStepStatus::Pending,
                'signedBy' => $done ? $signer->userId : null,
                'signedDate' => $done ? '2026-09-01 08:00:00' : null,
            ]);
        }

        return $enrollment;
    }

    /**
     * The request the desk validates: the row whose status the ID Status column paints.
     */
    private function request(Enrollments $enrollment, IdRequestStatus $status): void
    {
        Idrequests::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'requestReason' => IdRequestReason::NewStudent,
            'emergencyContactName' => 'Emergency Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
            'cardPhotoPath' => 'id-photos/queued.png',
            'requestDate' => '2026-09-08',
            'status' => $status,
        ]);
    }

    /**
     * Queue tab: Zulueta / ID-002 / Accountancy (with a pending request), Bautista / ID-003 / Nursing, then
     * Magsaysay / ID-001 / Information Technology. Issued tab: Roxas / ID-004 / Accountancy, already signed.
     */
    private function fourRows(): void
    {
        $this->request($this->deskRow($this->accountancy, 'Zulueta', 'ID-002'), IdRequestStatus::Pending);
        $this->deskRow($this->nursing, 'Bautista', 'ID-003');
        $this->deskRow($this->informationTechnology, 'Magsaysay', 'ID-001');
        $this->request($this->deskRow($this->accountancy, 'Roxas', 'ID-004', idSigned: true), IdRequestStatus::Validated);
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

    private function props($response): array
    {
        return $response->viewData('page')['props'];
    }

    private function sort($response): array
    {
        return $this->props($response)['sort'];
    }

    #[Test]
    public function the_queue_opens_newest_enrollment_first_when_nothing_is_asked(): void
    {
        $this->fourRows();

        $response = $this->actingAs($this->officer)->get(route('id.index'));

        $this->assertSame(['Magsaysay', 'Bautista', 'Zulueta'], $this->surnames($response));
        $this->assertSame('enrollmentId', $this->sort($response)['column']);
        $this->assertSame('desc', $this->sort($response)['direction']);
    }

    #[Test]
    public function the_queue_can_be_ordered_by_the_students_surname_one_hop_away(): void
    {
        $this->fourRows();

        $response = $this->actingAs($this->officer)
            ->get(route('id.index', ['sort' => 'name', 'direction' => 'asc']));

        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($response));

        // Both tab counts and both tiles come from the two bases, cloned before any join this order introduces, so
        // a surname order cannot make a workflow population depend on the table the desk only asked to order by.
        $props = $this->props($response);
        $this->assertSame(3, $props['tabs']['queue']['count']);
        $this->assertSame(1, $props['tabs']['issued']['count']);
        $this->assertSame(1, $props['stats']['pending']);
        $this->assertSame(1, $props['stats']['validated']);
    }

    #[Test]
    public function a_second_click_on_the_same_header_reverses_it(): void
    {
        $this->fourRows();

        $down = $this->actingAs($this->officer)
            ->get(route('id.index', ['sort' => 'name', 'direction' => 'desc']));

        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($down));
        $this->assertSame('name', $this->sort($down)['column']);
        $this->assertSame('desc', $this->sort($down)['direction']);
    }

    #[Test]
    public function the_course_column_orders_the_program_and_not_the_student(): void
    {
        $this->fourRows();

        $up = $this->actingAs($this->officer)
            ->get(route('id.index', ['sort' => 'course', 'direction' => 'asc']));
        $this->assertSame(['Accountancy', 'Information Technology', 'Nursing'], $this->courses($up));
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($up));

        $down = $this->actingAs($this->officer)
            ->get(route('id.index', ['sort' => 'course', 'direction' => 'desc']));
        $this->assertSame(['Nursing', 'Information Technology', 'Accountancy'], $this->courses($down));
    }

    #[Test]
    public function the_school_number_column_orders_by_the_number_and_not_by_the_name(): void
    {
        $this->fourRows();

        $response = $this->actingAs($this->officer)
            ->get(route('id.index', ['sort' => 'schoolId', 'direction' => 'asc']));

        $this->assertSame(['ID-001', 'ID-002', 'ID-003'], $this->schoolIds($response));
        $this->assertSame(['Magsaysay', 'Zulueta', 'Bautista'], $this->surnames($response));
    }

    #[Test]
    public function the_issued_tab_orders_its_own_set_and_keeps_its_own_rows(): void
    {
        $this->fourRows();

        $response = $this->actingAs($this->officer)
            ->get(route('id.index', ['tab' => 'issued', 'sort' => 'name', 'direction' => 'asc']));

        // One row lives on that tab, so the order is observed as a set question rather than a sequence: the tab
        // chooses the base, and the order is applied to the base the tab chose — not to the other one.
        $this->assertSame(['Roxas'], $this->surnames($response));
        $this->assertSame('issued', $this->props($response)['tab']);
        $this->assertSame('name', $this->sort($response)['column']);
    }

    #[Test]
    public function a_column_the_handler_never_listed_falls_back_and_never_reaches_the_query(): void
    {
        $this->fourRows();

        $asked = $this->actingAs($this->officer)
            ->get(route('id.index', ['sort' => 'passwordHash', 'direction' => 'asc']));

        $sort = $this->sort($asked);
        $this->assertSame('enrollmentId', $sort['column']);
        $this->assertNotContains('passwordHash', $sort['available']);

        // The fallback is about the COLUMN; a direction the desk asked for is still honoured, so the comparison is
        // against the same request spelled with the real key — not against arithmetic done in this file.
        $explicit = $this->actingAs($this->officer)
            ->get(route('id.index', ['sort' => 'enrollmentId', 'direction' => 'asc']));
        $this->assertSame($this->schoolIds($explicit), $this->schoolIds($asked));
        $this->assertSame(['Zulueta', 'Bautista', 'Magsaysay'], $this->surnames($explicit));
    }

    #[Test]
    public function the_enum_columns_and_the_derived_request_status_are_not_offered(): void
    {
        $this->fourRows();

        $available = $this->sort($this->actingAs($this->officer)->get(route('id.index')))['available'];

        // studentType and academicStanding are ENUM columns whose sequence depends on which server answers;
        // idStatus is the status of this row's FIRST request, painted from a relation rather than ordered by a
        // column. Absent rather than offered and ignored — a header the handler would not obey is the defect §28.3
        // owns for filters.
        foreach (['studentType', 'academicStanding', 'idStatus'] as $unorderable) {
            $this->assertNotContains($unorderable, $available, "{$unorderable} is not a column this query orders");
        }
        $this->assertSame(['name', 'schoolId', 'course', 'enrollmentId'], $available);
    }

    #[Test]
    public function a_tab_a_search_and_an_order_are_all_echoed_so_no_control_resets(): void
    {
        $this->fourRows();

        $response = $this->actingAs($this->officer)
            ->get(route('id.index', ['tab' => 'issued', 'search' => 'Roxas', 'sort' => 'course', 'direction' => 'asc']));
        $filters = $this->props($response)['filters'];

        $this->assertSame('issued', $filters['tab']);
        $this->assertSame('Roxas', $filters['search']);
        $this->assertSame('course', $filters['sort']);
        $this->assertSame('asc', $filters['direction']);
        $this->assertSame('course', $this->sort($response)['column']);
        $this->assertSame(['Roxas'], $this->surnames($response));

        // And the same order on the other tab arranges that tab's own rows, so switching never silently drops it.
        $queue = $this->actingAs($this->officer)
            ->get(route('id.index', ['search' => 'a', 'sort' => 'name', 'direction' => 'asc']));
        $queueFilters = $this->props($queue)['filters'];

        $this->assertSame('a', $queueFilters['search']);
        $this->assertSame('name', $queueFilters['sort']);
        $this->assertSame('asc', $queueFilters['direction']);
        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($queue));
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_not_passed_through(): void
    {
        $this->fourRows();

        $response = $this->actingAs($this->officer)
            ->get(route('id.index', ['sort' => 'name', 'direction' => 'asc; drop table idrequests']));

        $this->assertSame('desc', $this->sort($response)['direction']);
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($response));
    }
}
