<?php

namespace Tests\Feature\Notification;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\StudentType;
use App\Enums\WorkflowStepStatus;
use App\Events\EnrollmentStatusChanged;
use App\Events\WorkflowStepSigned;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Models\Workflowsteps;
use App\Support\CollegeScope;
use App\Support\WorkflowInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Prompt §8 and §7.1, and matrix gap G-44's second half: the bell must be addressed by the same boundary the
 * desk's own list applies.
 *
 * Measured on live `ems` before this was written: office 7 (Academic Department) holds 11 active accounts and 6
 * of them carry a `unitId` (`dept.caf` … `dept.cte` on units 1 to 6). `WorkflowInbox::actors()` plucked every
 * active account in the office, so a Department Evaluation signature rang five colleges' department desks while
 * each of those desks lists only its own college's records — the notice named a record its recipient could not
 * open. The repair asks the boundary from `CollegeScope::holds()`, the one statement of it, rather than restating
 * a where clause beside the helper.
 */
class NoticeCollegeBoundaryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_bell_reaches_the_bounded_desk_that_owns_the_records_college_and_not_the_others(): void
    {
        [$unit, $course, $enrollment] = $this->cast();

        $mine = $this->desk(OfficeId::Academic->value, $unit->unitId);
        $other = $this->desk(OfficeId::Academic->value, $this->otherCollege()->unitId);
        $unbounded = $this->desk(OfficeId::Academic->value, null);

        $addressed = WorkflowInbox::actors(OfficeId::Academic->value, $enrollment);

        $this->assertEqualsCanonicalizing(
            [$mine->userId, $unbounded->userId],
            $addressed,
            'a college-bound desk in the office received a notice for a record its own policy refuses'
        );
        $this->assertNotContains($other->userId, $addressed, 'another college was rung for a record it cannot reach');
    }

    #[Test]
    public function a_desk_bounded_by_nothing_is_bounded_by_nothing(): void
    {
        // Null unitId is a decision, not an oversight: an office head's work belongs to the office. Two such
        // accounts in one office must both keep receiving, or the fix would invert the boundary it enforces.
        [$unit, $course, $enrollment] = $this->cast();

        $first = $this->desk(OfficeId::Registrar->value, null);
        $second = $this->desk(OfficeId::Registrar->value, null);

        $addressed = WorkflowInbox::actors(OfficeId::Registrar->value, $enrollment);

        // Compared against what the office actually holds, not against the two accounts this case happened to
        // create: `cast()` seats a Registrar signer too, and a remembered list would read as a code defect
        // when it is only the fixture the case forgot.
        $expect = Staffusers::where('officeId', OfficeId::Registrar->value)
            ->where('status', 'active')
            ->pluck('userId')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertEqualsCanonicalizing($expect, $addressed);
        $this->assertGreaterThanOrEqual(2, count($addressed));
    }

    #[Test]
    public function a_notice_that_names_no_record_keeps_the_whole_office(): void
    {
        $office = $this->desk(OfficeId::Clinic->value, null);
        $bounded = $this->desk(OfficeId::Clinic->value, $this->otherCollege()->unitId);

        // The default argument is a real branch: a hand-built event with no enrollment must not silently narrow to
        // the unbounded accounts, because there is no college to ask about.
        $addressed = WorkflowInbox::actors(OfficeId::Clinic->value);

        $this->assertEqualsCanonicalizing([$office->userId, $bounded->userId], $addressed);
    }

    #[Test]
    public function an_inactive_account_in_the_right_college_is_still_not_addressed(): void
    {
        [$unit, $course, $enrollment] = $this->cast();

        $retired = $this->desk(OfficeId::Academic->value, $unit->unitId, 'inactive');
        $serving = $this->desk(OfficeId::Academic->value, $unit->unitId);

        $addressed = WorkflowInbox::actors(OfficeId::Academic->value, $enrollment);

        $this->assertContains($serving->userId, $addressed);
        $this->assertNotContains($retired->userId, $addressed,
            'bounding the bell must not widen it past the status filter the office list also honours');
    }

    #[Test]
    public function a_real_transition_rings_the_owning_college_across_the_listener(): void
    {
        // The helper can be right while a call site forgets to hand it the record, and every case above would stay
        // green in that state. This one drives the event the listener actually receives.
        [$unit, $course, $enrollment] = $this->cast();

        $mine = $this->desk(OfficeId::Academic->value, $unit->unitId);
        $other = $this->desk(OfficeId::Academic->value, $this->otherCollege()->unitId);

        $workflow = Enrollmentworkflow::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'currentStep' => 1,
            'workflowStatus' => 'inProgress',
        ]);
        Workflowsteps::create([
            'workflowId' => $workflow->workflowId,
            'officeId' => OfficeId::Academic->value,
            'stepOrder' => 1,
            'stepStatus' => WorkflowStepStatus::Pending->value,
        ]);

        $this->assertSame(OfficeId::Academic->value, WorkflowInbox::nextOffice($workflow, 'evaluated'),
            'the box, not the status map, must be what addresses this notice');

        $actor = $this->desk(OfficeId::Academic->value, null);
        event(new EnrollmentStatusChanged(
            $enrollment,
            EnrollmentStatus::Pending->value,
            EnrollmentStatus::Evaluated->value,
            $actor,
            'subjects evaluated for the term',
            1
        ));

        $rows = DB::table('notifications')
            ->where('notifiable_type', Staffusers::class)
            ->pluck('notifiable_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains((int) $mine->userId, $rows, 'the desk that owns the record was not rung');
        $this->assertNotContains((int) $other->userId, $rows,
            'the listener addressed another college, so its call site is not passing the record');
    }

    #[Test]
    public function a_box_signature_rings_the_owning_college_across_the_workflow_listener(): void
    {
        // The status listener and the workflow listener address separately, so one end-to-end case cannot cover
        // both call sites and a leg planted in the wrong file would sleep. This one drives the signing.
        [$unit, $course, $enrollment] = $this->cast();

        $mine = $this->desk(OfficeId::Academic->value, $unit->unitId);
        $other = $this->desk(OfficeId::Academic->value, $this->otherCollege()->unitId);

        $workflow = Enrollmentworkflow::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'currentStep' => 2,
            'workflowStatus' => 'inProgress',
        ]);
        $signed = Workflowsteps::create([
            'workflowId' => $workflow->workflowId,
            'officeId' => OfficeId::Guidance->value,
            'stepOrder' => 1,
            'stepStatus' => WorkflowStepStatus::Completed->value,
            'signedDate' => '2026-10-01 09:00:00',
        ]);
        Workflowsteps::create([
            'workflowId' => $workflow->workflowId,
            'officeId' => OfficeId::Academic->value,
            'stepOrder' => 2,
            'stepStatus' => WorkflowStepStatus::Pending->value,
        ]);

        event(new WorkflowStepSigned($workflow, $signed, $this->desk(OfficeId::Guidance->value, null)));

        $rows = DB::table('notifications')
            ->where('notifiable_type', Staffusers::class)
            ->pluck('notifiable_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertNotEmpty($rows, 'the signing wrote no notice at all, so this case proves nothing about address');
        $this->assertContains((int) $mine->userId, $rows);
        $this->assertNotContains((int) $other->userId, $rows,
            'the workflow listener addressed another college, so its call site is not passing the record');
    }

    #[Test]
    public function the_helper_asks_the_one_college_predicate_the_desks_ask(): void
    {
        // Two definitions of "is this the desk's college" is this repository's oldest defect. The bell must call
        // CollegeScope, and no second where clause may appear in the addressing helper.
        $src = (string) file_get_contents(base_path('app/Support/WorkflowInbox.php'));
        $this->assertStringContainsString('CollegeScope::holds(', $src,
            'the bell restates the college boundary instead of asking the class that owns it');
        $this->assertStringNotContainsString("whereNotNull('unitId')", $src,
            'a hand-written unitId filter beside CollegeScope::holds is a second answer to one question');

        $this->assertTrue(class_exists(CollegeScope::class));
    }

    #[Test]
    public function every_office_the_bell_addresses_is_a_real_desk_page(): void
    {
        foreach (OfficeId::cases() as $office) {
            $this->assertTrue(Route::has($office->deskRouteName()), $office->name.' has no desk route');
        }
    }

    private function otherCollege(): Academicunits
    {
        return Academicunits::create(['unitName' => 'College of Business', 'unitType' => 'college']);
    }

    private function desk(int $officeId, ?int $unitId, string $status = 'active'): Staffusers
    {
        return Staffusers::factory()->create([
            'officeId' => $officeId,
            'unitId' => $unitId,
            'status' => $status,
            'employeeNo' => 'EMP-'.uniqid(),
            'username' => 'bell_staff_'.uniqid(),
            'email' => 'bell_staff_'.uniqid().'@example.com',
        ]);
    }

    /** @return array{0: Academicunits, 1: Courses, 2: Enrollments} */
    private function cast(): array
    {
        $unit = Academicunits::create(['unitName' => 'College of Computer Studies', 'unitType' => 'college']);
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
        $course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSIT-BELL',
            'courseName' => 'Bachelor of Science in Information Technology',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);
        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        foreach (OfficeId::cases() as $office) {
            Offices::firstOrCreate(['officeId' => $office->value], ['officeName' => 'Office '.$office->value]);
        }

        $student = Students::create([
            'schoolIdNumber' => 'BELL-'.uniqid(),
            'lastName' => 'Dela Cruz',
            'firstName' => 'Juan',
            'middleName' => 'P',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2005-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'email' => 'bell_'.uniqid().'@example.com',
            'username' => 'bell_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $signer = $this->desk(OfficeId::Registrar->value, null);

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $course->courseId,
            'termId' => $term->termId,
            'yearLevel' => 2,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'enrollmentStatus' => EnrollmentStatus::Evaluated,
            'evaluatedBy' => $signer->userId,
        ]);

        $enrollment->load('student', 'course', 'enrollmentworkflow');

        return [$unit, $course, $enrollment];
    }
}
