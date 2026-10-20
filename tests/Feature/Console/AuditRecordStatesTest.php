<?php

namespace Tests\Feature\Console;

use App\Enums\ClinicRecordStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\IdRequestStatus;
use App\Enums\OfficeId;
use App\Enums\StudentType;
use App\Enums\UnitType;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowStepStatus;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Clinicrecords;
use App\Models\Courses;
use App\Models\Documentprintlog;
use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Idrequests;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Models\Workflowsteps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ems:audit:record-states exists because every one of these findings was produced by hand
 * against the live database during the audit — a validated request with no photo, a box
 * completed with no name on it, a request still open under a box its own office had already
 * signed. The audit is over; the question has not stopped being interesting, and nobody
 * should have to write the same SQL again to ask it.
 *
 * Two severities are pinned here on purpose. An IMPOSSIBLE state fails the run, because the
 * application cannot produce it, so its presence means a guard regressed or a row was edited
 * behind a desk's back. A DECISION is only NOTED, because the owner has already seen that shape
 * and chosen to keep it — the unattributable printed papers, and since 2026-10-12 the two boxes
 * that can read signed without the record that signs them — and a report tool must not decide
 * those over their hand.
 */
class AuditRecordStatesTest extends TestCase
{
    use RefreshDatabase;

    private Enrollments $enrollment;

