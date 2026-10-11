<?php

namespace Tests\Feature\Console;

use App\Enums\IdRequestStatus;
use App\Enums\OfficeId;
use App\Enums\StudentType;
use App\Enums\UnitType;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowStepStatus;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
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
 * behind a desk's back. The unattributable printed papers are only NOTED, because the owner
 * has not yet decided what to do with them and a report tool must not decide for them.
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
        ] as $label) {
            $this->assertFlagged('ok', $label);
        }

        $this->assertStringContainsString('Every state the application is supposed to prevent is prevented', $out);
        $this->assertStringNotContainsString('FAIL', $out);

        // The summary sentence has to be derived, not typed: count the rule lines that were
        // marked and require the headline to carry that same number.
        preg_match_all('/^  (?:ok|FAIL|note)\s+\S/m', $out, $marked);
        $this->assertCount(10, $marked[0], 'Ten rules should print a marked line each.');
        $this->assertStringContainsString('Records checked: 10 rules', $out);
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
    public function unattributable_printed_papers_are_noted_and_do_not_fail_the_run(): void
    {
        Documentprintlog::insert([
            'documentType' => 'clearanceSlip',
            'documentNumber' => 1,
            'printedBy' => $this->signer->userId,
            'printedDate' => now(),
            'enrollmentId' => null,
            'studentId' => null,
            'blockId' => null,
        ]);

        $this->assertSame(0, $this->runAudit('ems:audit:record-states'));

        $out = $this->commandOutput();
        $this->assertStringContainsString('note', $out);
        $this->assertStringContainsString('A printed paper attributable to no enrollment', $out);
        $this->assertStringContainsString('waiting on a decision this command cannot make', $out);
        $this->assertStringNotContainsString('FAIL', $out);
    }
}
