<?php

namespace Tests\Feature\Sorting;

use App\Enums\EnrollmentStatus;
use App\Enums\UnitType;
use App\Http\Controllers\Admin\WorkflowMonitorController;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EnumColumnOrder;
use Tests\TestCase;

/**
 * Prompt §14's sorting control, on the institution-wide workflow monitor — the nineteenth table wired to
 * `App\Support\ListSort` (§28.3 G-40, which stays open because half the list screens still offer no order).
 *
 * The monitor had exactly one order since it was written (`enrollmentId desc`), and an overseer scanning a term
 * looks for a student or a program, not for a record number. The order belongs in the query because the list
 * paginates on the server: a client sort would order the twenty-five rows already on screen and present that as
 * the pipeline, which is the page-versus-set defect §28.3 owns for tiles.
 *
 * Four columns can be ordered — two of them through a hop the row never shows — and four of the painted labels
 * deliberately cannot, because the page derives them per row. Both halves are pinned here, in both directions, and
 * the hop is pinned as never reaching the payload (G-51): an order that widens the row it lists is a leak with a
 * sort's costume.
 *
 * `DatabaseTransactions` on `:memory:` with the schema migrated in `setUp`, because every listed row walks a
 * workflow form and the box rows hang off `offices`, which the in-memory schema only holds once these seeds run.
 */
class WorkflowMonitorSortTest extends TestCase
{
    use DatabaseTransactions;

    private int $courseA;

    private int $courseB;

    private int $term;

    private ?int $signer = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite']);
        // The desk roles are what `assignRole` below looks up, and the rights are what the monitor's own gate asks;
        // an un-seeded schema has neither, and a missing role is a fixture error, not a finding about the page.
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RbacSeeder', '--database' => 'sqlite']);

        // `workflowsteps.officeId` is a foreign key into `offices`, so a record cannot walk its boxes until the
        // desks exist — and this page reads the next box of every row it lists.
        Offices::insert([
            ['officeId' => 1, 'officeName' => 'Registrar'],
            ['officeId' => 2, 'officeName' => 'Accounting Office'],
            ['officeId' => 3, 'officeName' => 'Assessment Office'],
            ['officeId' => 4, 'officeName' => 'Department Evaluation'],
            ['officeId' => 5, 'officeName' => 'Blocking and Scheduling'],
            ['officeId' => 6, 'officeName' => 'Admission Office'],
            ['officeId' => 11, 'officeName' => 'Clinic'],
            ['officeId' => 22, 'officeName' => 'ID Office'],
        ]);
        Religions::create(['religionId' => 1, 'religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitCode' => 'CCS', 'unitName' => 'College of Computer Studies', 'unitType' => UnitType::College,
        ]);
        $bscs = Courses::create([
            'unitId' => $unit->unitId, 'courseCode' => 'BSCS', 'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false, 'requiresRetentionExam' => false,
        ]);
        $bsit = Courses::create([
            'unitId' => $unit->unitId, 'courseCode' => 'BSIT', 'courseName' => 'Information Technology',
            'requiresEntranceExam' => false, 'requiresRetentionExam' => false,
        ]);
        $this->courseA = $bscs->courseId;
        $this->courseB = $bsit->courseId;

