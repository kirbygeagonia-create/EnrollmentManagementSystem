<?php

/**
 * G-44 harness: prove the bell's college boundary by planting the defect, not by deleting the fix.
 *
 * Each leg writes one wrong version of `WorkflowInbox::actors()` (or one call site that forgets the record) into
 * the real file, runs `NoticeCollegeBoundaryTest`, and requires the case that owns that promise to report
 * `1 failed`. A mutant that compiles but is not reached is a sleeping leg, so the needle for every plant must
 * match exactly once before anything is written, and both touched files are restored by hash in a `finally`
 * — a half-restored file makes every later verdict a lie.
 *
 * Run this on its own. Never launch it beside a suite run: its legs execute `artisan test`, and two writers in
 * one tree fake each other's readings.
 *
 * Usage: php storage/app/scratch/mutate-g44-notice-college-boundary.php [--only=<leg>]
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$inbox = 'app/Support/WorkflowInbox.php';
$listener = 'app/Listeners/SendWorkflowNotification.php';
$statusListener = 'app/Listeners/SendEnrollmentNotification.php';
$class = 'NoticeCollegeBoundaryTest';

$only = null;
for ($i = 1; $i < count($argv); $i++) {
    if (! preg_match('/^--only=(.+)$/', $argv[$i], $m)) {
        fwrite(STDERR, "REFUSED: unreadable argument {$argv[$i]} — this harness reads only --only=\n");
        exit(1);
    }
    $only = $m[1];
}

$strip = static fn (string $s): string => (string) preg_replace('/\e\[[0-9;]*m/', '', $s);

$runClass = static function () use ($php, $class, $strip): array {
    $out = $strip((string) shell_exec($php.' artisan test --filter='.$class.' 2>&1'));
    if (! preg_match('/Tests:\s+([^\r\n]*)/', $out, $m)) {
        return ['ran' => false, 'tally' => '', 'out' => $out];
    }

    return ['ran' => true, 'tally' => trim($m[1]), 'out' => $out];
};

$snapshot = [
    $inbox => (string) file_get_contents($root.'/'.$inbox),
    $listener => (string) file_get_contents($root.'/'.$listener),
    $statusListener => (string) file_get_contents($root.'/'.$statusListener),
];
$hashes = [];
foreach ($snapshot as $rel => $bytes) {
    $hashes[$rel] = sha1($bytes);
}

$restore = static function () use ($root, $snapshot, $hashes, $inbox, $listener): void {
    foreach ($snapshot as $rel => $bytes) {
        if (file_put_contents($root.'/'.$rel, $bytes) === false) {
            fwrite(STDERR, "RESTORE FAILED for {$rel} — the tree is left mutated, fix it by hand.\n");
        }
    }
    foreach ($hashes as $rel => $h) {
        if (sha1((string) file_get_contents($root.'/'.$rel)) !== $h) {
            fwrite(STDERR, "RESTORE VERIFY FAILED for {$rel}\n");
        }
    }
};

register_shutdown_function($restore);

/** @var array<int, array{name:string, file:string, from:string, to:string, expect:string}> */
$legs = [
    [
        'name' => 'boundary-not-asked',
        'file' => $inbox,
        'from' => "            ->filter(static fn (Staffusers \$user) => CollegeScope::holds(\$user, \$courseUnitId))",
        'to' => '            ->filter(static fn (Staffusers $user) => true)',
        'expect' => 'the bell reaches the bounded desk',
    ],
    [
        'name' => 'boundary-inverted',
        'file' => $inbox,
        'from' => "CollegeScope::holds(\$user, \$courseUnitId)",
        'to' => '! CollegeScope::holds($user, $courseUnitId)',
        'expect' => 'the bell reaches the bounded desk',
    ],
    [
        'name' => 'unbounded-excluded',
        'file' => $inbox,
        'from' => "            ->where('status', 'active')
            ->get();",
        'to' => "            ->where('status', 'active')
            ->whereNotNull('unitId')
            ->get();",
        'expect' => 'a desk bounded by nothing',
    ],
    [
        'name' => 'no-record-narrows',
        'file' => $inbox,
        'from' => '            return $accounts->pluck(\'userId\')->map(fn ($id) => (int) $id)->values()->all();',
        'to' => '            return [];',
        'expect' => 'a notice that names no record',
    ],
    [
        'name' => 'status-filter-dropped',
        'file' => $inbox,
        'from' => "            ->where('status', 'active')
            ->get();",
        'to' => "            ->get();",
        'expect' => 'an inactive account in the right college',
    ],
    [
        'name' => 'status-call-site-forgets-the-record',
        'file' => $statusListener,
        'from' => 'WorkflowInbox::actors(WorkflowInbox::nextOffice($workflow, $event->toStatus), $enrollment)',
        'to' => 'WorkflowInbox::actors(WorkflowInbox::nextOffice($workflow, $event->toStatus))',
        'expect' => 'a real transition rings the owning college',
    ],
    [
        'name' => 'signature-call-site-forgets-the-record',
        'file' => $listener,
        'from' => 'WorkflowInbox::actors($next?->officeId, $enrollment),',
        'to' => 'WorkflowInbox::actors($next?->officeId),',
        'expect' => 'a box signature rings the owning college',
    ],
];

