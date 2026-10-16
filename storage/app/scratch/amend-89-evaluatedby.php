<?php

/**
 * #89, ruled 2026-10-13: repoint the stored evaluation CREDIT to each row's own college desk, and leave
 * the SIGNATURES alone.
 *
 * Live `ems` carries 30 enrollments; 29 of them name `office4_head` in `evaluatedBy` — CTIC 16, CCJE 10,
 * CBG 3. The owner's ruling is that a college's department receives its own students, so that one account
 * holding the evaluation credit across three colleges describes work the application now refuses. The
 * amendment moves the credit to the desk the row's own course names, resolved BY USERNAME AND COLLEGE —
 * never by id, because a seeder keyed by name hands out whatever the counter is on (AGENTS.md).
 *
 * What this deliberately does NOT touch:
 *   · `workflowsteps.signedBy`. A signature is a claim about who physically signed a paper. Rewriting 20 of
 *     them to make the data tidier is worse than the anomaly it hides; the docx already says a signed box
 *     is corrected by the Registrar with a system administrator, not by an UPDATE.
 *   · the one enrollment whose Evaluation box was closed by `office2_head`. That is printed below as a
 *     finding, not amended.
 *   · the single row crediting `staff8`. It was never part of the question.
 *
 * The audit trail writes itself: `AppServiceProvider.php:44-52` attaches `AuditLogObserver` to every model,
 * so each `save()` lands an `updated` row in the app's own old/new snapshot format with `userId` null —
 * the same shape the 2026-10-13 `dept.*` seeding rows already have.
 *
 * DRY RUN unless --apply. Dump taken first: C:\Users\ADMIN\ems-backups\
 *   ems-pre-89-evaluatedby-amendment-2026-10-13.sql
 */

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';

use App\Models\Enrollments;
use App\Models\Staffusers;

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apply = in_array('--apply', $argv, true);
$expectedRows = 29;
$abort = function (string $why): never {
    fwrite(STDERR, "REFUSE: {$why}\n");
    exit(1);
};

$office = Staffusers::where('username', 'office4_head')->first();
if ($office === null) {
    $abort('office4_head is not on this database — the question this script answers no longer exists here');
}

$rows = Enrollments::with('course')->where('evaluatedBy', $office->userId)->orderBy('enrollmentId')->get();
if ($rows->count() !== $expectedRows) {
    $abort("expected {$expectedRows} enrollments credited to office4_head, found ".$rows->count().' — re-measure before writing');
}

// One desk per college, named rather than numbered.
$desks = Staffusers::where('username', 'like', 'dept.%')->get();
$byUnit = [];
foreach ($desks as $desk) {
    if ($desk->unitId === null) {
        $abort("desk {$desk->username} has no college, so it cannot be given a college's students");
    }
    if (isset($byUnit[$desk->unitId])) {
        $abort('two desks claim college '.$desk->unitId.': '.$byUnit[$desk->unitId]->username.' and '.$desk->username);
    }
    $byUnit[$desk->unitId] = $desk;
}

$plan = [];
$orphans = [];
$noDesk = [];
foreach ($rows as $row) {
    $unitId = $row->course?->unitId;
    if ($unitId === null) {
        $orphans[] = $row->enrollmentId.' ('.($row->course?->courseCode ?? 'no course').')';

        continue;
    }
    if (! isset($byUnit[$unitId])) {
        $noDesk[] = $unitId.' — '.$row->course->courseCode;

        continue;
    }
    $plan[] = [$row, $byUnit[$unitId]];
}

// A row whose course names no college is not the desk's (AGENTS.md), and the ruling gives a college's desk
// its own students — so both of those cases stop the run rather than being guessed at.
if ($orphans !== []) {
    $abort('these rows belong to no college and cannot be repointed: '.implode(', ', $orphans));
}
if ($noDesk !== []) {
    $abort('no dept.* account is seated for these colleges: '.implode(', ', array_unique($noDesk))
        .' — seat the desk first (Admin → User Management, or the demo pipeline that refuses with the fix)');
}
if (count($plan) !== $expectedRows) {
    $abort('the plan covers '.count($plan).' rows, not the '.$expectedRows.' that were counted');
}

printf("%s\n  %s\n\n", 'THE PLAN — evaluation credit moves to the college the row already names',
    $apply ? 'APPLYING' : 'DRY RUN, nothing written');
printf("%-14s %-9s %-52s %s\n", 'enrollment', 'course', 'college', 'credit moves');
foreach ($plan as [$row, $desk]) {
    printf("%-14d %-9s %-52s %s → %s\n",
        $row->enrollmentId, $row->course->courseCode, $row->course->unit?->unitName ?? '?',
        $office->username, $desk->username);
}

$tally = [];
foreach ($plan as [, $desk]) {
    $tally[$desk->username] = ($tally[$desk->username] ?? 0) + 1;
}
printf("\nnewspaper per desk: %s\n", json_encode($tally));

if (! $apply) {
    echo "\nDRY RUN — pass --apply to write (dump already taken).\n";
    exit(0);
}

$written = 0;
foreach ($plan as [$row, $desk]) {
    // Re-read the row's own column before saving: if anything moved it while this plan was on screen, the
    // guard fails the run rather than overwriting someone else's decision.
    $stillCredits = Enrollments::where('enrollmentId', $row->enrollmentId)->value('evaluatedBy');
    if ((int) $stillCredits !== (int) $office->userId) {
        $abort("enrollment {$row->enrollmentId} no longer credits office4_head — stopping, {$written} rows were written");
    }
    $model = Enrollments::findOrFail($row->enrollmentId);
    $model->evaluatedBy = $desk->userId;
    $model->save();
    $written++;
}

printf("written: %d rows\n", $written);

// Verify by reading the database back, not by trusting the loop. (The first version of this block joined
// `selectRaw('COUNT(*) c')` into `->sum('c')`, which Laravel rewrote into `sum(c)` over a join and threw
// 1054 *after* the writes had landed — the amendment was fine, the checker was not. Counting with a plain
// query is both shorter and true.)
$left = Enrollments::where('evaluatedBy', $office->userId)->count();
$perDesk = Enrollments::whereHas('evaluatedByUser', fn ($q) => $q->where('username', 'like', 'dept.%'))->count();
$mismatched = Enrollments::with('course')
    ->whereHas('evaluatedByUser', fn ($q) => $q->where('username', 'like', 'dept.%'))
    ->get()
    ->filter(function ($e) {
        $desk = $e->evaluatedByUser;
        if ($desk === null || $e->course === null) {
            return true;
        }

        return $e->course->unitId === null || (int) $desk->unitId !== (int) $e->course->unitId;
    });

printf("still crediting office4_head: %d (must be 0)\n", $left);
printf("now crediting a dept.* desk:  %d (must be %d)\n", $perDesk, $expectedRows);
printf("rows whose desk is the WRONG college: %d (must be 0)\n", $mismatched->count());

if ($left !== 0 || (int) $perDesk !== $expectedRows || $mismatched->count() !== 0) {
    $abort('post-write verification failed — restore from the dump and re-run');
}

echo "\nVERIFIED. The signatures were not touched: workflowsteps.signedBy still names whoever closed each box.\n";
echo "Flagged, not amended: one enrollment's Department Evaluation box was closed by office2_head.\n";
