<?php

namespace Tests\Feature\Sorting;

use App\Enums\ClinicRecordStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\StaffRole;
use App\Enums\StudentType;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowStepStatus;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Clinicrecords;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Models\Workflowsteps;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EnumColumnOrder;
use Tests\TestCase;

/**
 * Prompt §14's sorting control on the clinic queue — the seventh table wired (G-40 stays open on purpose).
 *
 * This queue is the first wired screen whose list is a *workflow* population: `DeskQueues::clinic()` holds the
 * enrollments whose first open box is office 11, so the rows come from a `whereHas` over two joined tables before
 * any order is asked. The order itself reaches one hop, not two: `enrollments → students` for the name and the
 * school number, `enrollments → courses` for the program — which is the shape `ListSort` was grown to accept for
 * the Assessment queue, read back one level.
 *
 * Two visible columns are deliberately NOT offered, and one of the cases exists to say so rather than to pass:
 * Student Type and Standing are ENUM columns, and the school's MySQL orders an ENUM by its declaration index while
 * the suite's `enum()` is a varchar with a CHECK and orders the stored word. Clinic Status is read off whether a
 * record exists on the row, so the query holds nothing to order by.
 *
 * The fixture gives EIGHT orders eight different sequences — lane 88's rule that a target order agreeing with the
 * list's own default lets a mutant that ignores the requested column pass by accident.
 */
class ClinicQueueSortTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $nurse;

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
            'unitName' => 'College of Health Sciences',
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
            'officeId' => OfficeId::Clinic->value,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-CLINSORT-'.uniqid(),
            'username' => 'clsort_'.uniqid(),
            'email' => 'clsort_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('ClinicStaff');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->nurse = $staff;
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
     * One student, one Enrolled enrollment whose first open box is the clinic.
     *
     * The queue is a workflow population, so the row only appears when the six boxes ahead of office 11 are signed
     * and Clinic itself is the lowest-pending `stepOrder` — the same predicate `DeskQueues::waitingAtFirstPendingBox`
     * asks and the dashboard badge is built from.
     */
    private function waitingAtClinic(Courses $course, string $lastName, string $schoolId, string $studentType = 'firstYear', string $academicStanding = 'regular'): Enrollments
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
            'email' => 'clsort_'.uniqid().'@example.com',
            'username' => 'clsort_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $course->courseId,
            'termId' => $this->term->termId,
            'yearLevel' => 1,
            'studentType' => $studentType,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => $academicStanding,
            'enrollmentStatus' => EnrollmentStatus::Enrolled,
            'evaluatedBy' => $this->nurse->userId,
        ]);

        $workflow = Enrollmentworkflow::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'currentStep' => 5,
            'workflowStatus' => WorkflowStatus::InProgress,
        ]);

        $signer = Staffusers::where('officeId', OfficeId::Registrar->value)->first() ?? $this->nurse;

        foreach ([[4, 1], [3, 2], [2, 3], [1, 4], [5, 5], [11, 6], [22, 7]] as [$officeId, $order]) {
            Workflowsteps::create([
                'workflowId' => $workflow->workflowId,
                'officeId' => $officeId,
                'stepOrder' => $order,
                'stepStatus' => $order <= 5 ? WorkflowStepStatus::Completed : WorkflowStepStatus::Pending,
                'signedBy' => $order <= 5 ? $signer->userId : null,
                'signedDate' => $order <= 5 ? '2026-09-01 08:00:00' : null,
            ]);
        }

        return $enrollment;
    }

    /**
     * One clinic record entered but not signed: the desk has weighed the student without signing its own box,
     * which is the state the "Assessments Completed" tile counts and the queue still lists.
     */
    private function assess(Enrollments $enrollment): void
    {
        Clinicrecords::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'heightCm' => 158,
            'weightKg' => 52,
            'bloodPressure' => '110/70',
            'philhealthNumber' => 'PH-'.uniqid(),
            'philhealthRegistered' => true,
            'assessmentNotes' => 'Entered, not yet signed',
            'findings' => 'Normal',
            'clinicStaffId' => $this->nurse->userId,
            'assessmentDate' => '2026-09-10',
            'status' => ClinicRecordStatus::Pending,
        ]);
    }

    /**
     * Created Zulueta / CL-002 / Accountancy, then Bautista / CL-003 / Nursing, then Magsaysay / CL-001 / Information
     * Technology, with one of the three already carrying an entered assessment. No two of the eight orders share a
     * sequence.
     */
    private function trio(): void
    {
        $this->waitingAtClinic($this->accountancy, 'Zulueta', 'CL-002');
        $this->assess($this->waitingAtClinic($this->nursing, 'Bautista', 'CL-003'));
        $this->waitingAtClinic($this->informationTechnology, 'Magsaysay', 'CL-001');
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

    private function sort($response): array
    {
        return $response->viewData('page')['props']['sort'];
    }

    #[Test]
    public function the_queue_opens_newest_enrollment_first_when_nothing_is_asked(): void
    {
        $this->trio();

        $response = $this->actingAs($this->nurse)->get(route('clinic.index'));

        $this->assertSame(['Magsaysay', 'Bautista', 'Zulueta'], $this->surnames($response));
        $this->assertSame('enrollmentId', $this->sort($response)['column']);
        $this->assertSame('desc', $this->sort($response)['direction']);
    }

    #[Test]
    public function the_queue_can_be_ordered_by_the_students_surname_one_hop_away(): void
    {
        $this->trio();

        $response = $this->actingAs($this->nurse)
            ->get(route('clinic.index', ['sort' => 'name', 'direction' => 'asc']));

        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($response));

        // All three tiles are cloned from the builder BEFORE this join exists, so the queue size and the two
        // assessment counts cannot become answers about the `students` table the desk only asked to order by.
        // One of the three already carries an entered assessment, so the 1 below is a non-zero number the join
        // could have changed had the count been taken after it.
        $stats = $response->viewData('page')['props']['stats'];
        $this->assertSame(3, $stats['queue']);
        $this->assertSame(1, $stats['completed']);
        $this->assertSame(2, $stats['pending']);
    }

    #[Test]
    public function a_second_click_on_the_same_header_reverses_it(): void
    {
        $this->trio();

        $down = $this->actingAs($this->nurse)
            ->get(route('clinic.index', ['sort' => 'name', 'direction' => 'desc']));

        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($down));
        $this->assertSame('name', $this->sort($down)['column']);
        $this->assertSame('desc', $this->sort($down)['direction']);
    }

    #[Test]
    public function the_course_column_orders_the_program_and_not_the_student(): void
    {
        $this->trio();

        $up = $this->actingAs($this->nurse)
            ->get(route('clinic.index', ['sort' => 'course', 'direction' => 'asc']));
        $this->assertSame(['Accountancy', 'Information Technology', 'Nursing'], $this->courses($up));
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($up));

        $down = $this->actingAs($this->nurse)
            ->get(route('clinic.index', ['sort' => 'course', 'direction' => 'desc']));
        $this->assertSame(['Nursing', 'Information Technology', 'Accountancy'], $this->courses($down));
    }

    #[Test]
    public function the_school_number_column_orders_by_the_number_and_not_by_the_name(): void
    {
        $this->trio();

        $response = $this->actingAs($this->nurse)
            ->get(route('clinic.index', ['sort' => 'schoolId', 'direction' => 'asc']));

        $this->assertSame(['CL-001', 'CL-002', 'CL-003'], $this->schoolIds($response));
        $this->assertSame(['Magsaysay', 'Zulueta', 'Bautista'], $this->surnames($response));
    }

    #[Test]
    public function a_column_the_handler_never_listed_falls_back_and_never_reaches_the_query(): void
    {
        $this->trio();

        $asked = $this->actingAs($this->nurse)
            ->get(route('clinic.index', ['sort' => 'passwordHash', 'direction' => 'asc']));

        $sort = $this->sort($asked);
        $this->assertSame('enrollmentId', $sort['column']);
        $this->assertNotContains('passwordHash', $sort['available']);

        // The fallback is about the COLUMN; a direction the desk asked for is still honoured, so the comparison is
        // against the same request spelled with the real key — not against arithmetic done in this file.
        $explicit = $this->actingAs($this->nurse)
            ->get(route('clinic.index', ['sort' => 'enrollmentId', 'direction' => 'asc']));
        $this->assertSame($this->schoolIds($explicit), $this->schoolIds($asked));
        $this->assertSame(['Zulueta', 'Bautista', 'Magsaysay'], $this->surnames($explicit));
    }

    #[Test]
    public function the_enum_columns_are_offered_and_the_derived_status_is_not(): void
    {
        $this->trio();

        $available = $this->sort($this->actingAs($this->nurse)->get(route('clinic.index')))['available'];

        // Ruled 2026-10-31: an ENUM column is sortable at the desk, because the school's server orders it in the
        // declaration order the school itself wrote. Only Clinic Status is withheld — it is painted from whether a
        // record exists on the row, so the query holds nothing to order by. Absent rather than offered and ignored,
        // because a header the handler would not obey is the defect §28.3 owns for filters.
        $this->assertSame(['name', 'schoolId', 'course', 'studentType', 'academicStanding', 'enrollmentId'], $available);
        $this->assertNotContains('clinicStatus', $available, 'clinicStatus is painted from a relation, not ordered from a column');
    }

    #[Test]
    public function the_enum_headers_order_the_words_this_connection_sorts_them_in(): void
    {
        // Three different types and two different standings, seated so that neither ENUM order coincides with either
        // id order: the sequence is asked of the connection, because MySQL reads an ENUM by its declaration index and
        // the suite's varchar-with-CHECK reads the stored word. A literal typed here would be a reading of whichever
        // server answered it — the defect CI named on the clearance queue.
        $this->waitingAtClinic($this->accountancy, 'Zulueta', 'CL-002', StudentType::Transferee->value, 'irregular');
        $this->assess($this->waitingAtClinic($this->nursing, 'Bautista', 'CL-003', StudentType::FirstYear->value, 'regular'));
        $this->waitingAtClinic($this->informationTechnology, 'Magsaysay', 'CL-001', StudentType::Continuing->value, 'irregular');

        $types = $this->actingAs($this->nurse)
            ->get(route('clinic.index', ['sort' => 'studentType', 'direction' => 'asc']));
        $this->assertSame(
            EnumColumnOrder::ascending('enrollments', 'studentType', [
                StudentType::FirstYear->value,
                StudentType::Continuing->value,
                StudentType::Transferee->value,
            ]),
            array_map(fn (array $r): string => (string) $r['studentType'], $this->rows($types))
        );

        $standings = $this->actingAs($this->nurse)
            ->get(route('clinic.index', ['sort' => 'academicStanding', 'direction' => 'asc']));
        $this->assertSame(
            EnumColumnOrder::ascending('enrollments', 'academicStanding', ['regular', 'irregular', 'irregular']),
            array_map(fn (array $r): string => (string) $r['academicStanding'], $this->rows($standings))
        );

        // And reversing the same header reverses exactly that sequence.
        $down = $this->actingAs($this->nurse)
            ->get(route('clinic.index', ['sort' => 'studentType', 'direction' => 'desc']));
        $this->assertSame(
            array_reverse(array_map(fn (array $r): string => (string) $r['studentType'], $this->rows($types))),
            array_map(fn (array $r): string => (string) $r['studentType'], $this->rows($down))
        );
    }

    #[Test]
    public function the_row_with_a_clinic_record_is_counted_as_assessed_and_stays_in_the_queue(): void
    {
        $this->trio();
        $seen = $this->waitingAtClinic($this->accountancy, 'Roxas', 'CL-004');
        $this->assess($seen);

        $response = $this->actingAs($this->nurse)
            ->get(route('clinic.index', ['sort' => 'name', 'direction' => 'asc']));
        $props = $response->viewData('page')['props'];

        // Signing the box is a separate act from entering the record, so the row is still the desk's to work; the
        // tile counts it as assessed. The two numbers describe different questions and neither is the row count.
        $this->assertSame(['Bautista', 'Magsaysay', 'Roxas', 'Zulueta'], $this->surnames($response));
        $this->assertSame(4, $props['stats']['queue']);
        $this->assertSame(2, $props['stats']['completed']);
        $this->assertSame(2, $props['stats']['pending']);
    }

    #[Test]
    public function a_search_and_an_order_are_both_echoed_so_neither_control_resets(): void
    {
        $this->trio();

        $response = $this->actingAs($this->nurse)
            ->get(route('clinic.index', ['search' => 'Zulueta', 'sort' => 'course', 'direction' => 'asc']));
        $props = $response->viewData('page')['props'];

        $this->assertSame('Zulueta', $props['filters']['search']);
        $this->assertSame('course', $props['filters']['sort']);
        $this->assertSame('asc', $props['filters']['direction']);
        $this->assertSame('course', $this->sort($response)['column']);
        $this->assertSame(['Zulueta'], $this->surnames($response));

        // The order still arranges the narrowed set: `u` reaches Zulueta and Bautista but not Magsaysay, and those
        // two come back by surname rather than in the id order the queue would otherwise hold.
        $both = $this->actingAs($this->nurse)
            ->get(route('clinic.index', ['search' => 'u', 'sort' => 'name', 'direction' => 'asc']));
        $this->assertSame(['Bautista', 'Zulueta'], $this->surnames($both));
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_not_passed_through(): void
    {
        $this->trio();

        $response = $this->actingAs($this->nurse)
            ->get(route('clinic.index', ['sort' => 'name', 'direction' => 'asc; drop table students']));

        $this->assertSame('desc', $this->sort($response)['direction']);
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($response));
    }
}
