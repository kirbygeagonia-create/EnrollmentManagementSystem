<?php

namespace Tests\Feature\Admin;

use App\Enums\EnrollmentStatus;
use App\Enums\UnitType;
use App\Enums\WorkflowStepStatus;
use App\Http\Controllers\Admin\WorkflowMonitorController;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollmentreturns;
use App\Models\Enrollments;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Services\WorkflowService;
use App\Support\WorkflowInbox;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The institution-wide workflow monitor (development prompt §6.1, §6.3).
 *
 * §6.1 names four places a workflow must be visible and the build served three of them:
 * the stepper sat on each desk's own detail page, so "where is the pipeline" was only ever
 * answerable one student at a time, by whoever owned the box. This file pins the fourth
 * place, and pins it the way the other desk screens are pinned: a right that decides, a
 * filter that reaches the query and comes back echoed, tiles that describe the whole
 * filtered set rather than the rows on screen, and the next desk read from the record's
 * own steps — the same answer the receiving desk's bell gives.
 */
class WorkflowMonitorTest extends TestCase
{
    use DatabaseTransactions;

    private int $testCourseId;

    private int $testTermId;

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
        $this->seedReferenceData();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RbacSeeder', '--database' => 'sqlite']);
    }

    private function seedReferenceData(): void
    {
        // `workflowsteps.officeId` is a foreign key into `offices`, so every box the
        // workflow declares has to exist as an office before a form can be built.
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
            'unitCode' => 'CCS',
            'unitName' => 'College of Computer Studies',
            'unitType' => UnitType::College,
        ]);

        $course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        $academicYear = Academicyears::create([
            'yearStart' => 2024,
            'yearEnd' => 2025,
            'yearLabel' => '2024-2025',
            'startDate' => '2024-06-01',
            'endDate' => '2025-05-31',
        ]);
        $term = Academicterms::create([
            'academicYearId' => $academicYear->academicYearId,
            'semester' => '1st',
            'startDate' => '2024-06-01',
            'endDate' => '2024-10-31',
        ]);

        $this->testCourseId = $course->courseId;
        $this->testTermId = $term->termId;
    }

    private function staffWithRole(string $roleName): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => 4,
            'role' => 'officeHead',
            'employeeNo' => 'EMP-WM-'.uniqid(),
            'username' => 'wm_'.uniqid(),
            'email' => 'wm_'.uniqid().'@example.com',
        ]);
        $staff->assignRole($roleName);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    /**
     * A record, its workflow form, and (by default) every box still open.
     */
    private function enrollment(string $status = EnrollmentStatus::Pending->value, bool $withForm = true): Enrollments
    {
        $student = Students::create([
            'schoolIdNumber' => 'WM-'.uniqid(),
            'lastName' => 'Monitored',
            'firstName' => 'Student',
            'middleName' => 'Q',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'telephoneNumber' => null,
            'email' => 'wm_'.uniqid().'@example.com',
            'username' => 'wm_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $this->testCourseId,
            'termId' => $this->testTermId,
            'admissionId' => null,
            'yearLevel' => 1,
            'studentType' => 'firstYear',
            'evaluatedBy' => $this->signerId(),
            'enrollmentType' => 'new',
            'academicStanding' => 'regular',
            'enrollmentStatus' => $status,
        ]);

        if ($withForm) {
            (new WorkflowService)->createWorkflow($enrollment);
        }

        return $enrollment->fresh();
    }

    private function signerId(): int
    {
        return $this->signer ??= $this->staffWithRole('DeptEvaluator')->userId;
    }

    private function sign(int $enrollmentId, int $officeId): void
    {
        $workflow = Enrollments::find($enrollmentId)->enrollmentworkflow;

        $workflow->workflowsteps()
            ->where('officeId', $officeId)
            ->update([
                'stepStatus' => WorkflowStepStatus::Completed->value,
                'signedBy' => $this->signerId(),
                'signedDate' => now(),
            ]);

        $workflow->unsetRelation('workflowsteps');
    }

    private function props(Staffusers $as, array $query = []): array
    {
        $response = $this->actingAs($as)->get(route('admin.workflow-monitor', $query));
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    #[Test]
    public function the_right_that_opens_the_monitor_is_seeded_and_reaches_the_desk_that_oversight(): void
    {
        // `workflow.view` is named `.view` so the read-everywhere Admin role takes it by its
        // own rule; a mutation right would have to be listed per role and would say the
        // overseer edits records, which ruling item 3 refuses.
        $admin = Role::findByName('Admin');
        $this->assertTrue($admin->hasPermissionTo('workflow.view'),
            'Admin holds every .view right by rule, so the monitor opens for the overseer');

        $admission = Role::findByName('AdmissionOfficer');
        $this->assertFalse($admission->hasPermissionTo('workflow.view'),
            'an intake desk is not given an institution-wide view of every other desk');
    }

    #[Test]
    public function an_account_without_the_right_is_refused_the_page_rather_than_shown_an_empty_list(): void
    {
        $this->enrollment();

        $this->actingAs($this->staffWithRole('Staff'))
            ->get(route('admin.workflow-monitor'))
            ->assertForbidden();
    }

    #[Test]
    public function the_monitor_lists_a_record_and_reports_the_phase_from_its_own_boxes(): void
    {
        $enrollment = $this->enrollment();

        $props = $this->props($this->staffWithRole('SysAdmin'));
        $row = collect($props['enrollments']['data'])->firstWhere('enrollmentId', $enrollment->enrollmentId);

        $this->assertNotNull($row, 'a record on a workflow form appears in the monitor');
        // firstYear carries all seven boxes and none is signed yet.
        $this->assertSame(7, $row['totalBoxes']);
        $this->assertSame(0, $row['signedBoxes']);
        $this->assertSame(7, $row['openBoxes']);
        $this->assertSame(4, $row['nextOfficeId']);
        $this->assertSame('Department Evaluation', $row['nextPhaseLabel']);
        $this->assertSame('Department Evaluation', $row['nextOfficeName']);
        $this->assertFalse($row['isLastBox']);
    }

    #[Test]
    public function signing_a_box_moves_the_record_to_the_next_desk_on_the_same_row(): void
    {
        $enrollment = $this->enrollment();
        $this->sign($enrollment->enrollmentId, 4);

        $props = $this->props($this->staffWithRole('SysAdmin'));
        $row = collect($props['enrollments']['data'])->firstWhere('enrollmentId', $enrollment->enrollmentId);

        $this->assertSame(1, $row['signedBoxes']);
        // Assessment (office 3) is the second box for a firstYear record, and its own office
        // name is what the desk reads — not the phase word, which belongs to the box.
        $this->assertSame(3, $row['nextOfficeId']);
        $this->assertSame('Assessment', $row['nextPhaseLabel']);
        $this->assertSame(3, WorkflowInbox::nextOffice($enrollment->fresh()->enrollmentworkflow, $enrollment->enrollmentStatus->value),
            'the row and the notification path answer one question');
        $this->assertNotNull($row['signedDate'], 'the row says when a desk last acted');
    }

    #[Test]
    public function the_desk_filter_keeps_only_the_records_whose_turn_that_desk_is(): void
    {
        $awaited = $this->enrollment();
        $signedOnce = $this->enrollment();
        $this->sign($signedOnce->enrollmentId, 4);

        $props = $this->props($this->staffWithRole('SysAdmin'), ['office' => 4]);
        $ids = collect($props['enrollments']['data'])->pluck('enrollmentId')->all();

        // Only the record still ahead of Evaluation belongs to that desk; the second one has
        // moved on to Assessment, and its Evaluation box reads signed.
        $this->assertSame([$awaited->enrollmentId], $ids);
        $this->assertSame(4, (int) $props['filters']['office'], 'the control keeps what the desk set');
        $this->assertSame('Department Evaluation', $props['officeChoices'][0]['label']);
    }

    #[Test]
    public function a_desk_with_a_later_open_box_is_not_offered_a_record_another_desk_is_owed(): void
    {
        // Both offices hold an open box, but Evaluation is ahead of Accounting. A filter that
        // asked only "does this record have an open box at office 3" would put a record the
        // Accounting desk cannot yet act on into its queue — the same shape the tile-vs-list
        // rules refuse everywhere else in this repository.
        $this->enrollment();

        $later = $this->props($this->staffWithRole('SysAdmin'), ['office' => 3]);
        $ahead = $this->props($this->staffWithRole('SysAdmin'), ['office' => 4]);

        $this->assertCount(0, $later['enrollments']['data'], 'the later desk is not owed this record yet');
        $this->assertCount(1, $ahead['enrollments']['data']);
        $this->assertSame(0, $later['stats']['inView'], 'the tile counts the same set the list filters');
    }

    #[Test]
    public function the_tiles_count_the_whole_filtered_set_and_never_the_rows_on_the_page(): void
    {
        foreach (range(1, 30) as $ignored) {
            $this->enrollment();
        }

        $props = $this->props($this->staffWithRole('SysAdmin'));

        $this->assertSame(25, count($props['enrollments']['data']), 'the page holds 25 rows');
        $this->assertSame(30, $props['stats']['inView']);
        $this->assertSame(30, $props['stats']['awaiting']);
        $this->assertSame(0, $props['stats']['signedThrough']);
        $this->assertSame(0, $props['stats']['withoutForm']);
        // A real zero from the server stays a zero; the tile only dashes an absent number.
        $this->assertIsInt($props['stats']['signedThrough']);
    }

    #[Test]
    public function a_record_with_no_workflow_form_is_named_instead_of_disappearing(): void
    {
        $this->enrollment(EnrollmentStatus::Pending->value, false);

        $props = $this->props($this->staffWithRole('SysAdmin'));
        $row = collect($props['enrollments']['data'])->first();

        $this->assertSame(1, $props['stats']['withoutForm']);
        $this->assertSame(0, $row['totalBoxes']);
        $this->assertNull($row['nextOfficeId'], 'no desk is claimed for a record that has no boxes');
    }

    #[Test]
    public function a_record_signed_through_every_box_is_counted_as_signed_through(): void
    {
        $enrollment = $this->enrollment();

        foreach (array_keys(WorkflowMonitorController::boxes()) as $officeId) {
            $this->sign($enrollment->enrollmentId, $officeId);
        }

        $props = $this->props($this->staffWithRole('SysAdmin'));
        $row = collect($props['enrollments']['data'])->firstWhere('enrollmentId', $enrollment->enrollmentId);

        $this->assertSame(1, $props['stats']['signedThrough']);
        $this->assertSame(0, $props['stats']['awaiting']);
        $this->assertSame(7, $row['signedBoxes']);
        $this->assertNull($row['nextOfficeId']);
    }

    #[Test]
    public function a_returned_record_shows_its_category_its_origin_and_where_it_was_sent(): void
    {
        $enrollment = $this->enrollment(EnrollmentStatus::ReturnedToEvaluation->value);
        $sender = $this->staffWithRole('RegistrarApprover');

        Enrollmentreturns::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'workflowId' => $enrollment->enrollmentworkflow->workflowId,
            'originOfficeId' => 1,
            'originStepOrder' => 4,
            'destination' => 'departmentEvaluation',
            'destinationOfficeId' => 4,
            'returnCategory' => 'curriculumSectionMismatch',
            'returnNote' => 'The load proposes a subject the pinned curriculum does not offer.',
            'expectedCorrection' => 'Re-propose from the current curriculum list.',
            'previousStatus' => EnrollmentStatus::Paid->value,
            'newStatus' => EnrollmentStatus::ReturnedToEvaluation->value,
            'returnedBy' => $sender->userId,
            'returnedAt' => now(),
        ]);

        $props = $this->props($this->staffWithRole('SysAdmin'));
        $row = collect($props['enrollments']['data'])->firstWhere('enrollmentId', $enrollment->enrollmentId);

        $this->assertSame(1, $props['stats']['returned']);
        $this->assertSame('curriculumSectionMismatch', $row['returnCategory']);
        $this->assertSame('Registrar', $row['returnOriginOfficeName']);
        $this->assertSame('Department Evaluation', $row['returnDestinationLabel']);
        $this->assertStringContainsString('pinned curriculum', $row['returnNote']);
    }

    #[Test]
    public function only_the_newest_return_of_a_record_sent_back_twice_is_shown(): void
    {
        $enrollment = $this->enrollment(EnrollmentStatus::ReturnedToEvaluation->value);
        $workflowId = $enrollment->enrollmentworkflow->workflowId;

        foreach ([['missingDocuments', 'Old send: the paper was missing.', '-2 days'],
            ['curriculumSectionMismatch', 'Newest send: the load does not fit.', '-1 hours']] as [$category, $note, $ago]) {
            Enrollmentreturns::create([
                'enrollmentId' => $enrollment->enrollmentId,
                'workflowId' => $workflowId,
                'originOfficeId' => 1,
                'originStepOrder' => 4,
                'destination' => 'departmentEvaluation',
                'destinationOfficeId' => 4,
                'returnCategory' => $category,
                'returnNote' => $note,
                'expectedCorrection' => 'Correct it at the owning desk.',
                'previousStatus' => EnrollmentStatus::Paid->value,
                'newStatus' => EnrollmentStatus::ReturnedToEvaluation->value,
                'returnedBy' => null,
                'returnedAt' => now()->modify($ago),
            ]);
        }

        $props = $this->props($this->staffWithRole('SysAdmin'));
        $row = collect($props['enrollments']['data'])->firstWhere('enrollmentId', $enrollment->enrollmentId);

        $this->assertSame('curriculumSectionMismatch', $row['returnCategory'],
            'a desk reading the queue must see what it owes now, not the first complaint');
    }

    #[Test]
    public function a_dropped_record_is_not_reported_as_work_somewhere_in_the_pipeline(): void
    {
        $this->enrollment(EnrollmentStatus::Dropped->value);
        $live = $this->enrollment();

        $props = $this->props($this->staffWithRole('SysAdmin'));

        $this->assertSame(1, $props['stats']['inView']);
        $this->assertSame([$live->enrollmentId], collect($props['enrollments']['data'])->pluck('enrollmentId')->all());
    }

    #[Test]
    public function a_status_the_schema_cannot_hold_is_refused_with_a_message_not_answered_with_an_empty_list(): void
    {
        // A dropdown value the column's enum cannot hold is the fiction lane 46 refuses; the
        // screen must show the refusal rather than paint zero rows.
        $response = $this->actingAs($this->staffWithRole('SysAdmin'))
            ->get(route('admin.workflow-monitor', ['status' => 'graduated']));

        $response->assertRedirect();
        $response->assertSessionHasErrors('status');
    }

    #[Test]
    public function the_status_and_type_choices_are_the_enums_the_columns_hold(): void
    {
        $props = $this->props($this->staffWithRole('SysAdmin'));

        $this->assertEqualsCanonicalizing(
            array_column(EnrollmentStatus::cases(), 'value'),
            $props['statusChoices']
        );
        $this->assertNotContains('graduated', $props['statusChoices']);
        $this->assertNotContains('returnee', $props['studentTypeChoices'],
            'the monitor cannot offer a classification the schema does not hold (matrix G-9)');
    }

    #[Test]
    public function the_page_names_the_definition_the_records_are_walking_and_says_it_is_not_versioned(): void
    {
        $props = $this->props($this->staffWithRole('SysAdmin'));

        // §6.3 asks for the effective definition beside the progress. Until the school rules
        // which phases are mandatory, the honest answer is the code list plus its own name.
        $this->assertSame(count(WorkflowService::stepLabels()), $props['definition']['boxCount']);
        $this->assertSame('WorkflowService::stepsFor()', $props['definition']['source']);
        $this->assertFalse($props['definition']['versioned']);
    }

    #[Test]
    public function the_search_reaches_the_columns_the_screen_labels(): void
    {
        $wanted = $this->enrollment();
        $this->enrollment();

        $props = $this->props($this->staffWithRole('SysAdmin'));
        $byId = $this->actingAs($this->staffWithRole('SysAdmin'))
            ->get(route('admin.workflow-monitor', ['search' => $wanted->student->schoolIdNumber]))
            ->viewData('page')['props'];

        $this->assertCount(2, $props['enrollments']['data'], 'an unfiltered read shows both');
        $this->assertSame([$wanted->enrollmentId], collect($byId['enrollments']['data'])->pluck('enrollmentId')->all());
        $this->assertSame($wanted->student->schoolIdNumber, $byId['filters']['search']);
    }
}
