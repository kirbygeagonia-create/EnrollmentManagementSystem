<?php

/**
 * Lane 36, part 1: retire the seven superseded demo enrollments — through the application's own door.
 *
 * The owner ruled on 2026-10-15, after the review found these seven are not "older-term school history" but
 * the EARLIER enrollment of students who each already hold a later one (DEMO-2026-004 is 654 in term 2, 55 in
 * term 10 and 262 in term 11). Two ways to act on that: delete the rows, or drop them the way the Registrar
 * drops anything. Deleting is refused here because the application already has an audited retirement —
 * `RegistrarController::drop()` transitions through the state machine (which writes the history row and the
 * remark), releases every proposed/confirmed subject so the seat is free, and stores the reason on the
 * record. Using it means the database keeps a true account of what happened and when; the rows go to
 * `dropped`, they do not vanish.
 *
 * What is deliberately NOT touched: `workflowsteps.signedBy` and `signedDate` on these enrollments' boxes.
 * Those signatures are the record of who closed each box, and rewriting one destroys the only evidence of the
 * act. The hashes of every box on the seven are compared before and after, and any change refuses the run.
 *
 * Guards, all checked BEFORE the first write:
 *   - a verified dump must exist (the caller takes it; this script refuses without it);
 *   - each enrollment must currently be `enrolled` (the state machine's own precondition);
 *   - each enrollment's STUDENT must hold a LATER enrollment in a higher term — "superseded" has to be a fact
 *     about this row, not an assumption carried over from a query run earlier in the day;
 *   - the actor must satisfy RegistrarPolicy::drop (office 1 + enrollment.drop), so the retirement is done by
 *     a role that could have done it at the desk;
 *   - the id list is a literal, and any id that is not in this run's expected set aborts.
 *
 * usage: php storage/app/scratch/retire-superseded-enrollments.php <path-to-dump.sql> [--apply]
 */

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\OfficeId;
use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Services\EnrollmentStateMachine;
use Illuminate\Support\Facades\DB;

$ids = [595, 597, 654, 655, 656, 657, 658];
$dumpArg = $argv[1] ?? '';
$apply = in_array('--apply', $argv, true);
$reason = 'Superseded demo enrollment: the same student holds a later term record, and this copy was left by '
    .'the move of the demo dataset. Ruled on 2026-10-15 after the record-state review found the two notes were '
    .'counting the same seven rows.';

$fails = 0;
$fail = function (string $why) use (&$fails): void {
    fwrite(STDERR, "REFUSE: {$why}\n");
    $fails++;
};

if (strlen($reason) < 10 || strlen($reason) > 500) {
    // The same floor RegistrarController::drop() validates at (min:10|max:500). The reason is the only place
    // this act is explained, so a run that would write a row the desk itself would refuse is not a run.
    $fail('reason too short or too long for the desk validation it stands in for (10..500)');
}

if ($dumpArg === '' || ! is_file($dumpArg) || filesize($dumpArg) < 100_000) {
    $fail('no verified dump given (pass the path to a mysqldump of ems taken for this run)');
}
if (! str_contains(file_get_contents($dumpArg), 'CREATE TABLE `enrollments`')) {
    $fail('the dump given does not contain the enrollments table it would have to restore');
}

$actor = Staffusers::query()
    ->where('officeId', OfficeId::Registrar->value)
    ->get()
    ->first(fn ($u) => $u->hasPermissionTo('enrollment.drop'));
if ($actor === null) {
    $fail('no live account holds enrollment.drop in the Registrar office — the desk action itself is unavailable, so this is not a retirement the application could perform');
}

$plan = [];
foreach ($ids as $id) {
    $e = Enrollments::find($id);
    if ($e === null) {
        $fail("enrollment {$id} not found — the expected set changed since the review");

        continue;
    }
    if ($e->enrollmentStatus !== EnrollmentStatus::Enrolled) {
        $fail("enrollment {$id} is {$e->enrollmentStatus->value}, not enrolled; the state machine will not drop it");

        continue;
    }
    $later = DB::table('enrollments')
        ->where('studentId', $e->studentId)
        ->where('termId', '>', $e->termId)
        ->where('enrollmentId', '!=', $e->enrollmentId)
        ->orderByDesc('termId')
        ->pluck('enrollmentId', 'termId');
    if ($later->isEmpty()) {
        $fail("enrollment {$id} has NO later enrollment for its student — it is not superseded, so nothing here justifies retiring it");

        continue;
    }

    $boxes = DB::table('workflowsteps as ws')
        ->join('enrollmentworkflow as ew', 'ew.workflowId', '=', 'ws.workflowId')
        ->where('ew.enrollmentId', $id)
        ->get(['ws.workflowStepId', 'ws.signedBy', 'ws.signedDate', 'ws.stepStatus']);
    $plan[] = [
        'enrollment' => $e,
        'boxes' => $boxes,
        'fingerprint' => md5(json_encode($boxes->map(fn ($b) => $b->signedBy.'|'.$b->signedDate.'|'.$b->stepStatus)->all())),
        'later' => $later,
        'subjects' => DB::table('enrolledsubjects')->where('enrollmentId', $id)
            ->whereIn('status', [EnrolledSubjectStatus::Proposed->value, EnrolledSubjectStatus::Confirmed->value])->count(),
        'historyBefore' => DB::table('enrollmentstatushistory')->where('enrollmentId', $id)->count(),
    ];
}