    private Staffusers $signer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedState();
    }

    /**
     * A record exactly as the desks would leave it: every box ahead of the ID office signed,
     * the ID box open, and one face-photo request waiting on it.
     */
    private function seedState(): void
    {
        Offices::insert([
            ['officeId' => 1, 'officeName' => 'Registrar'],
            ['officeId' => 2, 'officeName' => 'Accounting'],
            ['officeId' => 3, 'officeName' => 'Scholarship'],
            ['officeId' => 4, 'officeName' => 'Academic Department'],
            ['officeId' => 5, 'officeName' => 'Blocking'],
            ['officeId' => 11, 'officeName' => 'Clinic'],
            ['officeId' => 22, 'officeName' => 'ID Office'],
        ]);

        Religions::create(['religionId' => 1, 'religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitCode' => 'CCS',
            'unitName' => 'College of Computer Studies',
            'unitType' => UnitType::College,
        ]);

        $courseId = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ])->courseId;

        $year = Academicyears::create([
            'yearStart' => 2024,
            'yearEnd' => 2025,
            'yearLabel' => '2024-2025',
            'startDate' => '2024-06-01',
            'endDate' => '2025-05-31',
        ]);

        $termId = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2024-06-01',
            'endDate' => '2024-10-31',
        ])->termId;

        $student = Students::create([
            'schoolIdNumber' => 'TEST-'.uniqid(),
            'lastName' => 'Dela Cruz',
            'firstName' => 'Juan',
            'middleName' => 'M',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'email' => 'student_'.uniqid().'@example.com',
            'username' => 'student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $staff = Staffusers::factory()->make([
            'officeId' => OfficeId::IdOffice->value,
            'role' => 'staff',
            'employeeNo' => 'EMP-'.uniqid(),
            'username' => 'user_'.uniqid(),
            'email' => 'user_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();

        $this->signer = $staff;

        $this->enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $courseId,
            'termId' => $termId,
            'yearLevel' => 1,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => 'new',
            'academicStanding' => 'regular',
            'enrollmentStatus' => 'enrolled',
            'evaluatedBy' => $staff->userId,
        ]);

        $workflow = Enrollmentworkflow::create([
            'enrollmentId' => $this->enrollment->enrollmentId,
            'currentStep' => 7,
            'workflowStatus' => WorkflowStatus::InProgress,
        ]);

        // Office 3 (Assessment) only sits in a first-year form, so the seven boxes are
        // [4, 3, 2, 1, 5, 11, 22] and the ID box is the last one still open.
        foreach ([[4, 1], [3, 2], [2, 3], [1, 4], [5, 5], [11, 6], [22, 7]] as [$officeId, $order]) {
            Workflowsteps::create([
                'workflowId' => $workflow->workflowId,
                'officeId' => $officeId,
                'stepOrder' => $order,
                'stepStatus' => $order <= 6 ? WorkflowStepStatus::Completed : WorkflowStepStatus::Pending,
                'signedBy' => $order <= 6 ? $staff->userId : null,
                'signedDate' => $order <= 6 ? now() : null,
            ]);
        }

        Idrequests::create([
            'enrollmentId' => $this->enrollment->enrollmentId,
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Contact',
            'emergencyContactNumber' => '09171234567',
            'bloodType' => 'O+',
            'cardPhotoPath' => 'id-photos/captured.jpg',
            'requestDate' => now(),
            'status' => IdRequestStatus::Pending,
        ]);

        // The Clinic box above is signed, and recording the assessment *is* that signature
        // (§14R.2), so a record the desks genuinely produced has the row behind the box. Without
        // it the clean fixture would trip the rule this file added on 2026-10-12 and the "all
        // clean" test would be asserting a state the owner called impossible.
        Clinicrecords::create([
            'enrollmentId' => $this->enrollment->enrollmentId,
            'heightCm' => 170.0,
            'weightKg' => 65.0,
            'bloodPressure' => '110/70',
            'philhealthRegistered' => 1,
            'assessmentNotes' => 'Cleared for enrollment.',
            'findings' => 'Normal',
            'clinicStaffId' => $staff->userId,
            'assessmentDate' => now()->toDateString(),
            'status' => ClinicRecordStatus::Completed,
        ]);
    }

    /**
     * Artisan::output() drains the buffer, so the report is read once per run and kept.
     */
    private string $lastOutput = '';

    private function runAudit(string $command): int
    {
        $code = Artisan::call($command);
        $this->lastOutput = Artisan::output();

        return $code;
    }

    private function commandOutput(): string
    {
        return $this->lastOutput;
    }

    #[Test]
    public function a_record_the_desks_produced_reports_every_rule_clean(): void
    {
        $this->assertSame(0, $this->runAudit('ems:audit:record-states'));

        $out = $this->commandOutput();

        // Every rule line has to be marked ok on the line itself. Matching a label anywhere in
        // the output would pass on a rule the command never evaluated, because the label prints
        // whether its count is 0 or 5 — a mutant of one check proved exactly that.
        foreach ([
            'A validated ID request with no face photo on file',
            'A validated ID request with no validator recorded',
            'A pending ID request under an ID box that is already signed',
            'A workflow box completed with no signer',
            'A workflow box pending but carrying a signer',
            'A workflow marked completed while a box is still open',
            'A workflow still open while every box is signed',
            'An enrollment past a stage whose box for it is still open',
            'A notice addressed to a class that does not exist',
            'A printed paper attributable to no enrollment',
            'A Clinic box signed with no health assessment behind it',
            'An ID box signed with no validated request behind it',
            'A fee sheet whose total has no fee lines behind it',
            'A receipt on an account that never entered `paid`',
        ] as $label) {
            $this->assertFlagged('ok', $label);
        }

        $this->assertStringContainsString('Every state the application is supposed to prevent is prevented', $out);
        $this->assertStringNotContainsString('FAIL', $out);

        // The headline number has to be derived from the report, not typed beside it: count the
        // marked rule lines, then require the summary to carry that same number. Pinning the
        // literal instead would make every legitimate new rule a test failure — the same trap
        // ems:preflight avoided for the permission count.
        preg_match_all('/^  (?:ok|FAIL|note)\s+\S/m', $out, $marked);
        $this->assertMatchesRegularExpression('/Rules run: (\d+)/', $out, 'The summary should state how many rules ran.');
        preg_match('/Rules run: (\d+)/', $out, $stated);
        $this->assertSame(
            count($marked[0]),
            (int) $stated[1],
            'The stated rule count must equal the number of marked lines actually printed.'
        );
        $this->assertGreaterThanOrEqual(10, count($marked[0]));
    }

    /**
     * Assert the mark and the label share a line, so a rule that reported nothing cannot pass
     * a test that expects it to have fired.
     */
    private function assertFlagged(string $mark, string $label): void
    {
        $pattern = '/'.preg_quote($mark, '/').'\s+'.preg_quote($label, '/').'/';

        $this->assertMatchesRegularExpression(
            $pattern,
            $this->commandOutput(),
            "Expected the rule to be marked {$mark} on its own line: {$label}"
        );
    }

    #[Test]
    public function a_validated_request_without_its_photo_or_its_validator_fails_the_run(): void
    {
        Idrequests::where('enrollmentId', $this->enrollment->enrollmentId)
            ->update(['status' => IdRequestStatus::Validated->value, 'cardPhotoPath' => null, 'validatedBy' => null]);

        $this->assertSame(1, $this->runAudit('ems:audit:record-states'));

        $this->assertFlagged('FAIL', 'A validated ID request with no face photo on file');
        $this->assertFlagged('FAIL', 'A validated ID request with no validator recorded');
        $this->assertStringContainsString('state no desk action can produce', $this->commandOutput());
    }

    #[Test]
    public function a_request_still_open_under_a_box_its_own_office_signed_is_named(): void
    {
        // Exactly the state idrequests 53 sat in, found by hand on 2026-10-10.
        Workflowsteps::where('officeId', OfficeId::IdOffice->value)
            ->update([
                'stepStatus' => WorkflowStepStatus::Completed->value,
                'signedBy' => $this->signer->userId,
                'signedDate' => now(),
            ]);

        $this->assertSame(1, $this->runAudit('ems:audit:record-states'));

        $this->assertFlagged('FAIL', 'A pending ID request under an ID box that is already signed');
    }

    #[Test]
    public function a_box_signed_without_the_record_behind_it_is_noted_and_leaves_the_run_green(): void
    {
        // The shape the owner ruled on 2026-10-12. A Clinic box is signed by the assessment and an
        // ID box by the validation, so a signed box with no record behind it cannot come from a
        // desk — but it can come from a ruling: enrollment 53's contradictory request was deleted
        // and its signature left standing, and older-term demo records were seated before the
        // clinic rule was written. Reported, never repaired by the tool that reports it.
        Clinicrecords::where('enrollmentId', $this->enrollment->enrollmentId)->delete();

        Workflowsteps::where('officeId', OfficeId::IdOffice->value)
            ->update([
                'stepStatus' => WorkflowStepStatus::Completed->value,
                'signedBy' => $this->signer->userId,
                'signedDate' => now(),
            ]);
        Idrequests::where('enrollmentId', $this->enrollment->enrollmentId)->delete();
        $this->enrollment->fresh()->enrollmentworkflow->update(['workflowStatus' => WorkflowStatus::Completed]);

        $this->assertSame(0, $this->runAudit('ems:audit:record-states'));

        $this->assertFlagged('note', 'A Clinic box signed with no health assessment behind it');
        $this->assertFlagged('note', 'An ID box signed with no validated request behind it');
        $this->assertStringNotContainsString('FAIL', $this->commandOutput());
        $this->assertStringContainsString('waiting on a decision this command cannot make', $this->commandOutput());
    }

    #[Test]
    public function a_retired_enrollment_stops_counting_towards_the_two_ruled_notes(): void
    {
        // Lane 36 (2026-10-15). The record-state review found the seven enrollments behind both notes are
        // SUPERSEDED copies — each student already holds a later enrollment — and the owner ruled to retire
        // them through the Registrar's own drop. Rehearsing that on a clone showed the report did not move at
        // all: both rules asked only "completed box, no record", never "is this enrollment still live?", so a
        // decision the owner took would have changed the data and left the number identical. Retired rows stop
        // counting here; the same fixture, left `enrolled`, still counts — asserted in the test above, so
        // neither half of the rule can quietly go dead.
        Clinicrecords::where('enrollmentId', $this->enrollment->enrollmentId)->delete();

        Workflowsteps::where('officeId', OfficeId::IdOffice->value)
            ->update([
                'stepStatus' => WorkflowStepStatus::Completed->value,
                'signedBy' => $this->signer->userId,
                'signedDate' => now(),
            ]);
        Idrequests::where('enrollmentId', $this->enrollment->enrollmentId)->delete();
        $this->enrollment->fresh()->enrollmentworkflow->update(['workflowStatus' => WorkflowStatus::Completed]);

        $this->enrollment->fresh()->update(['enrollmentStatus' => EnrollmentStatus::Dropped]);

        $this->assertSame(0, $this->runAudit('ems:audit:record-states'));

        $this->assertFlagged('ok', 'A Clinic box signed with no health assessment behind it');
        $this->assertFlagged('ok', 'An ID box signed with no validated request behind it');
        $this->assertStringNotContainsString('FAIL', $this->commandOutput());
    }

    #[Test]
    public function a_box_whose_signature_and_status_disagree_is_reported_in_both_directions(): void
    {
        $clinic = Workflowsteps::where('officeId', OfficeId::Clinic->value)->first();
        $blocking = Workflowsteps::where('officeId', OfficeId::Blocking->value)->first();

        $clinic->update(['signedBy' => null]);
        $blocking->update(['stepStatus' => WorkflowStepStatus::Pending, 'signedBy' => $this->signer->userId]);

        $this->assertSame(1, $this->runAudit('ems:audit:record-states'));

        $this->assertFlagged('FAIL', 'A workflow box completed with no signer');
        $this->assertFlagged('FAIL', 'A workflow box pending but carrying a signer');
    }

    #[Test]
    public function a_workflow_that_claims_a_completion_its_boxes_do_not_agree_with_is_caught(): void
    {
        $workflow = $this->enrollment->fresh()->enrollmentworkflow;
        $workflow->update(['workflowStatus' => WorkflowStatus::Completed]);

        $this->assertSame(1, $this->runAudit('ems:audit:record-states'));
        $this->assertStringContainsString('A workflow marked completed while a box is still open', $this->commandOutput());

        // And the other side: the ID desk cannot sign the last box through a route that
        // forgets to close the workflow, so an all-signed form still listed as in progress
        // would strand the record.
        $workflow->update(['workflowStatus' => WorkflowStatus::InProgress]);
        Workflowsteps::where('workflowId', $workflow->workflowId)
            ->update([
                'stepStatus' => WorkflowStepStatus::Completed->value,
                'signedBy' => $this->signer->userId,
                'signedDate' => now(),
            ]);

        $this->assertSame(1, $this->runAudit('ems:audit:record-states'));
        $this->assertStringContainsString('A workflow still open while every box is signed', $this->commandOutput());
    }

    #[Test]
    public function an_enrollment_sitting_past_a_stage_its_office_has_not_signed_is_named(): void
    {
        Workflowsteps::where('officeId', OfficeId::Registrar->value)
            ->update(['stepStatus' => WorkflowStepStatus::Pending->value, 'signedBy' => null, 'signedDate' => null]);

        $this->assertSame(1, $this->runAudit('ems:audit:record-states'));
        $this->assertStringContainsString('An enrollment past a stage whose box for it is still open', $this->commandOutput());
    }

    #[Test]
    public function a_notice_addressed_to_a_class_that_does_not_resolve_is_undeliverable(): void
    {
        DB::table('notifications')->insert([
            'type' => 'App\\Notifications\\WorkflowStepSigned',
            'notifiable_type' => 'App\\Models\\NobodyCanReadThis',
            'notifiable_id' => 1,
            'data' => json_encode(['title' => 'A notice that can never be opened']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, $this->runAudit('ems:audit:record-states'));

        $out = $this->commandOutput();
        $this->assertStringContainsString('A notice addressed to a class that does not exist', $out);
        $this->assertStringContainsString('a message nobody will ever read', $out);
    }

    #[Test]
    public function a_real_notice_to_a_real_model_is_not_reported_even_though_the_client_prints_it_escaped(): void
    {
        // The stored value of a morph column carries single backslashes; the MySQL client only
        // displays them doubled. This test is the difference between a check and a false alarm.
        DB::table('notifications')->insert([
            'type' => 'App\\Notifications\\WorkflowStepSigned',
            'notifiable_type' => Staffusers::class,
            'notifiable_id' => $this->signer->userId,
            'data' => json_encode(['title' => 'A notice the desk can open']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(0, $this->runAudit('ems:audit:record-states'));
        $this->assertFlagged('ok', 'A notice addressed to a class that does not exist');
    }

    #[Test]
    public function an_unattributable_printed_paper_is_impossible_and_fails_the_run(): void
    {
        // Lane 36 (2026-10-15), after the owner's ruling on the 21 rows. This rule was a `decision` for one
        // reason: the rows existed, and deleting them or attributing them was somebody's call rather than a
        // code defect. That call has been made, the rows are behind a manifest, and what is left is the write
        // path — no paper this application can print leaves all three keys NULL (a slip carries its student, a
        // roster its block, everything else its enrollment), so a key-less row now belongs in the same
        // category as the other nine rules: a state the application is supposed to prevent.
        Documentprintlog::insert([
            'documentType' => 'clearanceSlip',
            'documentNumber' => 1,
            'printedBy' => $this->signer->userId,
            'printedDate' => now(),
            'enrollmentId' => null,
            'studentId' => null,
            'blockId' => null,
        ]);

        $this->assertSame(1, $this->runAudit('ems:audit:record-states'));

        $out = $this->commandOutput();
        $this->assertFlagged('FAIL', 'A printed paper attributable to no enrollment');
        $this->assertStringContainsString('record(s) in a state no desk action can produce', $out);
        $this->assertStringNotContainsString('waiting on a decision this command cannot make', $out);
    }

    #[Test]
    public function a_fee_sheet_that_quotes_a_total_with_no_lines_under_it_is_named(): void
    {
        // Lane 53 (2026-10-18), found by weighing every account's lines against its header: DEMO-2026-014's
        // sheet carried ₱17,500 over an empty `charges` table, because the demo seeder wrote that one sheet
        // with a raw insert and never priced it. The fee sheet prints the lines under the total, so the desk
        // read a figure with nothing beneath it to add up to it.
        $feeTypeId = DB::table('feetypes')->insertGetId([
            'feeName' => 'Tuition Fee (per unit)',
            'defaultAmount' => 11250,
            'unitBasis' => 'perUnit',
        ]);

        $assessmentId = DB::table('studentassessments')->insertGetId([
            'enrollmentId' => $this->enrollment->enrollmentId,
            'totalAssessedAmount' => 17500.00,
            'totalScholarshipCoverage' => 0.00,
            'totalWaived' => 0.00,
            'remainingBalance' => 17500.00,
            'assessmentDate' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, $this->runAudit('ems:audit:record-states'));
        $this->assertFlagged('FAIL', 'A fee sheet whose total has no fee lines behind it');

        // Priced, the same sheet reads clean — the rule asks for lines, and a check that could not go quiet
        // would only be a permanent red.
        DB::table('charges')->insert([
            'assessmentId' => $assessmentId,
            'feeTypeId' => $feeTypeId,
            'amount' => 17500.00,
            'waivedAmount' => 0.00,
        ]);

        $this->assertSame(0, $this->runAudit('ems:audit:record-states'));
        $this->assertFlagged('ok', 'A fee sheet whose total has no fee lines behind it');
    }

    #[Test]
    public function money_on_an_account_that_never_recorded_being_paid_is_named(): void
    {
        // AccountingController::record() takes cash only on an `assessed` enrollment and moves it to `paid`
        // when the balance clears, and EnrollmentStateMachine::transition() is the only writer of that step —
        // so receipts on a settled account with no `-> paid` in its history mean the money arrived without the
        // desk that produces it. Five demo enrollments sat in exactly this state.
        $this->seedSettledAccount(17500.00);

        $this->assertSame(1, $this->runAudit('ems:audit:record-states'));
        $this->assertFlagged('FAIL', 'A receipt on an account that never entered `paid`');

        $this->recordPaidStep();

        $this->assertSame(0, $this->runAudit('ems:audit:record-states'));
        $this->assertFlagged('ok', 'A receipt on an account that never entered `paid`');
    }

    #[Test]
    public function an_account_the_school_owes_nothing_because_it_owes_nothing_is_not_reported(): void
    {
        // The other half of the rule, and the reason it asks for a receipt rather than only a zero balance: a
        // grant can cover an account outright, and §17 shows on the sheet that such an account owed no paper.
        // Reporting it would make every scholarship demo a permanent red.
        $this->seedSettledAccount(0.00);

        $this->assertSame(0, $this->runAudit('ems:audit:record-states'));
        $this->assertFlagged('ok', 'A receipt on an account that never entered `paid`');
    }

    #[Test]
    public function a_receipt_that_claims_settlement_over_a_balance_still_owing_is_named(): void
    {
        // The desk's own vocabulary: AccountingController::record() writes `partial` for every peso taken while
        // a balance remains and reserves `paid` for the money that clears the account. A `paid` receipt over an
        // owing sheet therefore states a settlement that same desk refused to state — and the status is read as a
        // fact by RegistrarController::blockingReasons(). The demo's ₱8,000 installment was written this way.
        $this->seedOwingAccount(8000.00, 'paid');

        $this->assertSame(1, $this->runAudit('ems:audit:record-states'));
        $this->assertFlagged('FAIL', 'A receipt that says the account is settled while it still owes');
    }

    #[Test]
    public function an_installment_marked_partial_overs_a_balance_is_the_desk_answering_correctly(): void
    {
        // The same money and the same owing balance, one word different: this is the state the cashier leaves
        // behind, so the rule must read it as clean or every part payment on the demo day is a fault.
        $this->seedOwingAccount(8000.00, 'partial');

        $this->assertSame(0, $this->runAudit('ems:audit:record-states'));
        $this->assertFlagged('ok', 'A receipt that says the account is settled while it still owes');
    }

    /**
     * A fee sheet settled to zero and, unless told otherwise, the receipt that settled it.
     */
    private function seedSettledAccount(float $receipt): void
    {
        $feeTypeId = DB::table('feetypes')->insertGetId([
            'feeName' => 'Tuition Fee (per unit)',
            'defaultAmount' => 17500,
            'unitBasis' => 'perUnit',
        ]);

        $assessmentId = DB::table('studentassessments')->insertGetId([
            'enrollmentId' => $this->enrollment->enrollmentId,
            'totalAssessedAmount' => 17500.00,
            'totalScholarshipCoverage' => $receipt > 0 ? 0.00 : 17500.00,
            'totalWaived' => 0.00,
            'remainingBalance' => 0.00,
            'assessmentDate' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('charges')->insert([
            'assessmentId' => $assessmentId,
            'feeTypeId' => $feeTypeId,
            'amount' => 17500.00,
            'waivedAmount' => 0.00,
        ]);

        if ($receipt === 0.00) {
            return;
        }

        DB::table('payments')->insert([
            'enrollmentId' => $this->enrollment->enrollmentId,
            'orNumber' => 'TEST-OR-'.uniqid(),
            'amount' => $receipt,
            'paymentDate' => now(),
            'paymentMode' => 'cash',
            'processedBy' => $this->signer->userId,
            'paymentStatus' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * The history row the drawer would have written. Inserted rather than produced by
     * EnrollmentStateMachine, because this file measures the register, not the machine.
     */
    private function recordPaidStep(): void
    {
        DB::table('enrollmentstatushistory')->insert([
            'enrollmentId' => $this->enrollment->enrollmentId,
            'fromStatus' => 'assessed',
            'toStatus' => 'paid',
            'changedBy' => $this->signer->userId,
            'remarks' => 'Full payment received',
            'changedAt' => now(),
        ]);
    }

    /**
     * A fee sheet that still owes, and a receipt on it carrying the status the caller names.
     */
    private function seedOwingAccount(float $receipt, string $status): void
    {
        $feeTypeId = DB::table('feetypes')->insertGetId([
            'feeName' => 'Tuition Fee (per unit)',
            'defaultAmount' => 17500,
            'unitBasis' => 'perUnit',
        ]);

        $assessmentId = DB::table('studentassessments')->insertGetId([
            'enrollmentId' => $this->enrollment->enrollmentId,
            'totalAssessedAmount' => 17500.00,
            'totalScholarshipCoverage' => 0.00,
            'totalWaived' => 0.00,
            'remainingBalance' => 17500.00 - $receipt,
            'assessmentDate' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('charges')->insert([
            'assessmentId' => $assessmentId,
            'feeTypeId' => $feeTypeId,
            'amount' => 17500.00,
            'waivedAmount' => 0.00,
        ]);

        DB::table('payments')->insert([
            'enrollmentId' => $this->enrollment->enrollmentId,
            'orNumber' => 'TEST-OR-'.uniqid(),
            'amount' => $receipt,
            'paymentDate' => now(),
            'paymentMode' => 'cash',
            'processedBy' => $this->signer->userId,
            'paymentStatus' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function two_schedules_of_one_block_at_one_hour_are_named_and_back_to_back_is_not(): void
    {
        // Ruled 2026-10-19: a group cannot be in two rooms at once, and `/blocking/{block}/schedules` is the
        // route that makes that mistake easy — it adds a subject to a block that already meets. The boundary is
        // asserted with the defect, because a meeting starting as another ends is the ordinary back-to-back
        // class and a rule that counted it would fail every real timetable.
        $this->seedTimetable(sameBlock: true, sameRoom: false, sameOfficer: false, bStart: '09:30');

        $this->assertSame(1, $this->runAudit('ems:audit:record-states'));
        $this->assertFlagged('FAIL', 'Two schedules of one block meeting at the same hour');

        DB::table('schedulemeetings')->where('startTime', '09:30')->update(['startTime' => '10:00']);

        $this->assertSame(0, $this->runAudit('ems:audit:record-states'));
        $this->assertFlagged('ok', 'Two schedules of one block meeting at the same hour');
    }

    #[Test]
    public function a_room_or_an_officer_in_two_schedules_is_named_in_one_term_only(): void
    {
        // The two questions the Blocking form already asks, so a write path that bypasses it is visible here.
        // The term half is the ruling in the other direction: rooms and officers are reused every year, so a
        // meeting in another term must not read as a collision — measured by moving one schedule's block.
        $this->seedTimetable(sameBlock: false, sameRoom: true, sameOfficer: false, bStart: '09:30');

        $this->assertSame(1, $this->runAudit('ems:audit:record-states'));
        $this->assertFlagged('FAIL', 'A room or an officer in two schedules at the same hour');

        DB::table('schedulemeetings')->where('startTime', '09:30')->update(['startTime' => '10:00']);
        $this->assertSame(0, $this->runAudit('ems:audit:record-states'));

        $this->seedTimetable(sameBlock: false, sameRoom: false, sameOfficer: true, bStart: '11:30');
        DB::table('schedulemeetings')->where('startTime', '11:30')->update(['startTime' => '09:30']);
        $this->assertSame(1, $this->runAudit('ems:audit:record-states'), 'the officer branch was not counted');

        // Now put the second schedule's block in another term: same hour, same people, no collision.
        $later = DB::table('blocks')->orderByDesc('blockId')->first();
        DB::table('blocks')->where('blockId', $later->blockId)->update(['termId' => $this->seedTerm('2nd')]);

        $this->assertSame(0, $this->runAudit('ems:audit:record-states'), 'a term other than the first vetoed the pair');
        $this->assertFlagged('ok', 'A room or an officer in two schedules at the same hour');
    }

    /**
     * One academic year, one course, two rooms, two schedules of one meeting each; the flags say what the pair
     * shares. Built by insert rather than through BlockingController, because the register is being measured —
     * the controller's own refusal is pinned in BlockingControllerTest, and a guard checked through the code it
     * guards would prove nothing about either.
     */
    private function seedTimetable(bool $sameBlock, bool $sameRoom, bool $sameOfficer, bool $sameTerm = true, string $bStart = '09:30', string $bEnd = '10:30'): void
    {
        $unitId = DB::table('academicunits')->insertGetId([
            'unitName' => 'Test Unit '.uniqid(),
            'unitType' => 'college', // UnitType::College's backing value: a raw insert bypasses the cast.
        ]);
        $courseId = DB::table('courses')->insertGetId([
            'unitId' => $unitId,
            'courseName' => 'Test Course '.uniqid(),
            'courseCode' => 'TC'.substr((string) mt_rand(100000, 999999), 0, 6),
            'requiresEntranceExam' => 0,
            'requiresCourseSpecificExam' => 0,
            'entranceExamExemptsTransferee' => 0,
            'requiresRetentionExam' => 0,
        ]);
        $subjectId = DB::table('subjects')->insertGetId([
            'subjectCode' => 'TS'.substr((string) mt_rand(100000, 999999), 0, 6),
            'subjectName' => 'Test Subject',
            'lectureUnits' => 3,
            'labUnits' => 0,
            'subjectType' => 'lecture',
        ]);

        $rooms = [];
        foreach (['Room A', 'Room B'] as $roomName) {
            $rooms[] = DB::table('rooms')->insertGetId([
                'roomName' => $roomName.' '.uniqid(),
                'capacity' => 40,
                'building' => 'Main',
            ]);
        }

        $officers = [$this->signer->userId];
        if (! $sameOfficer) {
            $officers[] = DB::table('staffusers')->insertGetId([
                'firstName' => 'Other',
                'lastName' => 'Officer',
                'username' => 'other_officer_'.uniqid(),
                'email' => 'other'.uniqid().'@example.test',
                'passwordHash' => bcrypt('password'),
                'role' => 'instructor',
                'status' => 'active',
                'employeeNo' => 'EMP-'.uniqid(),
            ]);
        }

        $termA = $this->seedTerm('1st');
        $termB = $sameTerm ? $termA : $this->seedTerm('2nd');

        $blocks = [];
        foreach ([[$courseId, $termA], [$courseId, $termB]] as $i => [$course, $term]) {
            $blocks[] = DB::table('blocks')->insertGetId([
                'courseId' => $course,
                'termId' => $term,
                'yearLevel' => 1,
                'blockName' => 'Test Block '.($i + 1).' '.uniqid(),
                'maxStudents' => 40,
                'scheduleStatus' => 'draft',
            ]);
        }
        if ($sameBlock) {
            $blocks[1] = $blocks[0];
        }

        $meetings = [
            ['09:00', '10:00', $blocks[0], $rooms[0], $officers[0]],
            [$bStart, $bEnd, $blocks[1], $sameRoom ? $rooms[0] : $rooms[1], $sameOfficer ? $officers[0] : $officers[1]],
        ];

        foreach ($meetings as [$start, $end, $blockId, $roomId, $officerId]) {
            $scheduleId = DB::table('schedules')->insertGetId([
                'blockId' => $blockId,
                'subjectId' => $subjectId,
                'instructorId' => $officerId,
                'roomId' => $roomId,
            ]);

            DB::table('schedulemeetings')->insert([
                'scheduleId' => $scheduleId,
                'dayOfWeek' => 'Monday',
                'startTime' => $start,
                'endTime' => $end,
            ]);
        }
    }

    /**
     * A term inside the year the first schedule's block already points at, so only the semester differs.
     */
    private function seedTerm(string $semester): int
    {
        $year = DB::table('academicyears')->first();
        if ($year === null) {
            $yearId = DB::table('academicyears')->insertGetId([
                'yearLabel' => '2025-2026',
                'startDate' => '2025-06-01',
                'endDate' => '2026-05-31',
            ]);
        } else {
            $yearId = $year->academicYearId;
        }

        return (int) DB::table('academicterms')->insertGetId([
            'academicYearId' => $yearId,
            'semester' => $semester,
            'startDate' => '2025-08-01',
            'endDate' => '2025-12-01',
        ]);
    }
}
