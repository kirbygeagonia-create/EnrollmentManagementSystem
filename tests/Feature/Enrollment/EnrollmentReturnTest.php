<?php

namespace Tests\Feature\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Enums\GuardianRelationship;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\ReturnCategory;
use App\Enums\ReturnDestination;
use App\Enums\UnitType;
use App\Enums\WorkflowStepStatus;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Addresses;
use App\Models\Admissions;
use App\Models\Courses;
use App\Models\Enrollmentreturns;
use App\Models\Enrollments;
use App\Models\Guardians;
use App\Models\Offices;
use App\Models\Payments;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Models\Students;
use App\Models\Subjects;
use App\Models\Workflowsteps;
use App\Policies\EvaluationPolicy;
use App\Services\WorkflowService;
use App\Support\SchoolTime;
use App\Support\WorkflowInbox;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Development prompt §6.4, gap G-10: the one return act every desk shares.
 *
 * Ruled by the owner 2026-10-26: one right (`enrollment.return`) held by three desks, and a return
 * that must state who sent it, where it goes, which category it is, what the receiving desk has to
 * finish, and who and when. Ruling 14's Registrar-only hold is gone rather than left beside this, so
 * these cases read the single mechanism: its authorization, its routing refusals, the trail row, and
 * the notice the receiving desk is owed.
 *
 * Every case writes through the HTTP route rather than the service, because §6.4 is a promise about
 * what a desk can do — and because the older version of this act sat behind a route whose only check
 * was a permission.
 */
class EnrollmentReturnTest extends TestCase
{
    use DatabaseTransactions;

    private int $testCourseId;

    private int $testTermId;

    private int $otherCourseId;

    private int $bscsUnitId;

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
        Offices::insert([
            ['officeId' => 1, 'officeName' => 'Registrar'],
            ['officeId' => 2, 'officeName' => 'Accounting Office'],
            ['officeId' => 3, 'officeName' => 'Assessment Office'],
            ['officeId' => 4, 'officeName' => 'Department Evaluation'],
            ['officeId' => 6, 'officeName' => 'Admission Office'],
            // The workflow form carries a box for each of these too, and `workflowsteps.officeId`
            // holds a foreign key into `offices`, so a workflow cannot be built without them.
            ['officeId' => 5, 'officeName' => 'Blocking and Scheduling'],
            ['officeId' => 11, 'officeName' => 'Clinic'],
            ['officeId' => 22, 'officeName' => 'ID Office'],
        ]);

        Religions::create(['religionId' => 1, 'religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitCode' => 'CCS',
            'unitName' => 'College of Computer Studies',
            'unitType' => UnitType::College,
        ]);
        $otherUnit = Academicunits::create([
            'unitCode' => 'CAS',
            'unitName' => 'College of Arts and Sciences',
            'unitType' => UnitType::College,
        ]);

