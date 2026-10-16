<?php

namespace App\Console\Commands;

use App\Enums\EnrollmentStatus;
use App\Enums\IdRequestStatus;
use App\Enums\OfficeId;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowStepStatus;
use App\Models\Documentprintlog;
use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Idrequests;
use App\Models\Workflowsteps;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Report records that no desk action can produce.
 *
 * Every check here came out of the audit as a hand measurement — a face photo a validated
 * request does not have, a box signed by nobody, a workflow that claims it is finished while
 * a box is still open, a request still pending under a box its own office already signed.
 * Each was found by writing a query against live `ems` and staring at the number, so the
 * next one is only found if somebody does that again. This command does it on demand, and it
 * writes nothing.
 *
 * Two severities, because the two findings mean different things: an IMPOSSIBLE state says a
 * row was edited by hand or a guard has regressed, and a non-zero count fails the run; a
 * DECISION count is an open question the owner already knows about, reported so it is not
 * silently re-measured differently by the next person.
 *
 * Every predicate is built through Eloquent against class constants. A morph column stores a
 * single-backslash class name, but the MySQL client prints it doubled, so an SQL literal is
 * how a healthy database gets reported as broken — see the withdrawn notification finding of
 * 2026-10-11.
 */
class AuditRecordStates extends Command
{
    protected $signature = 'ems:audit:record-states';

    protected $description = 'Report records no desk action can produce, and the known open decisions beside them';

