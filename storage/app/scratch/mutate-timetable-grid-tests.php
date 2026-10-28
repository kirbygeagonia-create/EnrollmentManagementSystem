<?php

/**
 * Prove that TimetableGridSpanTest can fail — and tell a dead mutant from a killed defect.
 *
 * §7.9's grid span is pinned by reading the module's source, which is the only surface available
 * where there is no JavaScript test suite. A test that reads source text is easy to write so that it
 * can never go red: the needle drifts, the mutation matches nothing, and the run reports the checker's
 * own clean output as a pass (lane 44's mutant that sleeps). So every leg here requires the named case
 * to answer `1 failed`, distinguishes BAD (the needle did not land) from SURVIVED (the defect was
 * planted and the test still passed), and restores the file by hash in a `finally` — unconditionally,
 * because a guard that makes the restore a no-op after the first call leaves every later mutant in the
 * tree (lane 48's lesson).
 *
 * Usage: php storage/app/scratch/mutate-timetable-grid-tests.php
 */

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$root = dirname(__DIR__, 3);
$source = 'resources/js/Pages/Blocking/Show.jsx';
$path = $root.'/'.str_replace('/', DIRECTORY_SEPARATOR, $source);
$test = 'tests/Feature/Blocking/TimetableGridSpanTest.php';

$original = file_get_contents($path);

if ($original === false) {
    fwrite(STDERR, "REFUSED: cannot read {$path}.\n");

    exit(1);
}

$hash = sha1($original);

/** Each leg: [label, needle, replacement, the test method that must go red]. */
$legs = [
    ['the span stops on the hour', 'const END_MINUTE = 30;', 'const END_MINUTE = 0;', 'the_grid_spans_half_past_nine_at_night_not_nine_o_clock'],
    ['Sunday leaves the week', "'Friday', 'Saturday', 'Sunday'];", "'Friday'];", 'the_week_runs_monday_through_sunday_as_the_instruction_names_it'],
    ['the interval doubles', 'const SLOT_MINUTES = 30;', 'const SLOT_MINUTES = 60;', 'the_interval_is_thirty_minutes_and_the_rows_are_derived_from_it'],
    ['the row count is typed, not derived', 'const SLOTS = (END_HOUR * 60 + END_MINUTE - START_HOUR * 60) / SLOT_MINUTES;', 'const SLOTS = 28;', 'the_interval_is_thirty_minutes_and_the_rows_are_derived_from_it'],
    ['the heading names a span the grid does not draw', 'Weekly Timetable (7:00 AM – 9:30 PM, 30-minute slots)', 'Weekly Timetable (7:00 AM – 9:00 PM, 30-minute slots)', 'the_screen_says_the_span_it_draws'],
];

$fails = 0;

$run = static function (string $filter) use ($php, $root, $test): string {
    // An empty filter must not be passed at all: `--filter=''` answers "No tests found", and a
    // control leg that reads that as a green run is the harness's own age, not a result.
    $out = (string) shell_exec('cd '.escapeshellarg($root).' && '
        .$php.' artisan test '.escapeshellarg(str_replace('/', DIRECTORY_SEPARATOR, $test))
        .($filter === '' ? '' : ' --filter='.escapeshellarg($filter)).' 2>&1');

    // Pest writes ANSI colour codes into captured output, which breaks a tally regex and any needle
    // spanning a styled word (lane 73b's lesson): strip them, or this harness's verdict depends on
    // whether the caller's shell had a TERM.
    return preg_replace('/\e\[[0-9;]*m/', '', $out) ?? $out;
};

$check = static function (string $name, bool $ok, string $detail = '') use (&$fails): void {
    printf("  %s %s%s\n", $ok ? 'PASS' : 'BAD ', $name, $detail === '' ? '' : ' — '.$detail);
    if (! $ok) {
        $fails++;
    }
};

try {
    echo "control (unmutated):\n";
    $out = $run('');
    if (! preg_match('/Tests:\s+\d+ passed \((\d+) assertions\)/', $out, $m)) {
        echo mb_substr($out, -1500);
        $check('the four grid cases run green before anything is planted', false, 'the run printed no pass tally');

        exit(1);
    }
    $check('the four grid cases run green before anything is planted', true, $m[1].' assertions');

    foreach ($legs as [$label, $needle, $replacement, $method]) {
        echo "\nleg: {$label}\n";

        if (! str_contains($original, $needle)) {
            $check($label, false, 'the needle matches no byte of the current file — this leg is the harness\'s age, not a rule that does not fire');

            continue;
        }
        if ($needle === $replacement) {
            $check($label, false, 'needle and replacement are the same text');

            continue;
        }

        $mutated = str_replace($needle, $replacement, $original);
        if (sha1($mutated) === $hash) {
            $check($label, false, 'the mutation left the file byte-identical');

            continue;
        }

        file_put_contents($path, $mutated);

        if (sha1((string) file_get_contents($path)) !== sha1($mutated)) {
            $check($label, false, 'the tree does not hold the bytes that were written');

            continue;
        }

        $out = $run($method);
        $killed = (bool) preg_match('/Tests:\s+1 failed/', $out);
        $compiled = ! str_contains($out, 'syntax error') && ! str_contains($out, 'Fatal error');

        if (! $compiled) {
            $check($label, false, 'the mutated file does not compile — a mutant that cannot run is not a killed defect');
        } else {
            $check($label, $killed, $killed ? 'the named case went red on the defect' : 'the test passed over the planted defect (SURVIVED)');
        }
    }

    echo "\ncontrol again:\n";
    // Each leg above writes from the pristine bytes, so the tree still holds the last mutation at
    // this point: put the real file back before measuring the second control, or this leg grades
    // the harness's own residue as a result (lane 73b's ordering bug, met again).
    file_put_contents($path, $original);
    $out = $run('');
    $check('the grid cases are green again after the restores', (bool) preg_match('/Tests:\s+4 passed/', $out));
} finally {
    file_put_contents($path, $original);
    $back = sha1((string) file_get_contents($path));
    printf("\nrestored %s: %s\n", $back === $hash ? 'byte-identical' : 'CHANGED — restore from git', $source);
}

printf("\n%s\n", $fails === 0 ? 'OK — every leg can fail, and the control is green on both sides.' : "{$fails} leg(s) bad.");

exit($fails === 0 ? 0 : 1);