        $course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);
        // A second program in a second college, for the one question `enrollment.return` cannot
        // answer on its own: every department desk holds that right, so the college decides which
        // records are its to send back.
        $otherCourse = Courses::create([
            'unitId' => $otherUnit->unitId,
            'courseCode' => 'AB',
            'courseName' => 'Bachelor of Arts',
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
        $this->otherCourseId = $otherCourse->courseId;
        $this->bscsUnitId = (int) $unit->unitId;
    }

    private function staffForOffice(?int $officeId, string $role, ?int $unitId = null): Staffusers
    {
        // `officeId` is passed even when null: StaffusersFactory defaults it to the Registrar, so a
        // filtered-out key would silently seat the deskless account in office 1 and the case below
        // that needs a null origin would test nothing.
        $staff = Staffusers::factory()->create([
            'officeId' => $officeId,
            'unitId' => $unitId,
            'role' => 'officeHead',
            'employeeNo' => 'EMP-RET-'.uniqid(),
            'username' => 'ret_'.($officeId ?? 'none').'_'.uniqid(),
            'email' => 'ret_'.($officeId ?? 'none').'_'.uniqid().'@example.com',
        ]);
        $staff->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    /**
     * A record on a real workflow form, with its Department Evaluation box signed by the account
     * named — a signature already given is what makes sending it back to that desk an act rather
     * than a request to act twice. `evaluatedBy` carries a foreign key, so the id is a real account
     * and never a literal.
     */
    private function enrollment(Staffusers $signer, string $status = EnrollmentStatus::Paid->value, bool $evaluated = true, ?int $courseId = null): Enrollments
    {
        $student = Students::create([
            'schoolIdNumber' => 'RET-'.uniqid(),
            'lastName' => 'Returned',
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
            'email' => 'ret_'.uniqid().'@example.com',
            'username' => 'ret_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $courseId ?? $this->testCourseId,
            'termId' => $this->testTermId,
            'admissionId' => null,
            'yearLevel' => 1,
            'studentType' => 'firstYear',
            'evaluatedBy' => $signer->userId,
            'enrollmentType' => 'new',
            'academicStanding' => 'regular',
            'enrollmentStatus' => $status,
        ]);

        Studentassessments::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'totalAssessedAmount' => 1000,
            'totalScholarshipCoverage' => 0,
            'totalWaived' => 0,
            'remainingBalance' => 0,
            'assessmentDate' => now(),
        ]);

        $workflow = (new WorkflowService)->createWorkflow($enrollment);
        if ($evaluated) {
            $workflow->workflowsteps()->where('officeId', 4)->update([
                'stepStatus' => WorkflowStepStatus::Completed->value,
                'signedBy' => $signer->userId,
                'signedDate' => now(),
            ]);
        }

        return $enrollment->fresh();
    }

    private function approvedApplication(Enrollments $enrollment): Admissions
    {
        return Admissions::create([
            'studentId' => $enrollment->studentId,
            'courseId' => $enrollment->courseId,
            'termId' => $enrollment->termId,
            'applicantType' => 'firstYear',
            'admissionStatus' => 'approved',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function returnTo(Staffusers $actor, Enrollments $enrollment, array $payload = [])
    {
        return $this->actingAs($actor)->post(route('enrollment.return', $enrollment), $payload + [
            'destination' => ReturnDestination::DepartmentEvaluation->value,
            'returnCategory' => ReturnCategory::CurriculumSectionMismatch->value,
            'returnNote' => 'The proposed load lists a subject whose prerequisite is not on file.',
            'expectedCorrection' => 'Drop the subject or attach the prerequisite record, then re-sign.',
        ]);
    }

    /**
     * The props an Inertia screen answers. The route returns HTML unless the request carries
     * `X-Inertia`, so `->json('props.x')` reports "Invalid JSON was returned from the route" — the
     * payload a browser receives sits on the view's `page` data.
     *
     * @return array<string, mixed>
     */
    private function propsOf($response): array
    {
        return $response->viewData('page')['props'];
    }

    /**
     * Every profile fact BR32 makes the Sign act wait for. Written as records rather than as column
     * pokes, because `missingProfileFields()` asks for two complete addresses and one complete
     * guardian — a null in any of their required cells reads as a profile the desk has not captured.
     */
    private function completeProfile(Enrollments $enrollment): void
    {
        $student = $enrollment->student;

        // A first-year has completed zero semesters; the field must be present, not merely non-blank.
        $student->update(['semestersCompleted' => 0, 'yearsInInstitution' => 0]);

        foreach (['home', 'current'] as $type) {
            Addresses::create([
                'studentId' => $student->studentId,
                'addressType' => $type,
                'houseBuildingNo' => '12',
                'street' => 'Test Street',
                'sitioPurok' => 'Purok 1',
                'barangay' => 'Test Barangay',
                'cityMunicipality' => 'Cebu City',
                'district' => 'District 1',
                'province' => 'Cebu',
                'region' => 'Region VII',
                'country' => 'Philippines',
                'zipCode' => '6000',
            ]);
        }

        Guardians::create([
            'studentId' => $student->studentId,
            'relationship' => GuardianRelationship::Mother->value,
            'fullName' => 'Guardian Q. Returned',
            'contactNumber' => '09170000000',
            'email' => 'guardian_'.uniqid().'@example.com',
            'isEmergencyContact' => true,
            'isAuthorizedToActOnBehalf' => true,
        ]);

        $enrollment->update(['formIssuedDate' => SchoolTime::today()]);
    }

    #[Test]
    public function the_three_desks_holding_the_one_right_can_all_return_the_same_kind_of_record(): void
    {
        foreach ([
            ['officeId' => 1, 'role' => 'RegistrarApprover', 'desk' => 'Registrar'],
            ['officeId' => 6, 'role' => 'AdmissionOfficer', 'desk' => 'Admission'],
            ['officeId' => 4, 'role' => 'DeptEvaluator', 'desk' => 'Department Evaluation'],
        ] as $desk) {
            $actor = $this->staffForOffice($desk['officeId'], $desk['role']);
            $enrollment = $this->enrollment($actor);

            $this->assertTrue(
                $actor->hasPermissionTo('enrollment.return'),
                "The {$desk['desk']} desk was expected to hold the one return right."
            );

            $this->returnTo($actor, $enrollment)->assertSessionHasNoErrors();

            $this->assertSame('returnedToEvaluation', $enrollment->fresh()->enrollmentStatus->value,
                "A return made by the {$desk['desk']} desk did not move the record.");
        }
    }

    #[Test]
    public function a_desk_without_the_return_right_is_refused_and_the_gate_that_refuses_it_is_the_gate_the_page_asks(): void
    {
        // A cashier in Accounting: an office that reads these records but is not one of the three
        // desks §6.4 names.
        $evaluator = $this->staffForOffice(4, 'DeptEvaluator');
        $cashier = $this->staffForOffice(2, 'AccountingStaff');
        $enrollment = $this->enrollment($evaluator);

        $this->assertFalse($cashier->hasPermissionTo('enrollment.return'));

        // The controller passes THIS ability to `can()`, and its show() handlers answer the offered
        // destinations from the same call, so a false here is both a 403 and an absent button.
        $this->assertFalse(Gate::forUser($cashier)->allows('enrollment.returnRecord', $enrollment));

        $this->returnTo($cashier, $enrollment)->assertForbidden();

        $this->assertSame('paid', $enrollment->fresh()->enrollmentStatus->value);
        $this->assertDatabaseCount('enrollmentreturns', 0);
    }

    #[Test]
    public function the_return_right_is_not_seated_with_the_office_head_of_every_office(): void
    {
        // Ruling 7 split the sign rights out of OfficeHead for the same reason: a right every desk
        // head holds makes the routing a question of job title rather than of custody.
        $head = $this->staffForOffice(1, 'OfficeHead');

        $this->assertFalse($head->hasPermissionTo('enrollment.return'));
    }

    #[Test]
    public function a_college_bound_department_may_not_return_another_college_s_record(): void
    {
        $evaluator = $this->staffForOffice(4, 'DeptEvaluator', $this->bscsUnitId);
        $own = $this->enrollment($evaluator);
        $foreign = $this->enrollment($this->staffForOffice(4, 'DeptEvaluator'), courseId: $this->otherCourseId);

        $this->assertTrue(Gate::forUser($evaluator)->allows('enrollment.returnRecord', $own));
        $this->assertFalse(Gate::forUser($evaluator)->allows('enrollment.returnRecord', $foreign));

        $this->returnTo($evaluator, $foreign)->assertForbidden();
        $this->assertSame('paid', $foreign->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function the_refusal_names_the_rule_that_answered_rather_than_leaving_the_desk_a_placeholder(): void
    {
        $evaluator = $this->staffForOffice(4, 'DeptEvaluator', $this->bscsUnitId);
        $foreign = $this->enrollment($this->staffForOffice(4, 'DeptEvaluator'), courseId: $this->otherCourseId);

        // errors/403.blade.php and the JSON render both read App\Support\DenialReason::of(), which
        // treats Laravel's placeholder as no reason — so the controller passes the policy's own
        // sentence, and the desk is told whether it lacked the right or the college.
        $this->returnTo($evaluator, $foreign)
            ->assertForbidden()
            ->assertSee('another college');
    }

    #[Test]
    public function a_desk_that_belongs_to_no_college_is_bounded_by_nothing(): void
    {
        // The Registrar and Admission hold no unitId: an office head's work belongs to the office,
        // not to one college. Read the other way round, the Registrar could not return the record
        // it approved.
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $foreign = $this->enrollment($this->staffForOffice(4, 'DeptEvaluator'), courseId: $this->otherCourseId);

        $this->assertNull($registrar->unitId);
        $this->returnTo($registrar, $foreign)->assertSessionHasNoErrors();
        $this->assertSame('returnedToEvaluation', $foreign->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function a_record_the_department_has_not_signed_offers_no_destination_at_all_and_refuses_the_press(): void
    {
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $enrollment = $this->enrollment($this->staffForOffice(4, 'DeptEvaluator'), evaluated: false);

        // Nothing signed, so the department is refused; no approved application, so Admission is
        // refused. The page carries an empty list and paints no button rather than inviting the
        // desk to discover the refusal by clicking into it.
        $this->actingAs($registrar)
            ->get(route('registrar.show', $enrollment))
            ->assertInertia(fn ($page) => $page->where('returnOptions.destinations', []));

        $this->returnTo($registrar, $enrollment)->assertSessionHasErrors('destination');
        $this->assertSame('paid', $enrollment->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function a_dropped_record_is_terminal_and_says_so(): void
    {
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $enrollment = $this->enrollment($registrar, status: EnrollmentStatus::Dropped->value);

        $this->returnTo($registrar, $enrollment)->assertSessionHasErrors('destination');

        $this->assertSame('dropped', $enrollment->fresh()->enrollmentStatus->value);
        $this->assertDatabaseCount('enrollmentreturns', 0);
    }

    #[Test]
    public function a_record_already_parked_at_a_destination_is_not_returned_there_twice(): void
    {
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $enrollment = $this->enrollment($registrar, status: EnrollmentStatus::ReturnedToEvaluation->value);

        $this->returnTo($registrar, $enrollment)->assertSessionHasErrors('destination');

        // A second row would make the trail a list of presses rather than a list of returns.
        $this->assertDatabaseCount('enrollmentreturns', 0);
    }

    #[Test]
    public function admission_is_offered_only_when_an_approved_application_is_there_to_reopen(): void
    {
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $orphan = $this->enrollment($registrar);

        $this->returnTo($registrar, $orphan, ['destination' => ReturnDestination::Admission->value])
            ->assertSessionHasErrors('destination');
        $this->assertSame('paid', $orphan->fresh()->enrollmentStatus->value);

        // A continuing student's record is issued by the department, so the desk that owes that
        // paper is not Admission — and the page stops listing it.
        $this->assertSame(
            ['departmentEvaluation'],
            array_column($this->propsOf(
                $this->actingAs($registrar)->get(route('registrar.show', $orphan))
            )['returnOptions']['destinations'], 'value'),
        );

        $issued = $this->enrollment($registrar);
        $this->approvedApplication($issued);

        $this->returnTo($registrar, $issued, ['destination' => ReturnDestination::Admission->value])
            ->assertSessionHasNoErrors();
        $this->assertSame('returnedToAdmission', $issued->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function a_return_to_admission_parks_the_record_and_reopens_the_application_that_issued_it(): void
    {
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $enrollment = $this->enrollment($registrar);
        $application = $this->approvedApplication($enrollment);

        $this->returnTo($registrar, $enrollment, ['destination' => ReturnDestination::Admission->value])
            ->assertSessionHasNoErrors();

        $this->assertSame('returnedToAdmission', $enrollment->fresh()->enrollmentStatus->value);
        $this->assertSame('pending', $application->fresh()->admissionStatus->value);

        // Admission holds no box on this record's form, so every box goes back: nothing on the form
        // is earlier than the desk that issued it.
        $this->assertSame(0, Workflowsteps::where('workflowId', $enrollment->enrollmentworkflow->workflowId)
            ->where('stepStatus', WorkflowStepStatus::Completed->value)
            ->count());

        $trail = Enrollmentreturns::sole();
        $this->assertSame('admission', $trail->destination->value);
        $this->assertSame(6, $trail->destinationOfficeId);
        $this->assertNull($trail->destinationStepOrder, 'A destination with no box must not claim a step.');
    }

    #[Test]
    public function approving_the_reopened_application_brings_a_parked_record_back_into_the_pipeline(): void
    {
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $admission = $this->staffForOffice(6, 'AdmissionOfficer');
        $enrollment = $this->enrollment($registrar);
        $application = $this->approvedApplication($enrollment);

        $this->returnTo($registrar, $enrollment, ['destination' => ReturnDestination::Admission->value])
            ->assertSessionHasNoErrors();
        $this->assertSame('returnedToAdmission', $enrollment->fresh()->enrollmentStatus->value);

        $seats = Enrollments::count();

        $this->actingAs($admission)->post(route('admission.approve', $application))->assertRedirect();

        $this->assertSame(
            'pending',
            $enrollment->fresh()->enrollmentStatus->value,
            'The machine declares returnedToAdmission => pending and this approval is the only act that takes that edge.'
        );
        $this->assertSame($seats, Enrollments::count(), 'Resuming the seat-holder must not mint a second enrollment.');
        $this->assertSame('approved', $application->fresh()->admissionStatus->value);

        $trail = DB::table('enrollmentstatushistory')
            ->where('enrollmentId', $enrollment->enrollmentId)
            ->orderBy('changedAt')
            ->orderBy('historyId')
            ->pluck('toStatus')
            ->all();

        $this->assertSame('pending', end($trail), 'The resume is a transition, so it owes the record a history row.');
        $this->assertGreaterThan(1, count($trail), 'The return and the resume are two acts, not one silent write.');
    }

    #[Test]
    public function the_trail_row_carries_every_fact_section_6_4_lists(): void
    {
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $enrollment = $this->enrollment($registrar);

        $this->returnTo($registrar, $enrollment, [
            'returnCategory' => ReturnCategory::MissingDocuments->value,
            'returnNote' => 'The registration form is not on file and the transcript is missing.',
            'expectedCorrection' => 'Attach the signed form and the transcript, then re-sign.',
        ])->assertSessionHasNoErrors();

        $trail = Enrollmentreturns::sole();

        $this->assertSame($enrollment->enrollmentId, $trail->enrollmentId);
        $this->assertSame($enrollment->enrollmentworkflow->workflowId, $trail->workflowId);
        $this->assertSame(1, $trail->originOfficeId, 'The sending desk comes from the account, never the form.');
        $this->assertSame(1, $trail->originStepOrder, 'The phase reached is the last box actually signed.');
        $this->assertSame('departmentEvaluation', $trail->destination->value);
        $this->assertSame(4, $trail->destinationOfficeId);
        $this->assertSame(1, $trail->destinationStepOrder);
        $this->assertSame('missingDocuments', $trail->returnCategory->value);
        $this->assertSame('The registration form is not on file and the transcript is missing.', $trail->returnNote);
        $this->assertSame('Attach the signed form and the transcript, then re-sign.', $trail->expectedCorrection);
        $this->assertSame('paid', $trail->previousStatus);
        $this->assertSame('returnedToEvaluation', $trail->newStatus);
        $this->assertSame($registrar->userId, $trail->returnedBy);
        $this->assertNotNull($trail->returnedAt);
    }

    #[Test]
    public function an_account_with_no_office_of_its_own_still_leaves_a_complete_trail(): void
    {
        // staffusers.officeId is nullable, so this is a legal account rather than a hypothetical
        // one: a NOT NULL origin would have answered the act with a QueryException and the generic
        // "conflicts with information already on file" page, which names no record and no column.
        $deskless = $this->staffForOffice(null, 'RegistrarApprover');
        $enrollment = $this->enrollment($deskless);

        $this->assertNull($deskless->officeId);
        $this->returnTo($deskless, $enrollment)->assertSessionHasNoErrors();

        $this->assertNull(Enrollmentreturns::sole()->originOfficeId);
        $this->assertSame('returnedToEvaluation', $enrollment->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function the_status_history_row_is_written_by_the_state_machine_in_the_same_act(): void
    {
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $enrollment = $this->enrollment($registrar);

        $this->returnTo($registrar, $enrollment)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('enrollmentstatushistory', [
            'enrollmentId' => $enrollment->enrollmentId,
            'fromStatus' => 'paid',
            'toStatus' => 'returnedToEvaluation',
        ]);

        $remarks = DB::table('enrollmentstatushistory')
            ->where('enrollmentId', $enrollment->enrollmentId)
            ->where('toStatus', 'returnedToEvaluation')
            ->value('remarks');

        // The history keeps its prose sentence and the trail keeps the same facts in columns; both
        // are written in one transaction, which is what makes a return auditable rather than narrated.
        $this->assertStringContainsString('Curriculum or section mismatch', (string) $remarks);
        $this->assertStringContainsString('prerequisite is not on file', (string) $remarks);
    }

    #[Test]
    public function a_return_reopens_the_destination_box_and_every_box_after_it_and_keeps_the_earlier_ones(): void
    {
        // Planted shape: today's two destinations both sit at the head of the form, so a box earlier
        // than the destination cannot be reached by a desk act. The rule is still "this box and
        // everything after it", and it is the rule that decides which signature survives — so it is
        // tested against a form that has one.
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $enrollment = $this->enrollment($registrar);
        $workflowId = $enrollment->enrollmentworkflow->workflowId;

        $evaluationBox = Workflowsteps::where('workflowId', $workflowId)->where('officeId', 4)->sole();
        Workflowsteps::where('workflowId', $workflowId)->whereIn('stepOrder', [2, 3])->update([
            'stepStatus' => WorkflowStepStatus::Completed->value,
            'signedBy' => $registrar->userId,
            'signedDate' => now(),
        ]);
        $evaluationBox->update(['stepOrder' => 4]);

        $this->returnTo($registrar, $enrollment)->assertSessionHasNoErrors();

        $kept = Workflowsteps::where('workflowId', $workflowId)->whereIn('stepOrder', [2, 3])->get();
        $this->assertTrue($kept->every(fn ($step) => $step->stepStatus === WorkflowStepStatus::Completed
            && $step->signedBy !== null), 'Boxes before the destination keep the signature already given.');

        $reopened = Workflowsteps::where('workflowId', $workflowId)->where('stepOrder', '>=', 4)->get();
        $this->assertTrue($reopened->every(fn ($step) => $step->stepStatus === WorkflowStepStatus::Pending
            && $step->signedBy === null
            && $step->signedDate === null), 'The destination and everything after it goes back to pending.');

        $this->assertSame(4, Enrollmentreturns::sole()->destinationStepOrder);
    }

    #[Test]
    public function a_cleared_box_is_not_an_erased_act(): void
    {
        // The signature is cleared from the box so the desk can give a new one, and kept in the
        // history and the trail so the audit still says who did it — the rule the payment void follows.
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $enrollment = $this->enrollment($registrar);
        $workflowId = $enrollment->enrollmentworkflow->workflowId;

        $this->returnTo($registrar, $enrollment)->assertSessionHasNoErrors();

        $this->assertNull(Workflowsteps::where('workflowId', $workflowId)->where('officeId', 4)->value('signedBy'));
        $this->assertSame($registrar->userId, Enrollmentreturns::sole()->returnedBy);
    }

    #[Test]
    public function the_desk_that_owes_the_correction_is_the_desk_told(): void
    {
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $toDepartment = $this->enrollment($registrar);
        $toAdmission = $this->enrollment($registrar);
        $this->approvedApplication($toAdmission);

        $this->returnTo($registrar, $toDepartment)->assertSessionHasNoErrors();
        $this->returnTo($registrar, $toAdmission, ['destination' => ReturnDestination::Admission->value])
            ->assertSessionHasNoErrors();

        // WorkflowInbox is what the notification listeners address, so these two answers are the
        // bell's whole routing table. A record parked at Admission has every box pending, and
        // asking the box list alone would ring the department instead.
        $this->assertSame(4, WorkflowInbox::nextOffice(
            $toDepartment->fresh()->enrollmentworkflow,
            EnrollmentStatus::ReturnedToEvaluation->value,
        ));
        $this->assertSame(6, WorkflowInbox::nextOffice(
            $toAdmission->fresh()->enrollmentworkflow,
            EnrollmentStatus::ReturnedToAdmission->value,
        ));
    }

    #[Test]
    public function the_receiving_desk_reads_who_sent_it_and_what_it_owes(): void
    {
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $evaluator = $this->staffForOffice(4, 'DeptEvaluator');
        $enrollment = $this->enrollment($evaluator);

        $this->returnTo($registrar, $enrollment, [
            'returnNote' => 'Two proposed subjects have no prerequisite on file.',
            'expectedCorrection' => 'Re-propose the load without them, then sign again.',
        ])->assertSessionHasNoErrors();

        // Without this prop the six facts would be write-only: the page used to print the sentence
        // from `enrollments.returnReason` under a heading naming the Registrar in prose, which was
        // true only while one desk could return at all.
        $this->actingAs($evaluator)
            ->get(route('evaluation.show', $enrollment))
            ->assertInertia(fn ($page) => $page
                ->where('openReturn.originOfficeName', 'Registrar')
                ->where('openReturn.destinationLabel', 'Department Evaluation')
                ->where('openReturn.categoryLabel', 'Curriculum or section mismatch')
                ->where('openReturn.note', 'Two proposed subjects have no prerequisite on file.')
                ->where('openReturn.expectedCorrection', 'Re-propose the load without them, then sign again.')
                ->where('openReturn.returnedByName', $registrar->name)
                ->where('openReturn.previousStatus', 'paid'));
    }

    #[Test]
    public function the_notice_leaves_once_the_record_moves_on_but_the_trail_stays(): void
    {
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $enrollment = $this->enrollment($registrar);

        $this->returnTo($registrar, $enrollment)->assertSessionHasNoErrors();
        $this->assertNotNull($this->propsOf($this->actingAs($registrar)
            ->get(route('registrar.show', $enrollment)))['openReturn']);

        // The desk corrects and resubmits. Written straight onto the record because the act that
        // does this in the pipeline is the department's signature, which this case is not about.
        $enrollment->update(['enrollmentStatus' => EnrollmentStatus::Evaluated]);

        $this->assertNull($this->propsOf($this->actingAs($registrar)
            ->get(route('registrar.show', $enrollment)))['openReturn']);
        $this->assertDatabaseCount('enrollmentreturns', 1);
    }

    #[Test]
    public function the_note_and_the_expected_correction_are_both_required_and_neither_may_be_short(): void
    {
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $enrollment = $this->enrollment($registrar);

        $this->returnTo($registrar, $enrollment, ['returnNote' => 'too short'])
            ->assertSessionHasErrors('returnNote');
        $this->returnTo($registrar, $enrollment, ['expectedCorrection' => 'abc'])
            ->assertSessionHasErrors('expectedCorrection');
        $this->returnTo($registrar, $enrollment, ['returnCategory' => 'notACategory'])
            ->assertSessionHasErrors('returnCategory');
        $this->returnTo($registrar, $enrollment, ['destination' => 'clinic'])
            ->assertSessionHasErrors('destination');

        $this->assertSame('paid', $enrollment->fresh()->enrollmentStatus->value);
        $this->assertDatabaseCount('enrollmentreturns', 0);
    }

    #[Test]
    public function the_choices_a_desk_sees_are_answered_by_the_server_rather_than_spelled_by_the_page(): void
    {
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $enrollment = $this->enrollment($registrar);
        $this->approvedApplication($enrollment);

        $options = $this->propsOf($this->actingAs($registrar)
            ->get(route('registrar.show', $enrollment)))['returnOptions'];

        $this->assertSame(['admission', 'departmentEvaluation'], array_column($options['destinations'], 'value'));
        $this->assertSame(ReturnCategory::values(), array_column($options['categories'], 'value'));
        foreach ($options['categories'] as $category) {
            $this->assertNotSame($category['value'], $category['label'],
                'A choice must be labelled for a desk, not spelled as its own column value.');
        }
    }

    #[Test]
    public function a_returned_record_still_reopens_for_the_department_s_re_proposal(): void
    {
        $evaluator = $this->staffForOffice(4, 'DeptEvaluator');
        $enrollment = $this->enrollment($evaluator, status: EnrollmentStatus::ReturnedToEvaluation->value);

        $this->assertTrue((new EvaluationPolicy)->proposeSubjects($evaluator, $enrollment));

        // …and stays closed for the statuses that must not be re-proposed.
        $enrollment->enrollmentStatus = EnrollmentStatus::Assessed;
        $this->assertFalse((new EvaluationPolicy)->proposeSubjects($evaluator, $enrollment));
    }

    #[Test]
    public function a_desk_that_reads_the_record_but_may_not_return_it_sees_the_notice_and_not_the_control(): void
    {
        // A ProgramHead holds `evaluation.view` and no return right, so this is the reachable shape of
        // the rule the Admission draft screen was rebuilt around: the offer follows the policy, while
        // the record's own history stays readable. `evaluation.show` answers its `can` map from the
        // record-scoped acts, and the return is decided by `returnOptions` alone — which is why an
        // earlier draft of this page read a `can.enrollmentReturn` flag that its controller never sent
        // and the button could not paint.
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $programHead = $this->staffForOffice(4, 'ProgramHead');
        $enrollment = $this->enrollment($this->staffForOffice(4, 'DeptEvaluator'));

        $this->assertFalse($programHead->hasPermissionTo('enrollment.return'));
        $this->assertTrue($programHead->hasPermissionTo('evaluation.view'));

        $this->returnTo($registrar, $enrollment)->assertSessionHasNoErrors();

        $this->actingAs($programHead)
            ->get(route('evaluation.show', $enrollment))
            ->assertInertia(fn ($page) => $page
                ->where('returnOptions', null)
                ->where('openReturn.originOfficeName', 'Registrar')
                ->where('openReturn.expectedCorrection', 'Drop the subject or attach the prerequisite record, then re-sign.'));
    }

    #[Test]
    public function the_admission_desk_sees_the_notice_even_when_it_may_not_send_anything_back(): void
    {
        // Admission is the destination as often as it is the origin, and the desk that has to read the
        // correction is not always the desk allowed to make the next move: an office head opens the
        // application (admission.view) without holding enrollment.return.
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $applicationKeeper = $this->staffForOffice(6, 'OfficeHead');
        $enrollment = $this->enrollment($this->staffForOffice(4, 'DeptEvaluator'));
        $application = $this->approvedApplication($enrollment);

        $this->returnTo($registrar, $enrollment, ['destination' => ReturnDestination::Admission->value])
            ->assertSessionHasNoErrors();
        $this->assertSame('pending', $application->fresh()->admissionStatus->value);

        $target = $this->propsOf($this->actingAs($applicationKeeper)
            ->get(route('admission.show', $application)))['returnTarget'];

        $this->assertNotNull($target, 'The application was re-opened for this desk, so the page must say why.');
        $this->assertNull($target['options'], 'A desk without the return right is offered no destination at all.');
        $this->assertSame('Registrar', $target['openReturn']['originOfficeName']);
        $this->assertNotEmpty($target['openReturn']['expectedCorrection'],
            'The correction the Registrar typed has to be what the Admission desk reads back.');
    }

    #[Test]
    public function the_bell_names_the_desk_that_actually_sent_the_record(): void
    {
        // The bell sentence used to read "the Registrar returned this record to Department Evaluation"
        // because the Registrar was the only desk that could return anything. Three desks hold the act
        // now, and the second destination had no sentence at all, so a record parked at Admission fell
        // through to the default branch that leaks the enum word.
        // Two promises in one sentence. It used to read "the Registrar returned this record to
        // Department Evaluation" because the Registrar was the only desk that could return anything,
        // and the second destination had no sentence at all, so a record parked at Admission fell
        // through to the branch that leaks the enum word.
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $evaluator = $this->staffForOffice(4, 'DeptEvaluator');
        $admissionDesk = $this->staffForOffice(6, 'AdmissionOfficer');
        $toDepartment = $this->enrollment($evaluator);
        $toAdmission = $this->enrollment($evaluator);
        $this->approvedApplication($toAdmission);

        // The status listener is queued, so the sentence only exists once the job runs. This install
        // runs it synchronously — the ruling DEPLOYMENT.md carries, because a queue worker here exists
        // only to deliver the in-app bell — and the case reads the delivered message, not a fake.
        config(['queue.default' => 'sync']);

        $this->returnTo($registrar, $toDepartment)->assertSessionHasNoErrors();
        $this->returnTo($registrar, $toAdmission, ['destination' => ReturnDestination::Admission->value])
            ->assertSessionHasNoErrors();

        $messages = DB::table('notifications')
            ->where('type', 'enrollment_status_changed')
            ->pluck('data')
            ->map(fn (string $json): string => (string) (json_decode($json, true)['message'] ?? ''));

        $this->assertTrue(
            $messages->contains(fn (string $m): bool => str_contains($m, "{$registrar->name} returned this record to Department Evaluation")),
            'The bell still names the Registrar rather than the desk that made the act.'
        );
        $this->assertTrue(
            $messages->contains(fn (string $m): bool => str_contains($m, "{$registrar->name} returned this record to Admission")),
            'A record sent to Admission had no sentence for the desk that owes it.'
        );

        // The recipient is the desk the record was sent TO. This is the ordering trap: both listeners
        // run inside the act's own transaction, and `ReturnRecorder` re-opens the boxes only after the
        // transition fires — so reading the box list here answers the desk that would have been next
        // had the record moved forward (Assessment), and the return rang nobody at all.
        $recipients = DB::table('notifications')
            ->where('type', 'enrollment_status_changed')
            ->pluck('notifiable_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $expected = [$admissionDesk->userId, $evaluator->userId];
        sort($expected);

        $this->assertSame(
            $expected,
            $recipients,
            'A return addressed the wrong desk: one notice per destination, and no other recipient.'
        );
    }

    #[Test]
    public function a_paid_record_sent_back_comes_home_through_the_ordinary_desk_acts(): void
    {
        // §27.2 E-4's second trigger said a record that reached `paid`, went back to the department, and
        // was re-costed could not get back to `paid`: finalize fires only from `evaluated`, a receipt only
        // from `assessed`, and the balance a return leaves is already zero. E-1's settlement is the answer,
        // and this drives the whole round trip instead of asserting it — every step an ordinary desk act,
        // by an account holding the right that act asks for.
        $department = $this->staffForOffice(4, 'DeptEvaluator');
        $registrar = $this->staffForOffice(1, 'RegistrarApprover');
        $assessmentDesk = $this->staffForOffice(3, 'ScholarshipOfficer');
        $accounting = $this->staffForOffice(2, 'AccountingStaff');

        $enrollment = $this->enrollment($department);
        $workflowId = $enrollment->enrollmentworkflow->workflowId;

        // The record is at `paid` because money was handed over, and a return never un-hands it
        // (ruling 14). Without the receipt the account would read as owing, and the settlement this
        // round trip depends on would refuse for a reason that has nothing to do with the return.
        Payments::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'orNumber' => 'RET-OR-'.uniqid(),
            'amount' => 1000,
            'paymentDate' => now(),
            'paymentMode' => PaymentMode::Cash,
            'paymentStatus' => PaymentStatus::Paid,
            'processedBy' => $accounting->userId,
        ]);

        $subject = Subjects::create([
            'subjectCode' => 'RET-101',
            'subjectName' => 'Returned Load Subject',
            'lectureUnits' => 3,
            'labUnits' => 0,
            'subjectType' => 'lecture',
        ]);

        $this->returnTo($registrar, $enrollment)->assertSessionHasNoErrors();
        $this->assertSame(EnrollmentStatus::ReturnedToEvaluation, $enrollment->fresh()->enrollmentStatus);

        // The department's own way back is the proposal: `EvaluationPolicy::proposeSubjects` opens
        // `returnedToEvaluation` to this desk on purpose ("registrar-returned enrollments come back to
        // this desk for re-proposal"), and it is the act that moves the record to `evaluated`. Signing
        // alone never changes a status.
        $this->actingAs($department)
            ->post(route('evaluation.subjects.propose', $enrollment), ['subjects' => [['subjectId' => $subject->subjectId]]])
            ->assertSessionHasNoErrors();
        $this->assertSame(EnrollmentStatus::Evaluated, $enrollment->fresh()->enrollmentStatus);

        // BR32 holds the Sign act until the profile is complete, and the signature is what Assessment
        // reads before it finalizes — so the fixture supplies the real facts rather than stamping the
        // date the act would have written.
        $this->completeProfile($enrollment->fresh());

        $this->actingAs($department)
            ->post(route('evaluation.sign', $enrollment))
            ->assertSessionHasNoErrors();

        $assessment = $enrollment->fresh()->studentassessments;
        $this->assertNotNull($assessment, 'The record came back with the fee sheet the return left standing.');

        $this->actingAs($assessmentDesk)
            ->post(route('assessment.finalize', $assessment))
            ->assertSessionHasNoErrors();
        $this->assertSame(EnrollmentStatus::Assessed, $enrollment->fresh()->enrollmentStatus);

        $this->actingAs($accounting)
            ->post(route('accounting.settle', $assessment))
            ->assertSessionHasNoErrors();
        $this->assertSame(EnrollmentStatus::Paid, $enrollment->fresh()->enrollmentStatus);

        $this->assertSame(0.0, (float) $assessment->fresh()->outstandingBalance(),
            'The round trip must not manufacture a balance for a return to collect.');

        $this->assertSame(['returnedToEvaluation', 'evaluated', 'assessed', 'paid'],
            DB::table('enrollmentstatushistory')->where('enrollmentId', $enrollment->enrollmentId)
                ->orderBy('changedAt')->orderBy('historyId')->pluck('toStatus')->all(),
            'The trail is the sentence a desk reads: sent back, re-evaluated, re-costed, settled again.');

        foreach ([4, 3, 2] as $officeId) {
            $this->assertNotNull(
                Workflowsteps::where('workflowId', $workflowId)->where('officeId', $officeId)->value('signedDate'),
                "Office {$officeId}'s box was cleared by the return and has to be signed again for the record to move."
            );
        }

        $this->assertSame(1, Enrollmentreturns::where('enrollmentId', $enrollment->enrollmentId)->count(),
            'Coming home does not un-write the return that sent it back.');
    }
}
