<?php

namespace Tests\Feature\Registrar;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Prompt §14's sorting control, on the queue that has had exactly one order since it was written
 * (§28.3 G-40).
 *
 * The queue sorted by `enrollmentId desc` and nothing else, so an officer hunting for one student among the
 * Assessed and Paid rows could only page through them. Sorting belongs in the query rather than the page because
 * the list paginates on the server: a client sort would order the twenty rows already on screen and present that
 * as the queue, which is the page-vs-set defect §28.3 records for tiles.
 *
 * What these cases pin is the direction of each promise, not merely that a sort happened: the rows must come back
 * in surname order (not merely "differently"), the toggle must reverse the SAME column, a key the handler never
 * listed must fall back without reaching the SQL, and the page must be told which order was applied — a sort the
 * server obeyed but never reported is a header that resets while the list stays narrowed.
 */
class RegistrarQueueSortTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $registrar;

    private Courses $course;

    private Academicterms $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        Offices::firstOrCreate(['officeId' => 1], ['officeName' => 'Office of the Registrar']);
        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitName' => 'College of Computer Studies',
            'unitType' => 'college',
        ]);
        $this->course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSIT',
            'courseName' => 'Information Technology',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
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

        $staff = Staffusers::factory()->create([
            'officeId' => 1,
            'employeeNo' => 'EMP-SORT-'.uniqid(),
            'username' => 'sort_registrar_'.uniqid(),
            'email' => 'sort_registrar_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('RegistrarApprover');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->registrar = $staff;
    }

    /** Surnames deliberately inverse to the order the rows are created in. */
    private function assessed(string $lastName, int $yearLevel = 1): Enrollments
    {
        $student = Students::create([
            'schoolIdNumber' => 'SORT-'.strtoupper(substr(uniqid(), -6)),
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
            'email' => 'sort_'.uniqid().'@example.com',
            'username' => 'sort_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        return Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $this->course->courseId,
            'termId' => $this->term->termId,
            'yearLevel' => $yearLevel,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'enrollmentStatus' => EnrollmentStatus::Assessed,
            'evaluatedBy' => $this->registrar->userId,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function rows($response): array
    {
        return $response->viewData('page')['props']['enrollments']['data'];
    }

    private function surnames($response): array
    {
        return array_map(fn ($row) => $row['student']['lastName'] ?? null, $this->rows($response));
    }

    /**
     * Created Bautista, Zulueta, Magsaysay so that surname order differs from BOTH id orders.
     *
     * The first draft created them Zulueta, Magsaysay, Bautista, which made `enrollmentId desc` return exactly the
     * surname-ascending list — and the mutant harness leg that replaced the whole sort with the old hard-coded
     * order then passed the case and reported itself survived. A fixture whose wrong order agrees with the defect it
     * is meant to catch is the lane 79 lesson, met from this side.
     */
    private function surnameTrio(): void
    {
        $this->assessed('Bautista');
        $this->assessed('Zulueta');
        $this->assessed('Magsaysay');
    }

    #[Test]
    public function the_queue_can_be_ordered_by_the_students_surname(): void
    {
        $this->surnameTrio();

        $response = $this->actingAs($this->registrar)
            ->get(route('registrar.index', ['sort' => 'name', 'direction' => 'asc']));

        // Not "different from the default" — the actual surname order, which a reversed sort would fail.
        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($response));
    }

    #[Test]
    public function asking_the_same_column_again_reverses_it_rather_than_resetting(): void
    {
        $this->surnameTrio();

        $down = $this->actingAs($this->registrar)
            ->get(route('registrar.index', ['sort' => 'name', 'direction' => 'desc']));

        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($down));
        $this->assertSame('name', $down->viewData('page')['props']['sort']['column']);
        $this->assertSame('desc', $down->viewData('page')['props']['sort']['direction']);
    }

    #[Test]
    public function a_column_the_handler_never_listed_falls_back_and_never_reaches_the_query(): void
    {
        $zulueta = $this->assessed('Zulueta');
        $magsaysay = $this->assessed('Magsaysay');

        // The shape of a forged or stale key: it is not in the allow-list, so it must be ignored rather
        // than interpolated — an identifier cannot be bound as a parameter, so passing it through would be
        // the injection, not a miss.
        $withDirection = $this->actingAs($this->registrar)
            ->get(route('registrar.index', ['sort' => 'passwordHash', 'direction' => 'asc']));

        $sort = $withDirection->viewData('page')['props']['sort'];
        $this->assertSame('enrollmentId', $sort['column'], 'an unlisted key must fall back to the list’s own column');
        $this->assertNotContains('passwordHash', $sort['available']);

        // The fallback is about the COLUMN; a direction the desk asked for is still honoured, because it was
        // validated to asc|desc and an officer who asked for ascending did not ask to be contradicted.
        //
        // The ids are read back from the rows the fixture created, never written as 1 and 2: on MySQL 8 an
        // AUTO_INCREMENT counter spent by earlier tests in the same run hands out different numbers, while
        // SQLite rewinds it inside a rolled-back transaction — so literals pass locally and go red on CI, the
        // exact trap AGENTS.md records for the rehearsal schema. Order, not value, is the promise.
        $ascending = array_map(fn ($row) => (int) $row['enrollmentId'], $this->rows($withDirection));
        $this->assertCount(2, $ascending, 'an order cannot be read from fewer than two rows');
        $this->assertSame(
            [$zulueta->enrollmentId, $magsaysay->enrollmentId],
            $ascending,
            'the ascending fallback must be the creation order, read from the rows the fixture made'
        );
        $this->assertSame(['Zulueta', 'Magsaysay'], $this->surnames($withDirection));

        // With no direction asked, the list keeps its own newest-first order.
        $plain = $this->actingAs($this->registrar)->get(route('registrar.index'));
        $this->assertSame('desc', $plain->viewData('page')['props']['sort']['direction']);
        $this->assertSame(
            [$magsaysay->enrollmentId, $zulueta->enrollmentId],
            array_map(fn ($row) => (int) $row['enrollmentId'], $this->rows($plain)),
            'with nothing asked the queue keeps its own newest-first order'
        );
        $this->assertSame(['Magsaysay', 'Zulueta'], $this->surnames($plain));
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_not_passed_through(): void
    {
        $this->assessed('Zulueta');
        $this->assessed('Magsaysay');

        $response = $this->actingAs($this->registrar)
            ->get(route('registrar.index', ['sort' => 'name', 'direction' => 'asc; drop table students']));

        $this->assertSame('desc', $response->viewData('page')['props']['sort']['direction']);
        // Still answered, and still in surname order — descending here.
        $this->assertSame(['Zulueta', 'Magsaysay'], $this->surnames($response));
    }

    #[Test]
    public function the_page_is_told_which_order_was_applied_and_which_it_may_ask_for(): void
    {
        $this->assessed('Zulueta');

        $sort = $this->actingAs($this->registrar)
            ->get(route('registrar.index', ['sort' => 'yearLevel', 'direction' => 'asc']))
            ->viewData('page')['props']['sort'];

        // Without this the header cannot show its arrow after a page change or a filter, and the control reads as
        // reset while the list is still ordered by something the officer chose.
        $this->assertSame('yearLevel', $sort['column']);
        $this->assertSame('asc', $sort['direction']);
        $this->assertSame(['name', 'schoolId', 'yearLevel', 'status', 'enrollmentId'], $sort['available']);
    }
}