    public function handle(): int
    {
        $findings = [
            'A validated ID request with no face photo on file' => [
                'count' => $this->validatedWithoutPhoto(),
                'severity' => 'impossible',
                'why' => 'IDPolicy::validate requires filled(cardPhotoPath), so only a hand edit can leave a signed request without its picture.',
            ],
            'A validated ID request with no validator recorded' => [
                'count' => $this->validatedWithoutSigner(),
                'severity' => 'impossible',
                'why' => 'IDController::validate stamps validatedBy in the same update, so an empty one means the status was written around the desk.',
            ],
            'A pending ID request under an ID box that is already signed' => [
                'count' => $this->pendingUnderSignedIdBox(),
                'severity' => 'impossible',
                'why' => 'Validation is terminal and it is the box signing, so this request can never be acted on: it is the state idrequests 53 sat in until it was deleted 2026-10-11.',
            ],
            'A workflow box completed with no signer' => [
                'count' => $this->completedBoxWithoutSigner(),
                'severity' => 'impossible',
                'why' => 'WorkflowService::signStep writes signedBy and signedDate together; a box with a status and no name is a signature the paper trail cannot show.',
            ],
            'A workflow box pending but carrying a signer' => [
                'count' => $this->pendingBoxWithSigner(),
                'severity' => 'impossible',
                'why' => 'The reverse half of the same rule: an unsigned box must not name an officer.',
            ],
            'A workflow marked completed while a box is still open' => [
                'count' => $this->completedWorkflowWithOpenBox(),
                'severity' => 'impossible',
                'why' => 'signStep sets the workflow completed only when no box remains uncompleted.',
            ],
            'A workflow still open while every box is signed' => [
                'count' => $this->openWorkflowWithAllBoxesSigned(),
                'severity' => 'impossible',
                'why' => 'The same rule seen from the other side; a desk would show the record as unfinished forever.',
            ],
            'An enrollment past a stage whose box for it is still open' => [
                'count' => $this->statusAheadOfItsBox(),
                'severity' => 'impossible',
                'why' => 'Approving an enrollment signs the Registrar box and settling an account signs Accounting\'s, so the record cannot be ahead of the signature that moved it.',
            ],
            'A notice addressed to a class that does not exist' => [
                'count' => $this->undeliverableNotices(),
                'severity' => 'impossible',
                'why' => 'A morph value no bell can resolve is a message nobody will ever read, which is how the notification channel was dead at both ends before C.1.',
            ],
            'A printed paper attributable to no enrollment, student or block' => [
                'count' => $this->unattributablePrintRows(),
                // Promoted from `decision` on 2026-10-15, after the owner's ruling on the 21 legacy rows. It
                // was only ever a note because somebody had to decide whether to prune or attribute them; the
                // decision is made, so a key-less row left in the table can only be a write that logged a
                // paper without any of its three keys.
                'severity' => 'impossible',
                'why' => 'Docx §25 P-14: MySQL treats a NULL enrollment as distinct, so the unique index cannot reject a repeat for a key-less row and no scope counts it. No paper this application prints leaves all three keys NULL — a slip carries its student, a roster its block, everything else its enrollment — so this count is a write path, not legacy data. The 21 rows live `ems` carried were pruned under an owner ruling on 2026-10-15.',
            ],
            'A Clinic box signed with no health assessment behind it' => [
                'count' => $this->clinicBoxWithoutRecord(),
                'severity' => 'decision',
                'why' => 'Recording the assessment is the signature on this box (§14R.2 of the acceptance script), so no desk could leave it signed with nothing on file. The seven past-term records that sat here were retired on 2026-10-15 and the demo seeder now writes the assessment behind every box it signs, so a non-zero count names a live box no desk will close — a dropped enrollment is excluded above, because a retired record is not work.',
            ],
            'An ID box signed with no validated request behind it' => [
                'count' => $this->idBoxWithoutValidatedRequest(),
                'severity' => 'decision',
                'why' => 'Validation is the ID box signing, so a signed box normally carries the request that signed it. Enrollment 53 is the exception by ruling, not by accident: its contradictory request row was deleted 2026-10-11 and the signature was deliberately left standing.',
            ],
        ];

        $impossible = 0;
        $decisions = 0;

        foreach ($findings as $label => $finding) {
            $count = $finding['count'];

            if ($finding['severity'] === 'impossible') {
                $impossible += $count;
                $mark = $count === 0 ? 'ok  ' : 'FAIL';
            } else {
                $decisions += $count;
                $mark = $count === 0 ? 'ok  ' : 'note';
            }

            $this->line(sprintf('  %s  %-62s %4d', $mark, $label, $count));

            if ($count > 0) {
                $this->line('        '.$finding['why']);
            }
        }

        $this->newLine();
        // The rule count is derived from the list above. The table names a previous version of this
        // sentence enumerated are left out on purpose: a hand-kept inventory of what a command looks
        // at goes stale the day someone adds a rule, which is the defect pass 92 found in the docx.
        $this->info('Rules run: '.count($findings).'; each is listed above with its own count and the reason it is worth looking at.');

        if ($impossible === 0 && $decisions === 0) {
            $this->info('Every state the application is supposed to prevent is prevented, and no open decision row is on file.');

            return self::SUCCESS;
        }

        if ($impossible > 0) {
            $this->error($impossible.' record(s) in a state no desk action can produce. Fix the write path, or restore the row from a dump — do not edit the status column by hand.');
        }

        if ($decisions > 0) {
            $this->warn($decisions.' record(s) waiting on a decision this command cannot make (see the note above each).');
        }

        return $impossible > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function validatedWithoutPhoto(): int
    {
        return Idrequests::where('status', IdRequestStatus::Validated)
            ->where(function ($q): void {
                $q->whereNull('cardPhotoPath')->orWhere('cardPhotoPath', '');
            })
            ->count();
    }

    private function validatedWithoutSigner(): int
    {
        return Idrequests::where('status', IdRequestStatus::Validated)
            ->whereNull('validatedBy')
            ->count();
    }

    /**
     * A request still open on an enrollment whose ID office box is already signed.
     *
     * Read through the relations rather than a join so the office id comes from OfficeId and
     * the status from the enum cast, not from a literal a later rename can miss.
     */
    private function pendingUnderSignedIdBox(): int
    {
        return Idrequests::where('status', IdRequestStatus::Pending)
            ->whereHas('enrollment.enrollmentworkflow.workflowsteps', fn ($q) => $q
                ->where('officeId', OfficeId::IdOffice->value)
                ->where('stepStatus', WorkflowStepStatus::Completed->value))
            ->count();
    }

    private function completedBoxWithoutSigner(): int
    {
        return Workflowsteps::where('stepStatus', WorkflowStepStatus::Completed->value)
            ->whereNull('signedBy')
            ->count();
    }

    private function pendingBoxWithSigner(): int
    {
        return Workflowsteps::where('stepStatus', WorkflowStepStatus::Pending->value)
            ->whereNotNull('signedBy')
            ->count();
    }

    private function completedWorkflowWithOpenBox(): int
    {
        return Enrollmentworkflow::where('workflowStatus', WorkflowStatus::Completed)
            ->whereHas('workflowsteps', fn ($q) => $q->where('stepStatus', '!=', WorkflowStepStatus::Completed->value))
            ->count();
    }

    private function openWorkflowWithAllBoxesSigned(): int
    {
        return Enrollmentworkflow::where('workflowStatus', '!=', WorkflowStatus::Completed)
            ->whereDoesntHave('workflowsteps', fn ($q) => $q->where('stepStatus', '!=', WorkflowStepStatus::Completed->value))
            ->count();
    }

    private function statusAheadOfItsBox(): int
    {
        /** @var array<int, array{status: EnrollmentStatus, office: OfficeId}> $pairs */
        $pairs = [
            ['status' => EnrollmentStatus::Enrolled, 'office' => OfficeId::Registrar],
            ['status' => EnrollmentStatus::Paid, 'office' => OfficeId::Accounting],
        ];

        $total = 0;

        foreach ($pairs as $pair) {
            $total += Enrollments::where('enrollmentStatus', $pair['status'])
                ->whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q
                    ->where('officeId', $pair['office']->value)
                    ->where('stepStatus', WorkflowStepStatus::Pending->value))
                ->count();
        }

        return $total;
    }

