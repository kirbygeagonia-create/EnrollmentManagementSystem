<?php

/**
 * The two cross-office signatures in live `ems`, repointed to the desk that owns each box.
 *
 * Measured: office 4's Department Evaluation box and office 3's Assessment box, on one workflow, both
 * "signed" by `office2_head`. No desk could have produced that — `WorkflowService::signStep()` refuses a
 * cross-office signature (BR14) on every HTTP path — so the rows are the demo pipeline's literal
 * `signedBy => 3`, which stamped every completed box of that workflow with Accounting's officer. The
 * generator is fixed; this repairs the two rows it already wrote.
 *
 * What is deliberately left alone: `signedDate` and `stepStatus`. A signature claims who signed and when;
 * the *when* is the seeding moment the pipeline intended and nobody disputes it, and the record really did
 * pass those desks in the demo. Only the *who* was wrong, so only that moves.
 *
 * The write goes through the Eloquent model so `AuditLogObserver` (attached to every model at
 * `AppServiceProvider.php:44-52`) records the correction itself, with the wrong signer preserved in
 * `oldValues`. A repair that erased the evidence of the error would repeat it. Identification and
 * verification use plain SQL instead of Eloquent, because `Workflowsteps::signedBy` is both the column and
 * a `BelongsTo` of the same name — Eloquent resolves that in the reader's favour, and this script must not
 * depend on which of the two it gets.
 *
 * Dump taken first, and checked for the tables AND for the absence of any DROP DATABASE (the mistake that
 * cost live `ems` today was a clone script that carried one):
 *   C:\Users\ADMIN\ems-backups\ems-pre-lane30-signature-repair-2026-10-14.sql
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';

use App\Models\Workflowsteps;
use Illuminate\Support\Facades\DB;

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apply = in_array('--apply', $argv, true);
$fail = function (string $why): never {
    fwrite(STDERR, "REFUSE: {$why}\n");
    exit(1);
};

$database = DB::connection()->getDatabaseName();
if ($database !== 'ems') {
    $fail("this script expects the `ems` connection, got '{$database}'");
}

$sql = "select ws.workflowStepId, ws.workflowId, ws.officeId, ws.signedBy, ws.stepStatus, ws.signedDate,
                w.enrollmentId, wrong.username as wrong_signer, right_owner.username as owning_desk
           from workflowsteps ws
           join enrollmentworkflow w on w.workflowId = ws.workflowId
           join staffusers wrong on wrong.userId = ws.signedBy
           join staffusers right_owner on right_owner.username = concat('office', ws.officeId, '_head')
          where ws.stepStatus = 'completed' and wrong.officeId <> ws.officeId
          order by ws.workflowStepId";

$rows = DB::select($sql);

// A second query: rows whose office has no `officeN_head` at all would vanish from the join above and be
// silently skipped, so count the foreign rows directly and insist the two figures agree.
$foreignCount = (int) DB::select("select count(*) c from workflowsteps ws join staffusers s on s.userId = ws.signedBy
        where ws.stepStatus = 'completed' and s.officeId <> ws.officeId")[0]->c;

if ($foreignCount !== 2 || count($rows) !== 2) {
    $fail("expected 2 cross-office signatures and 2 with an owning desk seated, found {$foreignCount} and ".count($rows));
}

// Recorded before any write, so the verification afterwards compares against a fact rather than a memory.
$completedBefore = (int) DB::select("select count(*) c from workflowsteps where stepStatus = 'completed'")[0]->c;

printf("%s\n\n%-12s %-7s %-12s %-16s %-16s %s\n",
    'THE PLAN — the signer moves; signedDate and stepStatus do not',
    'step', 'office', 'enrollment', 'signed by (wrong)', 'will read', 'signed date');

foreach ($rows as $r) {
    printf("%-12d %-7d %-12d %-16s %-16s %s\n",
        $r->workflowStepId, $r->officeId, $r->enrollmentId, $r->wrong_signer, $r->owning_desk, $r->signedDate);
}

if (! $apply) {
    echo "\nDRY RUN — nothing written. Pass --apply (dump taken and verified).\n";
    exit(0);
}

foreach ($rows as $r) {
    $ownerId = (int) DB::select('select userId from staffusers where username = ?', [$r->owning_desk])[0]->userId;

    /** @var Workflowsteps $step */
    $step = Workflowsteps::find($r->workflowStepId);
    if ($step === null) {
        $fail("step {$r->workflowStepId} disappeared mid-run");
    }
    if ((int) $step->getAttributeValue('signedBy') !== (int) $r->signedBy) {
        $fail("step {$r->workflowStepId} was signed by someone else in the meantime — stopping");
    }

    $step->setAttribute('signedBy', $ownerId);
    $step->save();

    $after = DB::select('select signedBy, stepStatus, signedDate from workflowsteps where workflowStepId = ?',
        [$r->workflowStepId])[0];
    if ($after->stepStatus !== 'completed' || (string) $after->signedDate !== (string) $r->signedDate) {
        $fail("step {$r->workflowStepId}: status or date moved — restore from the dump and stop");
    }
}

$left = (int) DB::select("select count(*) c from workflowsteps ws join staffusers s on s.userId = ws.signedBy
        where ws.stepStatus = 'completed' and s.officeId <> ws.officeId")[0]->c;
$total = (int) DB::select("select count(*) c from workflowsteps where stepStatus = 'completed'")[0]->c;
$audits = (int) DB::select("select count(*) c from auditlogs where entityTable = 'workflowsteps'
        and action = 'updated' and createdAt >= date_sub(now(), interval 10 minute)")[0]->c;

printf("\nforeign signatures left: %d (must be 0)\n", $left);
printf("completed boxes total:   %d (was %d; must be unchanged)\n", $total, $completedBefore);
printf("audit rows written:      %d (must be 2)\n", $audits);

if ($left !== 0 || $audits !== 2 || $total !== $completedBefore) {
    $fail('post-write verification failed — restore from ems-pre-lane30-signature-repair-2026-10-14.sql');
}

echo "\nVERIFIED. The pipeline's own self-check now reports nothing, because nothing is left to report.\n";