        $year = Academicyears::create([
            'yearStart' => 2024, 'yearEnd' => 2025, 'yearLabel' => '2024-2025',
            'startDate' => '2024-06-01', 'endDate' => '2025-05-31',
        ]);
        $this->term = Academicterms::create([
            'academicYearId' => $year->academicYearId, 'semester' => '1st',
            'startDate' => '2024-06-01', 'endDate' => '2024-10-31',
        ])->termId;
    }

    private function signerId(): int
    {
        if ($this->signer !== null) {
            return $this->signer;
        }
        $staff = Staffusers::factory()->create([
            'officeId' => 4, 'role' => 'officeHead',
            'employeeNo' => 'EMP-WMS-'.uniqid(), 'username' => 'wms_'.uniqid(), 'email' => 'wms_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('DeptEvaluator');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->signer = $staff->userId;
    }

    /**
     * One record in the pipeline, with a surname, a program, a type and a status all supplied by the caller so an
     * order can be told apart from the key order it replaced.
     */
    private function record(string $lastName, int $courseId, string $studentType, string $status): Enrollments
    {
        $student = Students::create([
            'schoolIdNumber' => 'WMS-'.strtoupper(substr(uniqid(), -6)),
            'lastName' => $lastName, 'firstName' => 'Given', 'middleName' => 'M', 'suffix' => 'N/A',
            'gender' => 'male', 'birthdate' => '2004-01-01', 'birthplace' => 'Test City',
            'citizenship' => 'Filipino', 'civilStatus' => 'single', 'religionId' => 1,
            'contactNumber' => '09171234567', 'telephoneNumber' => null,
            'email' => 'wms_'.uniqid().'@example.com', 'username' => 'wms_s_'.uniqid(),
            'passwordHash' => bcrypt('password123'), 'status' => 'active',
        ]);

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $courseId,
            'termId' => $this->term,
            'admissionId' => null,
            'yearLevel' => 1,
            'studentType' => $studentType,
            'evaluatedBy' => $this->signerId(),
            'enrollmentType' => 'new',
            'academicStanding' => 'regular',
            'enrollmentStatus' => $status,
        ]);
        (new WorkflowService)->createWorkflow($enrollment);

        return $enrollment->fresh();
    }

    private function admin(): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => 1, 'role' => 'officeHead',
            'employeeNo' => 'EMP-WMA-'.uniqid(), 'username' => 'wma_'.uniqid(), 'email' => 'wma_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('SysAdmin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    /** @return array<string, mixed> */
    private function props(array $query = []): array
    {
        $response = $this->actingAs($this->admin())->get(route('admin.workflow-monitor', $query));
        // Every case reads a list, so a refusal that arrives as a rendered error page must not be able to read as
        // an empty order — the lesson G-52 paid for on the same family of screens.
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    /** @return list<string> */
    private function surnames(array $props): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['studentLastName'],
            $props['enrollments']['data']
        );
    }

    #[Test]
    public function the_list_opens_newest_record_first_when_nothing_is_asked(): void
    {
        $older = $this->record('Alcantara', $this->courseA, 'firstYear', EnrollmentStatus::Pending->value);
        $newer = $this->record('Zulueta', $this->courseB, 'transferee', EnrollmentStatus::Evaluated->value);

        $ids = array_map(
            static fn (array $row): int => (int) $row['enrollmentId'],
            $this->props()['enrollments']['data']
        );

        // The fallback is the order this page has always had, and the fixture puts the surnames in the opposite
        // arrangement on purpose: an assertion that only checks "newest first" would also pass on a surname sort.
        $this->assertSame([$newer->enrollmentId, $older->enrollmentId], $ids);
        $this->assertSame('enrollmentId', $this->props()['sort']['column'], 'the page is told which order was applied');
        $this->assertSame('desc', $this->props()['sort']['direction']);
    }

    #[Test]
    public function the_student_header_orders_by_surname_through_a_hop_the_row_never_shows(): void
    {
        // Surnames are inserted in the key's own order, so `enrollmentId desc` and surname ascending cannot both be
        // true of the same sequence — the shape that let one surviving mutant prove a fixture rather than a guard.
        $this->record('Magsaysay', $this->courseA, 'firstYear', EnrollmentStatus::Pending->value);
        $this->record('Bautista', $this->courseB, 'transferee', EnrollmentStatus::Evaluated->value);
        $this->record('Zulueta', $this->courseA, 'shifter', EnrollmentStatus::Assessed->value);

        $ascending = $this->surnames($this->props(['sort' => 'studentName', 'direction' => 'asc']));
        $descending = $this->surnames($this->props(['sort' => 'studentName', 'direction' => 'desc']));

        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $ascending,
            'the surname header did not order the pipeline by the student it names');
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $descending,
            'the second click did not reverse the same column');
    }

    #[Test]
    public function the_program_header_orders_by_the_course_code_through_its_own_hop(): void
    {
        // Inserted B, A, B so the code order differs from BOTH id orders, not merely from the default one: a
        // fixture that only escapes the fallback can still be passed by an ascending key.
        $bsitFirst = $this->record('One', $this->courseB, 'firstYear', EnrollmentStatus::Pending->value);
        $bscs = $this->record('Two', $this->courseA, 'transferee', EnrollmentStatus::Evaluated->value);
        $bsitLast = $this->record('Three', $this->courseB, 'shifter', EnrollmentStatus::Assessed->value);

        $byCode = array_map(
            static fn (array $row): int => (int) $row['enrollmentId'],
            $this->props(['sort' => 'courseCode', 'direction' => 'asc'])['enrollments']['data']
        );

        $this->assertSame(
            [$bscs->enrollmentId, $bsitFirst->enrollmentId, $bsitLast->enrollmentId],
            $byCode,
            'the program hop orders the list; ties break on the record key, oldest first'
        );
        $this->assertNotSame(
            [$bsitLast->enrollmentId, $bscs->enrollmentId, $bsitFirst->enrollmentId],
            $byCode,
            'the list came back in the key order the header replaced'
        );
    }

    #[Test]
    public function the_type_header_orders_the_enum_by_the_order_this_connection_gives_it(): void
    {
        $values = ['firstYear', 'transferee', 'shifter'];
        $this->record('Alpha', $this->courseA, 'shifter', EnrollmentStatus::Pending->value);
        $this->record('Beta', $this->courseA, 'firstYear', EnrollmentStatus::Evaluated->value);
        $this->record('Gamma', $this->courseA, 'transferee', EnrollmentStatus::Assessed->value);

        // MySQL sorts an ENUM by its declaration index and the suite's varchar-with-CHECK sorts the stored word, so
        // the expectation is asked of the connection rather than typed from one server's habit.
        $expected = EnumColumnOrder::ascending('enrollments', 'studentType', $values);

        $seen = array_map(
            static fn (array $row): string => (string) $row['studentType'],
            $this->props(['sort' => 'studentType', 'direction' => 'asc'])['enrollments']['data']
        );

        $this->assertSame($expected, $seen);
        $this->assertNotSame($values, $seen, 'the fixture is not already in the order, or this case cannot fail');
    }

    #[Test]
    public function the_record_status_header_orders_the_status_the_same_way(): void
    {
        $values = [
            EnrollmentStatus::Pending->value,
            EnrollmentStatus::Evaluated->value,
            EnrollmentStatus::Assessed->value,
        ];
        // Inserted in the order below so neither id direction equals the alphabetical sequence the suite expects;
        // the case asks the connection which order its own column has rather than assuming one.
        foreach ([EnrollmentStatus::Pending, EnrollmentStatus::Assessed, EnrollmentStatus::Evaluated] as $i => $status) {
            $this->record('Status'.chr(65 + $i), $this->courseA, 'firstYear', $status->value);
        }

        $expected = EnumColumnOrder::ascending('enrollments', 'enrollmentStatus', $values);

        $seen = array_map(
            static fn (array $row): string => (string) $row['enrollmentStatus'],
            $this->props(['sort' => 'enrollmentStatus', 'direction' => 'asc'])['enrollments']['data']
        );

        $this->assertSame($expected, $seen);
    }

    #[Test]
    public function a_column_the_handler_never_lists_falls_back_to_the_usual_order(): void
    {
        $this->record('Bautista', $this->courseA, 'firstYear', EnrollmentStatus::Pending->value);
        $this->record('Magsaysay', $this->courseB, 'transferee', EnrollmentStatus::Evaluated->value);

        $default = $this->props();
        // No direction beside the unlisted key: a listed-fallback with `direction=asc` is a legitimate order the
        // desk could ask for, and comparing it to the unsorted request is how this family's first draft wrote a
        // wrong expectation over right code (the Registrar queue's case met the same thing).
        $forged = $this->props(['sort' => 'passwordHash']);

        // Compared against the unsorted request rather than against arithmetic done here: an unlisted key must answer
        // exactly the list the desk would have seen anyway.
        $this->assertSame(
            array_map(static fn (array $r): int => (int) $r['enrollmentId'], $default['enrollments']['data']),
            array_map(static fn (array $r): int => (int) $r['enrollmentId'], $forged['enrollments']['data'])
        );
        $this->assertSame('enrollmentId', $forged['sort']['column'],
            'an unlisted key did not fall back to the column the list is ordered by anyway');
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_never_passed_through(): void
    {
        $this->record('One', $this->courseA, 'firstYear', EnrollmentStatus::Pending->value);
        $this->record('Two', $this->courseA, 'transferee', EnrollmentStatus::Evaluated->value);

        $props = $this->props(['sort' => 'studentName', 'direction' => '; drop tables']);

        $this->assertSame('desc', $props['sort']['direction'], 'an unusable direction is answered as a direction');
        $this->assertCount(2, $props['enrollments']['data'], 'the list still came back, ordered by the real column');
    }

    #[Test]
    public function the_order_and_a_filter_are_both_echoed_so_neither_control_resets(): void
    {
        $this->record('Bautista', $this->courseA, 'firstYear', EnrollmentStatus::Pending->value);
        $this->record('Zulueta', $this->courseA, 'transferee', EnrollmentStatus::Evaluated->value);

        $props = $this->props(['status' => EnrollmentStatus::Pending->value, 'sort' => 'studentName', 'direction' => 'asc']);

        $this->assertSame('studentName', $props['filters']['sort'],
            'the order the server obeyed never came back, so the header resets while the list stays ordered');
        $this->assertSame('asc', $props['filters']['direction'],
            'the direction the server obeyed never came back');
        $this->assertSame(EnrollmentStatus::Pending->value, $props['filters']['status']);
        $this->assertCount(1, $props['enrollments']['data'], 'the filter narrowed the set the order arranges');
    }

    #[Test]
    public function the_tiles_describe_the_whole_set_and_do_not_move_with_the_order(): void
    {
        foreach (['A', 'B', 'C', 'D'] as $i => $surname) {
            $this->record('Tile'.$surname, $this->courseA, 'firstYear', $i % 2 === 0 ? EnrollmentStatus::Pending->value : EnrollmentStatus::Evaluated->value);
        }

        $plain = $this->props()['stats'];
        $ordered = $this->props(['sort' => 'studentName', 'direction' => 'asc'])['stats'];

        // The order joins `students`; a tile cloned after it would be grouped by the join rather than the set, which
        // MySQL 8 answers 1055 at the desk for clicking a header.
        $this->assertSame($plain, $ordered, 'every tile is taken before any header can reach it');
        $this->assertSame(4, (int) $plain['inView']);
    }

    #[Test]
    public function the_hop_that_orders_a_column_never_reaches_the_listed_row(): void
    {
        $this->record('Bautista', $this->courseA, 'firstYear', EnrollmentStatus::Pending->value);

        $keyed = [];
        foreach (['', 'studentName', 'courseCode'] as $sort) {
            $rows = $this->props($sort === '' ? [] : ['sort' => $sort, 'direction' => 'asc'])['enrollments']['data'];
            $this->assertCount(1, $rows, 'a one-to-one hop may not drop the record it orders');
            $keyed[$sort] = array_keys($rows[0]);
        }

        $this->assertSame($keyed[''], $keyed['studentName'], 'the surname hop widened the row');
        $this->assertSame($keyed[''], $keyed['courseCode'], 'the program hop widened the row');
        $this->assertArrayNotHasKey('passwordHash', $rows[0]);
        $this->assertArrayNotHasKey('birthdate', $rows[0]);
    }

    #[Test]
    public function the_four_labels_that_are_derived_per_row_offer_no_header(): void
    {
        $available = $this->props()['sort']['available'];

        foreach (['progress', 'nextPhaseLabel', 'signedDate', 'returnCategoryLabel'] as $derived) {
            $this->assertNotContains($derived, $available,
                "[$derived] is built per row from the workflow's own box list, so no column this query names can order it");
        }

        // And the four that are columns really are offered, so the withheld list cannot silently grow.
        foreach (['studentName', 'courseCode', 'studentType', 'enrollmentStatus'] as $offered) {
            $this->assertContains($offered, $available, "[{$offered}] carries a header the handler still obeys");
        }
    }

    #[Test]
    public function the_action_asks_its_own_request_for_the_order_rather_than_the_helper_reaching_past_it(): void
    {
        // `lane46-filter-contract.php` judges whether a handler obeys the keys its page sends, and a key consumed one
        // call deeper reads as DEAD-SEND — so the two request values are read in the action, on the action's own line.
        $body = $this->actionBody();

        $this->assertStringContainsString("\$request->input('sort', '')", $body);
        $this->assertStringContainsString("\$request->input('direction', '')", $body);
        $this->assertStringContainsString('ListSort::apply(', $body);
        $this->assertStringNotContainsString('orderByDesc(\'enrollmentId\')', $body,
            'the hard-coded order the mechanism replaced is back, so a header would reset while the list stayed narrowed');
    }

    private function actionBody(): string
    {
        $src = (string) file_get_contents(base_path('app/Http/Controllers/Admin/WorkflowMonitorController.php'));
        $this->assertStringContainsString('public function index(Request $request): Response', $src);
        $start = (int) strpos($src, 'public function index(Request $request): Response');
        $rest = substr($src, $start);
        $end = (int) strpos($rest, "\n    /**\n     * ");
        $this->assertIsString($rest);

        return $end > 0 ? substr($rest, 0, $end) : $rest;
    }

    #[Test]
    public function the_controller_is_the_class_under_test(): void
    {
        $this->assertTrue(class_exists(WorkflowMonitorController::class));
    }
}
