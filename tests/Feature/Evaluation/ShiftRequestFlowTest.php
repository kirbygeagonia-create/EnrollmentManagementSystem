<?php

namespace Tests\Feature\Evaluation;

use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\ShiftRequestStatus;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Blocks;
use App\Models\Courses;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Majors;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Shiftingrequests;
use App\Models\Staffusers;
use App\Models\Students;
use App\Models\Subjects;
use App\Models\Workflowsteps;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Ruling 11 (G-7): the program change the system could not perform.
 *
 * §28 put it plainly — "A shift appears only as a courseId different from the previous
 * term's. Nothing notifies, nothing retires the previous blockId, and no history row
 * records the change." This is the paper that replaces that accident: the student's stated
 * will filed at Academic Department Evaluation, the dean or program head's endorsement that
 * says which term and year level the student lands at, and the Guidance Councillor's
 * signature, which is the final call either way.
 *
 * Two things the grant has to get right at once, and the tests below are the seams:
 * the receiving enrollment is a `shifter` record whose two enrollment pointers name the
 * move (that row is the history §21.4 said no table held), and the seat in the program
 * being left stops counting — Blocking weighs distinct students who are not dropped, so a
 * student who left still occupied a place until this existed.
 */
class ShiftRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $evaluator;

    private Staffusers $dean;

    private Staffusers $counsellor;

    private Staffusers $registrar;

    private Students $student;

    private Courses $from;

    private Courses $to;

    private Academicterms $term;

    private Blocks $block;

    private Enrollments $current;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        foreach ([1, 2, 3, 4, 5, 6, 11, 22] as $officeId) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => 'Office '.$officeId]);
        }

        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $unit = Academicunits::create(['unitName' => 'College of Computer Studies', 'unitType' => 'college']);

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

        $this->from = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSIT',
            'courseName' => 'Information Technology',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        $this->to = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSBA',
            'courseName' => 'Business Administration',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        Majors::create(['courseId' => $this->to->courseId, 'majorName' => 'Financial Management']);

        $subject = Subjects::create([
            'subjectCode' => 'IT101',
            'subjectName' => 'Introduction to Computing',
            'lectureUnits' => 3,
            'labUnits' => 0,
            'subjectType' => 'lecture',
        ]);

        $this->student = Students::create([
            'schoolIdNumber' => 'SHIFT-'.uniqid(),
            'lastName' => 'Shiftmaker',
            'firstName' => 'Student',
            'middleName' => 'S',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 2,
            'yearsInInstitution' => 1,
            'email' => 'shift_'.uniqid().'@example.com',
            'username' => 'shift_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->evaluator = $this->staffInOffice('DeptEvaluator');
        $this->dean = $this->staffInOffice('Dean');
        $this->counsellor = $this->staffInOffice('GuidanceStaff');
        $this->registrar = $this->staffInOffice('RegistrarApprover');

        // The record the shift leaves: enrolled in BSIT this term, seated in a block.
        $this->block = Blocks::create([
            'courseId' => $this->from->courseId,
            'termId' => $this->term->termId,
            'yearLevel' => 2,
            'blockName' => 'BSIT-2A',
            'maxStudents' => 40,
        ]);

        $this->current = Enrollments::create([
            'studentId' => $this->student->studentId,
            'courseId' => $this->from->courseId,
            'termId' => $this->term->termId,
            'yearLevel' => 2,
            'studentType' => StudentType::Continuing,
            'enrollmentType' => 'old',
            'enrollmentStatus' => EnrollmentStatus::Enrolled,
            'evaluatedBy' => $this->evaluator->userId,
        ]);

        Enrolledsubjects::create([
            'enrollmentId' => $this->current->enrollmentId,
            'subjectId' => $subject->subjectId,
            'blockId' => $this->block->blockId,
            'status' => EnrolledSubjectStatus::Confirmed,
        ]);
    }

    private function staffInOffice(string $spatieRole): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => 4,
            'role' => 'staff',
            'employeeNo' => 'EMP-SHIFT-'.uniqid(),
            'username' => 'shift_'.strtolower($spatieRole).'_'.uniqid(),
            'email' => 'shift_'.strtolower($spatieRole).'_'.uniqid().'@example.com',
        ]);
        $staff->assignRole($spatieRole);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    private function file(?int $targetCourseId = null): array
    {
        return [
            'studentId' => $this->student->studentId,
            'targetCourseId' => $targetCourseId ?? $this->to->courseId,
            'willStatement' => 'I request to shift from BSIT to BSBA for the first semester, as advised in my counselling session.',
        ];
    }

    private function endorsed(): Shiftingrequests
    {
        $this->actingAs($this->evaluator)->post(route('shift.store'), $this->file())->assertSessionHasNoErrors();

        $request = Shiftingrequests::sole();

        $this->actingAs($this->dean)
            ->post(route('shift.endorse', $request), ['termId' => $this->term->termId, 'yearLevel' => 2])
            ->assertSessionHasNoErrors();

        return $request->fresh();
    }

    #[Test]
    public function the_department_files_the_will_to_shift_and_nothing_is_enrolled_yet(): void
    {
        $this->actingAs($this->evaluator)
            ->post(route('shift.store'), $this->file())
            ->assertSessionHasNoErrors();

        $request = Shiftingrequests::sole();

        $this->assertSame('pending', $request->requestStatus->value);
        $this->assertSame($this->from->courseId, (int) $request->currentCourseId);
        $this->assertSame($this->to->courseId, (int) $request->targetCourseId);
        $this->assertSame($this->current->enrollmentId, (int) $request->currentEnrollmentId);
        $this->assertSame($this->evaluator->userId, (int) $request->requestedBy);
        $this->assertNull($request->grantedEnrollmentId);

        // Filing is not granting: the student is still in the program they are leaving.
        $this->assertSame(1, Enrollments::count());
        $this->assertSame('enrolled', $this->current->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function the_paper_refuses_a_student_with_nothing_to_leave_and_a_target_that_is_not_a_move(): void
    {
        $this->actingAs($this->evaluator)
            ->post(route('shift.store'), $this->file($this->from->courseId))
            ->assertSessionHasErrors('targetCourseId');

        $outsider = Students::create([
            'schoolIdNumber' => 'NONE-'.uniqid(),
            'lastName' => 'Nobody',
            'firstName' => 'Student',
            'middleName' => 'N',
            'suffix' => 'N/A',
            'gender' => 'female',
            'birthdate' => '2008-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'email' => 'none_'.uniqid().'@example.com',
            'username' => 'none_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->actingAs($this->evaluator)
            ->post(route('shift.store'), [...$this->file(), 'studentId' => $outsider->studentId])
            ->assertSessionHasErrors('studentId');

        $this->assertSame(0, Shiftingrequests::count());
    }

    #[Test]
    public function one_open_paper_per_student_because_two_would_ask_for_the_same_decision_twice(): void
    {
        $this->actingAs($this->evaluator)->post(route('shift.store'), $this->file())->assertSessionHasNoErrors();

        $this->actingAs($this->dean)
            ->post(route('shift.store'), $this->file())
            ->assertSessionHasErrors('studentId');

        $this->assertStringContainsString(
            'is already pending and waiting on',
            session('errors')->first('studentId')
        );
        $this->assertSame(1, Shiftingrequests::count());
    }

    #[Test]
    public function the_signatures_are_three_different_hands_and_in_order(): void
    {
        $this->actingAs($this->evaluator)->post(route('shift.store'), $this->file())->assertSessionHasNoErrors();
        $request = Shiftingrequests::sole();

        // Guidance cannot decide a paper the department head has not endorsed.
        $this->actingAs($this->counsellor)
            ->post(route('shift.decide', $request), ['decision' => 'grant'])
            ->assertForbidden();

        // The evaluator who filed it cannot endorse their own paper — they lack the right.
        $this->actingAs($this->evaluator)
            ->post(route('shift.endorse', $request), ['termId' => $this->term->termId, 'yearLevel' => 2])
            ->assertForbidden();

        $this->assertSame('pending', $request->fresh()->requestStatus->value);

        $this->actingAs($this->dean)
            ->post(route('shift.endorse', $request), ['termId' => $this->term->termId, 'yearLevel' => 2])
            ->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame('endorsed', $request->requestStatus->value);
        $this->assertSame($this->dean->userId, (int) $request->departmentSignedBy);
        $this->assertSame(2, (int) $request->yearLevel);

        // Endorsing twice is rewriting a paper that has moved on.
        $this->actingAs($this->dean)
            ->post(route('shift.endorse', $request), ['termId' => $this->term->termId, 'yearLevel' => 3])
            ->assertForbidden();
    }

    #[Test]
    public function granting_issues_the_receiving_enrollment_and_retires_the_seat_being_left(): void
    {
        $request = $this->endorsed();

        $this->actingAs($this->counsellor)
            ->post(route('shift.decide', $request), ['decision' => 'grant'])
            ->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame('granted', $request->requestStatus->value);
        $this->assertSame($this->counsellor->userId, (int) $request->decisionBy);

        // Both enrollment pointers in one row: "these two enrollments are the shift",
        // which is the history record §21.4 said nothing held.
        $this->assertSame($this->current->enrollmentId, (int) $request->currentEnrollmentId);

        $received = Enrollments::findOrFail($request->grantedEnrollmentId);
        $this->assertSame('shifter', $received->studentType->value);
        $this->assertSame($this->to->courseId, (int) $received->courseId);
        $this->assertSame('pending', $received->enrollmentStatus->value);
        $this->assertSame('old', $received->enrollmentType->value);
        $this->assertNull($received->admissionId, 'A student already enrolled is not an applicant (§11).');
        $this->assertNotNull($received->formIssuedDate);

        // §6.5: the receiving record is a returning student's form — six boxes, no Assessment.
        $boxes = Workflowsteps::where('workflowId', Enrollmentworkflow::where('enrollmentId', $received->enrollmentId)->value('workflowId'))
            ->pluck('officeId')
            ->all();
        $this->assertCount(6, $boxes);

        // The seat the shift leaves stops counting, and the record itself is closed with
        // the shift paper as its reason — the three signatures are why this desk's grant,
        // rather than the Registrar's drop, is what ends it.
        $this->assertSame('dropped', $this->current->fresh()->enrollmentStatus->value);
        $this->assertSame(
            [EnrolledSubjectStatus::Dropped->value],
            Enrolledsubjects::where('enrollmentId', $this->current->enrollmentId)->pluck('status')->map(fn ($s) => $s->value)->all()
        );
        $this->assertStringContainsString(
            "shift request #{$request->shiftingRequestId}",
            $this->current->enrollmentstatushistory()->latest('historyId')->value('remarks') ?? ''
        );
    }

    #[Test]
    public function granting_is_refused_when_a_different_record_holds_the_seat(): void
    {
        $request = $this->endorsed();

        // Another enrollment in the receiving term that this paper says nothing about.
        $other = Enrollments::create([
            'studentId' => $this->student->studentId,
            'courseId' => $this->from->courseId,
            'termId' => $this->term->termId,
            'yearLevel' => 2,
            'studentType' => StudentType::Continuing,
            'enrollmentType' => 'old',
            'enrollmentStatus' => EnrollmentStatus::Paid,
            'evaluatedBy' => $this->evaluator->userId,
        ]);

        $this->actingAs($this->counsellor)
            ->post(route('shift.decide', $request), ['decision' => 'grant'])
            ->assertSessionHasErrors('decision');

        $this->assertStringContainsString(
            "enrollment #{$other->enrollmentId}",
            session('errors')->first('decision')
        );
        $this->assertSame('endorsed', $request->fresh()->requestStatus->value);
        $this->assertSame(2, Enrollments::count());
    }

    #[Test]
    public function a_refusal_needs_a_reason_and_leaves_the_student_where_they_are(): void
    {
        $request = $this->endorsed();

        $this->actingAs($this->counsellor)
            ->post(route('shift.decide', $request), ['decision' => 'reject'])
            ->assertSessionHasErrors('remarks');

        $this->actingAs($this->counsellor)
            ->post(route('shift.decide', $request), ['decision' => 'reject', 'remarks' => 'Insufficient units credited for the second year.'])
            ->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame('rejected', $request->requestStatus->value);
        $this->assertSame('Insufficient units credited for the second year.', $request->decisionRemarks);
        $this->assertNull($request->grantedEnrollmentId);
        $this->assertSame('enrolled', $this->current->fresh()->enrollmentStatus->value);
        $this->assertSame(1, Enrollments::count());
    }

    #[Test]
    public function a_shift_grant_is_not_asked_to_prove_itself_with_an_examination_again(): void
    {
        $this->to->update(['requiresRetentionExam' => true]);

        $request = $this->endorsed();
        $this->actingAs($this->counsellor)->post(route('shift.decide', $request), ['decision' => 'grant'])->assertSessionHasNoErrors();

        $received = Enrollments::findOrFail($request->fresh()->grantedEnrollmentId);

        // Ruling 11: proof of readiness is the form plus the credit evaluation. Compared
        // against the same program and term with no shift behind it, which IS held to the
        // retention paper — otherwise this assertion would pass on a gate that never fires.
        $this->actingAs($this->evaluator)
            ->get(route('evaluation.show', $received))
            ->assertInertia(fn ($page) => $page
                ->component('Evaluation/Show')
                ->missing('signBlockers.retention')
            );

        $otherTerm = Academicterms::create([
            'academicYearId' => $this->term->academicYearId,
            'semester' => '2nd',
            'startDate' => '2026-12-01',
            'endDate' => '2027-03-31',
        ]);

        $ordinary = Enrollments::create([
            'studentId' => $this->student->studentId,
            'courseId' => $this->to->courseId,
            'termId' => $otherTerm->termId,
            'yearLevel' => 2,
            'studentType' => StudentType::Continuing,
            'enrollmentType' => 'old',
            'enrollmentStatus' => EnrollmentStatus::Pending,
            'evaluatedBy' => $this->evaluator->userId,
        ]);

        $this->actingAs($this->evaluator)
            ->get(route('evaluation.show', $ordinary))
            ->assertInertia(fn ($page) => $page
                ->where('signBlockers.retention', 'No retention examination recorded for this term — a returning student in a program that examines retention must pass it before the form is forwarded.')
            );
    }

    #[Test]
    public function a_program_change_cannot_be_squeezed_through_the_ordinary_enrollment_issue(): void
    {
        // A term with no seat held, so the only thing this request can be refused for is
        // the program mismatch itself.
        $year = Academicyears::create([
            'yearLabel' => '2027-2028',
            'startDate' => '2027-06-01',
            'endDate' => '2028-03-31',
        ]);
        $laterTerm = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2027-06-01',
            'endDate' => '2027-10-31',
        ]);

        // The detection rule, ruling 11: when the program does not match history, the
        // shift paper is the route — otherwise a shift stays invisible, which is G-7.
        $this->actingAs($this->evaluator)
            ->post(route('evaluation.store'), [
                'studentId' => $this->student->studentId,
                'termId' => $laterTerm->termId,
                'courseId' => $this->to->courseId,
                'yearLevel' => 2,
                'studentType' => 'continuing',
            ])
            ->assertSessionHasErrors('courseId');

        $this->assertStringContainsString(
            'filed as a shift request',
            session('errors')->first('courseId')
        );
        $this->assertSame(1, Enrollments::count());
    }

    #[Test]
    public function the_docket_is_for_the_three_desks_in_the_flow_and_nobody_else(): void
    {
        foreach ([$this->evaluator, $this->dean, $this->counsellor] as $desk) {
            $this->actingAs($desk)->get(route('shift.index'))->assertOk();
        }

        // The Registrar reads enrollments, not shift papers; Guidance Staff is in the flow
        // as the deciding desk.
        $this->assertFalse($this->registrar->checkPermissionTo('shift.request.create'));
        $this->actingAs($this->registrar)->get(route('shift.index'))->assertForbidden();

        $bystander = $this->staffInOffice('Staff');
        $this->actingAs($bystander)->get(route('shift.index'))->assertForbidden();
    }

    #[Test]
    public function the_docket_reads_the_way_the_paper_moves(): void
    {
        $this->actingAs($this->evaluator)->post(route('shift.store'), $this->file())->assertSessionHasNoErrors();
        $request = Shiftingrequests::sole();
        $this->actingAs($this->dean)
            ->post(route('shift.endorse', $request), ['termId' => $this->term->termId, 'yearLevel' => 2])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->counsellor)
            ->get(route('shift.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Evaluation/ShiftRequests')
                ->where('stats.endorsed', 1)
                ->where('requests.data.0.requestStatus', 'endorsed')
                ->where('requests.data.0.currentCourse.courseCode', 'BSIT')
                ->where('requests.data.0.targetCourse.courseCode', 'BSBA')
                ->where('can.decide', true)
                ->where('can.endorse', false)
            );
    }

    #[Test]
    public function the_status_vocabulary_carries_the_step_the_paper_waits_on(): void
    {
        $this->actingAs($this->evaluator)->post(route('shift.store'), $this->file())->assertSessionHasNoErrors();
        $request = Shiftingrequests::sole();

        $this->assertStringContainsString('dean or program head', $request->waitingOn());

        $this->actingAs($this->dean)
            ->post(route('shift.endorse', $request), ['termId' => $this->term->termId, 'yearLevel' => 2])
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString('Guidance Councillor', $request->fresh()->waitingOn());
        $this->assertSame(
            ['pending', 'endorsed', 'granted', 'rejected'],
            array_map(fn ($case) => $case->value, ShiftRequestStatus::cases())
        );
    }
}