$bad = 0;

$control = $runClass();
// The expected count is read off the control measured moments ago, not typed here: a frozen "7 passed" is a
// leg that goes red the day the class gains a case, which reads as a broken tool rather than a true reading.
$controlCount = preg_match('/^(\d+)\s+passed/', $control['tally'], $m) ? $m[1] : null;
if (! $control['ran'] || $controlCount === null) {
    fwrite(STDERR, "REFUSED: the control is not green (tally: ".($control['tally'] ?: 'no Tests: line').
        ") — a harness whose control fails proves nothing about its mutants.\n");
    exit(1);
}
echo "control-green              ok ({$control['tally']})\n";

foreach ($legs as $leg) {
    if ($only !== null && $only !== $leg['name']) {
        continue;
    }

    $bytes = $snapshot[$leg['file']];
    $hits = substr_count($bytes, $leg['from']);
    if ($hits !== 1) {
        fwrite(STDERR, "BAD [{$leg['name']}] its anchor occurs {$hits} time(s) in {$leg['file']} — the plant cannot land uniquely, so this leg is the harness's age, not a surviving defect.\n");
        $bad++;

        continue;
    }
    $mutated = str_replace($leg['from'], $leg['to'], $bytes);
    if ($mutated === $bytes) {
        fwrite(STDERR, "BAD [{$leg['name']}] the plant changes no bytes.\n");
        $bad++;

        continue;
    }
    if (file_put_contents($root.'/'.$leg['file'], $mutated) === false) {
        fwrite(STDERR, "BAD [{$leg['name']}] the write failed.\n");
        $bad++;

        continue;
    }

    $res = $runClass();
    $restore();
    clearstatcache();

    if (! $res['ran']) {
        fwrite(STDERR, "BAD [{$leg['name']}] the mutant run printed no Tests: line — it executed nothing, which is not a kill.\n");
        $bad++;

        continue;
    }
    $failedOnCase = strpos($res['out'], 'FAILED') !== false
        && strpos($res['out'], $leg['expect']) !== false;

    if (! $failedOnCase) {
        fwrite(STDERR, "SURVIVED [{$leg['name']}] the class answered ({$res['tally']}) and the case that owns the promise — '{$leg['expect']}' — did not go red.\n");
        $bad++;

        continue;
    }

    printf("%-40s killed (%s)\n", $leg['name'], $res['tally']);
}

$after = $runClass();
$afterCount = preg_match('/^(\d+)\s+passed/', trim($after['tally']), $m) ? $m[1] : null;
if (! $after['ran'] || $afterCount !== $controlCount || strpos($after['tally'], 'failed') !== false) {
    fwrite(STDERR, "REFUSED: the class is not green after the restores (tally: ".($after['tally'] ?: 'none').
        ", control was {$controlCount} passed) — a mutant is still in the tree.\n");
    exit(1);
}
echo 'control-green-after        ok ('.$after['tally'].")\n";

printf("\n%d leg(s) run, %d bad\n", count($legs), $bad);
if ($bad > 0) {
    exit(1);
}
echo 'ALL KILLED'."\n";
