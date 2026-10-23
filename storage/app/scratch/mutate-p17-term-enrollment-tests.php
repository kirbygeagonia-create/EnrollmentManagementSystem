<?php

/**
 * Mutants for P-17's tests. Each case plants one defect in the working tree, runs the named test, and
 * requires it to go red with the message that defect deserves; the bytes are restored in a `finally`
 * because a half-planted model makes every later verdict a lie.
 *
 * The run refuses rather than reporting a pass when: the control is not green, a needle matches
 * nothing (the defect was not planted at all), the file bytes did not change, or the suite prints no
 * `Tests:` line (a broken instrument, not a result).
 *
 *   php storage/app/scratch/mutate-p17-term-enrollment-tests.php
 */

$root = dirname(__DIR__, 3);
chdir($root);

$php = escapeshellarg(getenv('PHP') ?: 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe');
$file = 'tests/Feature/Print/ClearanceSlipTermEnrollmentTest.php';
$model = $root.'/app/Models/Studentclearances.php';
$blade = $root.'/resources/views/prints/clearance-slip.blade.php';

$run = function (string $method) use ($php, $file): array {
    $cmd = $php.' artisan test '.escapeshellarg($file).' --filter '.escapeshellarg($method).' 2>&1';
    // Pest colours its summary, and the escape sequences sit between "Tests:" and the figure — a
    // reader that does not strip them reads a green run as a broken instrument.
    $out = (string) shell_exec($cmd);
    $out = preg_replace('/\e\[[0-9;]*m/', '', $out);
    if (! preg_match('/Tests:\s+([0-9]+) (passed|failed|incomplete)/', $out, $m)) {
        return ['verdict' => 'BROKEN', 'line' => 'the run printed no Tests: line — '.substr(trim($out), -200)];
    }

    return ['verdict' => $m[2] === 'passed' ? 'GREEN' : 'RED', 'line' => trim($m[0])];
};

$restore = [];
$plant = function (string $path, string $from, string $to) use (&$restore): bool {
    $bytes = (string) file_get_contents($path);
    $restore[] = [$path, $bytes];
    if (substr_count($bytes, $from) !== 1) {
        return false;
    }
    file_put_contents($path, str_replace($from, $to, $bytes));

    return (string) file_get_contents($path) !== $bytes;
};
$unplant = function () use (&$restore): void {
    foreach (array_reverse($restore) as [$path, $bytes]) {
        file_put_contents($path, $bytes);
    }
    $restore = [];
};

$fail = 0;
$say = function (bool $ok, string $what) use (&$fail): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fail++;
    }
};

try {
    $control = $run('test_every_print_path_asks_the_one_method');
    $say($control['verdict'] === 'GREEN', 'control: the class is green before any defect — '.$control['line']);
    if ($control['verdict'] !== 'GREEN') {
        echo "REFUSED: a mutant run on a red tree proves nothing about the mutants.\n";
        exit(1);
    }

    $cases = [
        [
            'what the paper loses',
            'test_a_slip_raised_after_its_term_closed_names_the_newest_enrollment_by_id',
            $model,
            'return $exact ?? $enrollments->sortByDesc(\'enrollmentId\')->first();',
            'return $exact;',
            'the fallback removed: a slip raised after its term closed now prints N/A',
        ],
        [
            'the term filter dropped',
            'test_the_slip_names_the_enrollment_of_its_own_periods_term',
            $model,
            '$enrollments->first(fn (Enrollments $enrollment) => $enrollment->termId === $termId)',
            '$enrollments->sortByDesc(\'enrollmentId\')->first()',
            'the exact-term lookup replaced by newest-ever: P-10’s own claim broken quietly',
        ],
        [
            'the blade derives its own answer',
            'test_every_print_path_asks_the_one_method',
            $blade,
            '$termEnrollment = $clearance->termEnrollment();',
            '$termEnrollment = $clearance->student?->enrollments?->sortByDesc(\'enrolledDate\')->first();',
            'the template re-derives the enrollment inline, so the pinned method no longer describes the paper',
        ],
        [
            'the marker renamed',
            'test_every_print_path_asks_the_one_method',
            $model,
            '// PROVISIONAL: a slip raised after its term closed',
            '// NOTE: a slip raised after its term closed',
            'the open question loses its marker while the fallback stays, which is how a provisional branch becomes permanent',
        ],
    ];

    foreach ($cases as [$label, $method, $path, $from, $to, $why]) {
        if (! $plant($path, $from, $to)) {
            $say(false, $label.': the needle matched nothing, so this case planted no defect — '.$why);

            continue;
        }
        $r = $run($method);
        $unplant();
        $say($r['verdict'] === 'RED', $label.': expected RED, got '.$r['verdict'].' — '.$why.' ('.$r['line'].')');
    }
} finally {
    $unplant();
}

$bytes = (string) file_get_contents($model).(string) file_get_contents($blade);
$say(str_contains($bytes, 'return $exact ?? $enrollments->sortByDesc(\'enrollmentId\')->first();'),
    'the model is back to the tree’s own bytes');
$say(str_contains($bytes, '// PROVISIONAL: a slip raised after its term closed'),
    'and the provisional marker is in place again');

if ($fail > 0) {
    printf("\n%d case(s) misbehaved.\n", $fail);
    exit(1);
}

echo "\nAll P-17 cases behaved: green on the tree, red on the defect, each naming the contract it broke.\n";
