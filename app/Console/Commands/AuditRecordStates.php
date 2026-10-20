<?php

namespace App\Console\Commands;

use App\Enums\EnrollmentStatus;
use App\Enums\IdRequestStatus;
use App\Enums\OfficeId;
use App\Enums\PaymentStatus;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowStepStatus;
use App\Models\Documentprintlog;
use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Idrequests;
use App\Models\Payments;
use App\Models\Studentassessments;
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
            'A fee sheet whose total has no fee lines behind it' => [
                'count' => $this->sheetWithoutLines(),
                'severity' => 'impossible',
                'why' => 'AssessmentController::compute() writes one `charges` row per priced line in the same transaction as the total, and the fee sheet prints the lines under a header that prints the total — so a sheet with a total and no lines is a paper no desk issued, and its ₱17,500 has no breakdown to defend it.',
            ],
            'A receipt on an account that never entered `paid`' => [
                'count' => $this->receiptWithoutPaidStep(),
                'severity' => 'impossible',
                'why' => 'AccountingController::record() takes money only on an `assessed` enrollment and moves it to `paid` when the balance clears, and only EnrollmentStateMachine::transition() writes that step — so a settled account with receipts and no `paid` in its history was funded behind the desk, and the status-history panel the Registrar reads shows money that appeared by itself. A zero balance with no receipt is not this state: a grant can cover an account, and that account genuinely owes no cash.',
            ],
            'Two schedules of one block meeting at the same hour' => [
                'count' => $this->collidingSchedules('block'),
                'severity' => 'impossible',
                'why' => 'Ruled 2026-10-19: one group cannot be in two rooms at once. `storeSchedule` is addressed to a block (/blocking/{block}/schedules), so a second subject at an hour the block already meets is the ordinary way to build the timetable, and BlockingController::detectConflicts() asked only about the officer and the room until this batch. A non-zero count names a timetable the desk could never have approved, which is why the generator is checked for it too.',
            ],
            'A room or an officer in two schedules at the same hour' => [
                'count' => $this->collidingSchedules('resource'),
                'severity' => 'impossible',
                'why' => 'The two questions the Blocking desk has always asked, counted here so a write path that bypasses the form cannot leave a room teaching two classes or one officer lecturing twice at once. Compared inside the term the block belongs to, because `schedules` carries no term and rooms and officers are reused across years on purpose — a meeting in a retired term is not a collision.',
            ],
            'A receipt that says the account is settled while it still owes' => [
                'count' => $this->receiptPaidWhileOwing(),
                'severity' => 'impossible',
                'why' => 'AccountingController::record() marks a receipt `paid` only on the money that clears the balance and `partial` on every peso taken before that (§28.3 G-24), so a `paid` receipt over an account that still owes claims a settlement the same desk refused to record. The status is not decoration: `RegistrarController::blockingReasons()` reads it as the fact that a receipt settled, and the Accounting screen paints it under the amount.',
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

    /**
     * A fee sheet that states a total while the table beneath it holds nothing to add up.
     *
     * Counted per sheet rather than per enrollment, because the desk opens one account and prints
     * its lines: a retired sheet beside a live one is still a sheet that cannot be explained.
     */
    private function sheetWithoutLines(): int
    {
        return Studentassessments::where('totalAssessedAmount', '>', 0)
            ->whereDoesntHave('charges')
            ->count();
    }

    /**
     * An account the school has been paid for, on an enrollment that never recorded being paid.
     *
     * The receipt is what makes this decidable: a zero balance with no receipt is a grant covering
     * the account, and that account legitimately never reaches the drawer. The sub-query is built
     * from `Payments::held()` rather than a restated status list, because a guard that re-lists what
     * the drawer holds goes stale the day the drawer's list changes — see the money rule in AGENTS.md.
     */
    private function receiptWithoutPaidStep(): int
    {
        return Enrollments::whereIn('enrollmentId', Payments::held()->select('enrollmentId'))
            ->whereHas('studentassessments', fn ($q) => $q
                ->where('totalAssessedAmount', '>', 0)
                ->where('remainingBalance', '<=', 0))
            ->whereDoesntHave('enrollmentstatushistory', fn ($q) => $q
                ->where('toStatus', EnrollmentStatus::Paid->value))
            ->count();
    }

    /**
     * Receipts marked `paid` sitting on a fee sheet that still owes.
     *
     * Counted per receipt, because the receipt is the record that makes the claim: the desk that took
     * the money wrote one status line, and a later installment that clears the account does not make an
     * earlier `paid` true — it makes the earlier receipt's own line wrong about the moment it was taken.
     * The balance is read from the sheet's stored column, which §28.3 G-22 makes `outstandingBalance()`
     * answer, so this asks the same question the Accounting screen shows rather than a fourth one.
     */
    private function receiptPaidWhileOwing(): int
    {
        return Payments::where('paymentStatus', PaymentStatus::Paid->value)
            ->whereHas('enrollment.studentassessments', fn ($q) => $q
                ->where('totalAssessedAmount', '>', 0)
                ->where('remainingBalance', '>', 0))
            ->count();
    }

    /**
     * Schedules that meet at an hour another schedule of the same block (or the same room or officer) already
     * occupies, counted once per colliding pair by keeping only the later schedule.
     *
     * The overlap is half-open in the database's own TIME comparison — one meeting starting exactly as another
     * ends is a back-to-back class, which is the same rule `BlockingController::detectConflicts()` applies on
     * the form, so the guard cannot drift from the desk that enforces it. Both sides are reached through the
     * block, because `schedules` has no term of its own: comparing across terms would call a retired year's
     * timetable a collision, which is the ruling of 2026-10-19.
     *
     * @param  string  $on  'block' for one group in two places, 'resource' for a room or an officer in two
     */
    private function collidingSchedules(string $on): int
    {
        $query = DB::table('schedulemeetings as m1')
            ->join('schedules as s1', 's1.scheduleId', '=', 'm1.scheduleId')
            ->join('blocks as b1', 'b1.blockId', '=', 's1.blockId')
            ->join('schedulemeetings as m2', function ($j) {
                $j->on('m2.dayOfWeek', '=', 'm1.dayOfWeek')
                    ->on('m2.startTime', '<', DB::raw('m1.endTime'))
                    ->on('m2.endTime', '>', DB::raw('m1.startTime'));
            })
            ->join('schedules as s2', 's2.scheduleId', '=', 'm2.scheduleId')
            ->join('blocks as b2', 'b2.blockId', '=', 's2.blockId')
            ->whereColumn('b1.termId', 'b2.termId')
            ->whereColumn('s1.scheduleId', '<', 's2.scheduleId');

        if ($on === 'block') {
            $query->whereColumn('s1.blockId', 's2.blockId');
        } else {
            $query->where(function ($q) {
                $q->whereColumn('s1.roomId', 's2.roomId')
                    ->orWhereColumn('s1.instructorId', 's2.instructorId');
            });
        }

        return $query->distinct()->count('s2.scheduleId');
    }
}
