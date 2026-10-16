<?php

/**
 * Mutation probe for the demo pipeline's two cross-office signer counters.
 *
 * Lane 38b moved both to the same predicate signStep() consults at write time, so each counter has two edges
 * and both must be proved: a signature no desk could produce has to be reported, and a signature the
 * application grants legitimately — SysAdmin's, because `AuthServiceProvider`'s `Gate::before` answers before
 * any policy runs — must NOT be. The second edge is the one that matters here: the first version of the
 * clearance counter flagged staff8's own demo approvals, which would have made the project's admin login a
 * standing defect in its own gate, and a counter that cries wolf is a counter an operator learns to ignore.
 *
 * Usage: DB_DATABASE=ems_lane38_spent php storage/app/scratch/mutate-lane38-signers.php
 * Run without that prefix it refuses: this file rewrites signature columns, and the plain invocation is what a
 * careless operator types.
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\WorkflowStepStatus;
use App\Models\Staffusers;
use App\Models\Students;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

const TARGET = 'ems_lane38_spent';

$php = 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$root = dirname(__DIR__, 3);

if (DB::connection()->getDatabaseName() !== TARGET) {
    fwrite(STDERR, 'REFUSE: this probe rewrites signature columns and only belongs in \''
        .TARGET.'\', not in \''.DB::connection()->getDatabaseName().'\'.'."\n");
    exit(1);
}

$primaryKey = function (string $table): string {
    $column = DB::selectOne(
        'SELECT column_name AS name FROM information_schema.columns
           WHERE table_schema = ? AND table_name = ? AND column_key = ? LIMIT 1',
        [TARGET, $table, 'PRI']
    );

    if ($column === null) {
        throw new RuntimeException("{$table} has no single primary key.");
    }

    return (string) $column->name;
};

$bites = [];

$capture = function (string $table, int $id) use (&$bites, $primaryKey): void {
    $pk = $primaryKey($table);
    $row = DB::table($table)->where($pk, $id)->first();

    if ($row === null) {
        throw new RuntimeException("no {$table} row {$id} to capture");
    }

    $bites[] = ['table' => $table, 'pk' => $pk, 'id' => $id, 'row' => (array) $row];
};

$restore = function () use (&$bites): void {
    foreach (array_reverse($bites) as $bite) {
        $columns = $bite['row'];
        unset($columns[$bite['pk']]);
        DB::table($bite['table'])->where($bite['pk'], $bite['id'])->update($columns);
    }

    $bites = [];
};

$restored = true;

register_shutdown_function(function () use ($restore, &$restored): void {
    if (! $restored) {
        fwrite(STDERR, "\nshutdown: a planted signer was still in place — restoring now\n");

        try {
            $restore();
        } catch (Throwable $e) {
            fwrite(STDERR, '  restore failed: '.$e->getMessage()."\n");
        }
    }
});

$run = function () use ($php, $root): array {
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open(
        [$php, 'tools/seed_full_demo_pipeline.php'],
        $desc,
        $pipes,
        $root,
        array_merge((array) getenv(), ['DB_DATABASE' => TARGET])
    );
    $out = preg_replace('/\e\[[0-9;]*m/', '', stream_get_contents($pipes[1]).stream_get_contents($pipes[2]));

    foreach ($pipes as $p) {
        fclose($p);
    }

    return ['status' => proc_close($proc), 'out' => $out];
};

// Two signers: an office head with no blanket role, and the SysAdmin account the gate answers for.
$foreignHead = Staffusers::where('username', 'office1_head')->firstOrFail();
$sysAdmin = Staffusers::where('username', 'staff8')->firstOrFail();

// A clearance approval that is already approved, for a requirement Guidance owns — Guidance is the office whose
// academic-signer exception is widest, so the carve-out is tested rather than incidental.
$rico = Students::where('schoolIdNumber', 'DEMO-2026-007')->firstOrFail();

$approval = DB::table('clearanceapprovals as ca')
    ->join('studentclearances as sc', 'sc.studentClearanceId', '=', 'ca.studentClearanceId')
    ->join('clearancerequirements as cr', 'cr.clearanceRequirementId', '=', 'ca.clearanceRequirementId')
    ->where('sc.studentId', $rico->studentId)
    ->where('ca.status', 'approved')
    ->where('cr.officeId', '!=', $foreignHead->officeId)
    ->orderBy('ca.clearanceApprovalId')
    ->first(['ca.clearanceApprovalId', 'ca.approvedBy', 'cr.officeId']);

// A completed box on a past-term record, which the run re-attests but never re-signs.
$box = DB::table('workflowsteps as ws')
    ->join('enrollmentworkflow as w', 'w.workflowId', '=', 'ws.workflowId')
    ->join('enrollments as e', 'e.enrollmentId', '=', 'w.enrollmentId')
    ->where('ws.stepStatus', WorkflowStepStatus::Completed->value)
    ->whereNotNull('ws.signedBy')
    ->where('e.termId', '!=', 11)
    ->where('ws.officeId', '!=', $foreignHead->officeId)
    ->orderByDesc('ws.workflowStepId')
    ->first(['ws.workflowStepId', 'ws.signedBy', 'ws.officeId']);

if ($approval === null || $box === null) {
    fwrite(STDERR, "REFUSE: this schema has no past-term signed box or no approved clearance line for another "
        ."office — the demo pipeline never ran here.\n");
    exit(1);
}

echo 'Probe target: '.TARGET."\n";
printf("  clearance approval %-6d (requirement office %d) → foreign head %d, SysAdmin %d\n",
    $approval->clearanceApprovalId, $approval->officeId, $foreignHead->userId, $sysAdmin->userId);
printf("  workflow box       %-6d (office %d) → foreign head %d, SysAdmin %d\n\n",
    $box->workflowStepId, $box->officeId, $foreignHead->userId, $sysAdmin->userId);

$verdicts = [];

$check = function (string $what, int $wantStatus, string $wantPhrase, array $result) use (&$verdicts): void {
    $hasPhrase = str_contains($result['out'], $wantPhrase);
    $ok = $result['status'] === $wantStatus && ($wantStatus === 0 || $hasPhrase);
    $verdicts[] = $ok;

    printf(
        "%s  %-58s exit %-2d  %-24s\n",
        $ok ? 'PASS' : 'FAIL',
        $what,
        $result['status'],
        $hasPhrase ? 'names the finding' : 'no such finding'
    );

    if (! $ok) {
        echo "     expected exit {$wantStatus}".($wantStatus === 1 ? " and the phrase '{$wantPhrase}'" : '')
            ."; output follows.\n".$result['out']."\n";
    }
};

$check('control — nothing planted', 0, '', $run());

// 1. A clearance line approved by an officer who owns neither the requirement nor the blanket role.
$capture('clearanceapprovals', (int) $approval->clearanceApprovalId);
$restored = false;
DB::table('clearanceapprovals')->where('clearanceApprovalId', $approval->clearanceApprovalId)
    ->update(['approvedBy' => $foreignHead->userId]);
$check('clearance line signed by another office’s head', 1, 'Clearance:', $run());
$restored = true;
$restore();

// 2. The same line signed by SysAdmin — a state the application produces, so it must not be reported.
$capture('clearanceapprovals', (int) $approval->clearanceApprovalId);
$restored = false;
DB::table('clearanceapprovals')->where('clearanceApprovalId', $approval->clearanceApprovalId)
    ->update(['approvedBy' => $sysAdmin->userId]);
$check('the same line signed by SysAdmin', 0, '', $run());
$restored = true;
$restore();

// 3. A workflow box signed across offices by a head.
$capture('workflowsteps', (int) $box->workflowStepId);
$restored = false;
DB::table('workflowsteps')->where('workflowStepId', $box->workflowStepId)
    ->update(['signedBy' => $foreignHead->userId]);
$check('a completed box signed by another office’s head', 1, 'Signatures:', $run());
$restored = true;
$restore();

// 4. The same box signed by SysAdmin.
$capture('workflowsteps', (int) $box->workflowStepId);
$restored = false;
DB::table('workflowsteps')->where('workflowStepId', $box->workflowStepId)
    ->update(['signedBy' => $sysAdmin->userId]);
$check('the same box signed by SysAdmin', 0, '', $run());
$restored = true;
$restore();

foreach ([
    ['clearanceapprovals', 'clearanceApprovalId', (int) $approval->clearanceApprovalId, (int) $approval->approvedBy],
    ['workflowsteps', 'workflowStepId', (int) $box->workflowStepId, (int) $box->signedBy],
] as [$table, $pk, $id, $want]) {
    $now = (int) DB::table($table)->where($pk, $id)->value($table === 'clearanceapprovals' ? 'approvedBy' : 'signedBy');
    $verdicts[] = $now === $want;
    printf("%s  %-58s user %d (was %d)\n", $now === $want ? 'PASS' : 'FAIL',
        "restore returns {$table} {$id} to its signer", $now, $want);
}

$final = $run();
$verdicts[] = $final['status'] === 0;
printf("\n%s  %-58s\n", $final['status'] === 0 ? 'PASS' : 'FAIL', 'the run is green again after the last mutant');

$failed = count(array_filter($verdicts, fn (bool $ok): bool => ! $ok));

echo "\n".($failed === 0
    ? "Both counters refuse the signature the application cannot produce and accept the one it can.\n"
    : "{$failed} verdict(s) failed — neither counter is trustworthy yet.\n");

exit($failed === 0 ? 0 : 1);