printf("actor: %s (office %s, holds enrollment.drop)\ndump: %s (%s KB)\n%d of %d enrollments pass every guard\n\n",
    $actor?->username, (string) $actor?->officeId, basename($dumpArg), (int) (filesize($dumpArg ?: '/dev/null') / 1024),
    count($plan), count($ids));

foreach ($plan as $p) {
    $e = $p['enrollment'];
    printf("  %-6s student %-14s term %-3s -> later enrollment(s) %s | %d subject(s) to release, %d box(es), %d history row(s) now\n",
        $e->enrollmentId, $e->student?->schoolIdNumber ?? '#'.$e->studentId, $e->termId,
        $p['later']->keys()->implode(','), $p['subjects'], $p['boxes']->count(), $p['historyBefore']);
}

if ($fails > 0) {
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN — pass --apply to drop these through the state machine\n";
    exit(0);
}

$manifest = $root.'/storage/app/scratch/manifest-retire-superseded-'.now()->format('Y-m-d-His').'.json';
file_put_contents($manifest, json_encode(array_map(fn ($p) => [
    'enrollmentId' => $p['enrollment']->enrollmentId,
    'studentId' => $p['enrollment']->studentId,
    'termId' => $p['enrollment']->termId,
    'statusBefore' => $p['enrollment']->enrollmentStatus->value,
    'laterEnrollments' => $p['later']->all(),
    'boxes' => $p['boxes']->map(fn ($b) => (array) $b)->all(),
    'subjectsReleased' => $p['subjects'],
    'reason' => $reason,
], $plan), JSON_PRETTY_PRINT));
echo "\nmanifest written: ".basename($manifest)."\n";

$sm = app(EnrollmentStateMachine::class);
foreach ($plan as $p) {
    /** @var Enrollments $e */
    $e = $p['enrollment'];
    $sm->transition($e, EnrollmentStatus::Dropped, $actor, 'Dropped by the Registrar: '.$reason);
    DB::table('enrolledsubjects')->where('enrollmentId', $e->enrollmentId)
        ->whereIn('status', [EnrolledSubjectStatus::Proposed->value, EnrolledSubjectStatus::Confirmed->value])
        ->update(['status' => EnrolledSubjectStatus::Dropped->value]);
    $e->update(['dropReason' => $reason]);
    echo "dropped enrollment {$e->enrollmentId} (student {$e->studentId}, term {$e->termId})\n";
}

echo "\n=== post-conditions ===\n";
foreach ($plan as $p) {
    $id = $p['enrollment']->enrollmentId;
    $now = Enrollments::find($id);
    $boxes = DB::table('workflowsteps as ws')
        ->join('enrollmentworkflow as ew', 'ew.workflowId', '=', 'ws.workflowId')
        ->where('ew.enrollmentId', $id)
        ->get(['ws.workflowStepId', 'ws.signedBy', 'ws.signedDate', 'ws.stepStatus']);
    $fp = md5(json_encode($boxes->map(fn ($b) => $b->signedBy.'|'.$b->signedDate.'|'.$b->stepStatus)->all()));
    $hist = DB::table('enrollmentstatushistory')->where('enrollmentId', $id)->count();
    $ok = $now->enrollmentStatus === EnrollmentStatus::Dropped
        && $fp === $p['fingerprint']
        && $hist === $p['historyBefore'] + 1;
    printf("  %-6s status=%-8s signatures %s  history %d -> %d  %s\n", $id, $now->enrollmentStatus->value,
        $fp === $p['fingerprint'] ? 'UNCHANGED' : 'CHANGED!!', $p['historyBefore'], $hist, $ok ? 'ok' : 'FAILED');
    $ok || $fail("post-condition failed on enrollment {$id}");
}

printf("\n%s\n", $fails === 0
    ? sprintf('RETIRED %d enrollments through the state machine; no signature was rewritten.', count($plan))
    : sprintf('%d problem(s) — read them before doing anything else.', $fails));
exit($fails === 0 ? 0 : 1);