    private function undeliverableNotices(): int
    {
        $broken = DB::table('notifications')
            ->distinct()
            ->pluck('notifiable_type')
            ->reject(fn (string $type): bool => class_exists($type))
            ->values()
            ->all();

        if ($broken === []) {
            return 0;
        }

        return DB::table('notifications')->whereIn('notifiable_type', $broken)->count();
    }

    private function unattributablePrintRows(): int
    {
        return Documentprintlog::whereNull('enrollmentId')
            ->whereNull('studentId')
            ->whereNull('blockId')
            ->count();
    }

    /**
     * An enrollment whose Clinic box reads completed while nothing was ever recorded for it.
     *
     * Counted on the enrollment rather than the step, because one enrollment can hold several
     * boxes and the desk sees one record, not one row per box.
     */
    private function clinicBoxWithoutRecord(): int
    {
        return Enrollments::whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q
            ->where('officeId', OfficeId::Clinic->value)
            ->where('stepStatus', WorkflowStepStatus::Completed->value))
            // A retired enrollment's bare box is history, not a decision waiting to be made: no desk will ever
            // act on it again, so counting it keeps the note non-zero forever and teaches the operator to
            // skim the one number that is meant to be acted on. Lane 36 found this on a rehearsal clone —
            // seven enrollments were dropped and the count stayed 7.
            ->where('enrollmentStatus', '!=', EnrollmentStatus::Dropped->value)
            ->whereDoesntHave('clinicrecords')
            ->count();
    }

    private function idBoxWithoutValidatedRequest(): int
    {
        return Enrollments::whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q
            ->where('officeId', OfficeId::IdOffice->value)
            ->where('stepStatus', WorkflowStepStatus::Completed->value))
            ->where('enrollmentStatus', '!=', EnrollmentStatus::Dropped->value)
            ->whereDoesntHave('idrequests', fn ($q) => $q->where('status', IdRequestStatus::Validated))
            ->count();
    }
}
