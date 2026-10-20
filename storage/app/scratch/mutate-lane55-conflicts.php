<?php

/**
 * Lane 55 — prove the two new timetable rules are what changed the verdicts.
 *
 * The tests added in this batch pass on the current tree. That alone says nothing: a test that would also pass
 * against the code it is meant to guard is decoration. So the pre-fix controller is written in place from HEAD's
 * bytes and the two named tests are run again — each must go RED, because the block's own hour was never asked
 * and the term was never filtered. The pristine file is held in memory and restored in a shutdown guard, and
 * before either run is believed the two files are checked to actually differ (the lesson from lane 53, where a
 * mutation that silently failed to apply looked exactly like a surviving defect).
 *
 * Usage: php storage/app/scratch/mutate-lane55-conflicts.php
 */

$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$target = $root.'/app/Http/Controllers/Blocking/BlockingController.php';
$headCopy = $root.'/storage/app/scratch/lane55-head-BlockingController.php';

$pristine = (string) file_get_contents($target);
$hash0 = hash('sha256', $pristine);
$before = (string) @file_get_contents($headCopy);

if ($pristine === '') {
    fwrite(STDERR, "REFUSED: cannot read {$target}.\n");
    exit(1);
}
if ($before === '' || $before === $pristine) {
    fwrite(STDERR, "REFUSED: the HEAD export is missing or identical — export it first with\n"
        ."  git show HEAD:app/Http/Controllers/Blocking/BlockingController.php > storage/app/scratch/lane55-head-BlockingController.php\n");
    exit(1);
}

$failures = 0;
$finished = false;
$restore = function () use ($target, $pristine, $hash0): void {
    file_put_contents($target, $pristine);
    if (hash('sha256', (string) file_get_contents($target)) !== $hash0) {
        fwrite(STDERR, "BAD: the restore did not reproduce the original bytes.\n");
    }
};
register_shutdown_function(function () use ($restore, &$finished): void {
    if (! $finished) {
        fwrite(STDERR, "The run ended early — restoring the controller.\n");
        $restore();
    }
});

// Two of these answer a defect and must flip; the third pins a branch that was already honest. Proving a
// guard exists is not the same claim as proving a fix changed something, and the harness that conflated them
// reported a correct expectation as a failure — the room branch passed against HEAD too, because it works.
$fixProven = [
    'test_a_block_is_never_booked_into_two_rooms_at_one_hour',
    'test_a_retired_term_does_not_refuse_this_terms_room',
];
$coverageOnly = [
    'test_the_room_branch_refuses_a_room_taken_by_another_officer',
];
$tests = [...$fixProven, ...$coverageOnly];

// A plain substring filter is what this suite answers to; the class-qualified form collects nothing.
$run = function (string $method) use ($php, $root): array {
    $out = [];
    $code = 0;
    exec('cd /d '.escapeshellarg($root).' && '.escapeshellarg($php).' artisan test --filter='
        .escapeshellarg($method).' 2>&1', $out, $code);
    $log = implode("\n", $out);
    $summary = '';
    foreach (explode("\n", $log) as $line) {
        if (str_contains($line, 'Tests:')) {
            $summary = trim(preg_replace('/\e\[[0-9;]*m/', '', $line));
        }
    }

    return [$code, $log, $summary];
};

echo "Baseline — the current tree must be green on all three before a red swap means anything.\n";
foreach ($tests as $method) {
    [$code, $log, $summary] = $run($method);
    $ok = $code === 0 && str_contains($summary, '1 passed');
    printf("  %s %-58s %s\n", $ok ? 'ok  ' : 'FAIL', $method, $summary === '' ? 'no Tests: line — broken instrument' : $summary);
    if (! $ok) {
        $failures++;
    }
}

echo "\nPre-fix controller from HEAD — the same tests must refuse the old behaviour.\n";
file_put_contents($target, $before);
if ((string) file_get_contents($target) === $pristine) {
    echo "  BAD  the swap left the file byte-identical; nothing was measured\n";
    $failures++;
} else {
    printf("  ..   swapped in HEAD's bytes (%d → %d bytes)\n", strlen($pristine), strlen($before));
    foreach ($tests as $method) {
        [$code, $log, $summary] = $run($method);
        $red = $code !== 0 && str_contains($summary, '1 failed');
        $green = $code === 0 && str_contains($summary, '1 passed');
        $mustFlip = in_array($method, $fixProven, true);

        if ($mustFlip && $red) {
            printf("  ok   %-58s red on the pre-fix code: %s\n", $method, $summary);
        } elseif (! $mustFlip && $green) {
            printf("  ok   %-58s green before the fix too — this branch predates the batch, the test only pins it\n", $method);
        } else {
            printf("  FAIL %-58s expected %s on the pre-fix code, got %s\n",
                $method, $mustFlip ? 'red' : 'green', $summary === '' ? 'no Tests: line' : $summary);
            $failures++;
        }
    }
}
$restore();

echo "\nRestored — the three tests must be green again on the file this batch wrote.\n";
$again = (string) file_get_contents($target);
if (hash('sha256', $again) !== $hash0) {
    echo "  BAD  the tree does not hold the batch's bytes any more\n";
    $failures++;
}
foreach ($tests as $method) {
    [$code, $log, $summary] = $run($method);
    $ok = $code === 0 && str_contains($summary, '1 passed');
    printf("  %s %-58s %s\n", $ok ? 'ok  ' : 'FAIL', $method, $summary === '' ? 'no Tests: line' : $summary);
    if (! $ok) {
        $failures++;
    }
}

$finished = true;

if ($failures > 0) {
    printf("\n%d case(s) did not behave — do not report these two rules as proved.\n", $failures);
    exit(1);
}

echo "\nThe block's hour and the term's scope each changed a verdict; the room branch was already honest.\n";
exit(0);
