<?php

/**
 * Mutants for P-17's tests (tests/Feature/Print/ClearanceSlipTermEnrollmentTest.php). Each case plants
 * one defect in the working tree, runs the named test, and requires it to go red with the message that
 * defect deserves; the bytes are restored in a `finally` because a half-planted model makes every later
 * verdict a lie.
 *
 * P-17 was ruled 2026-10-23, so the defects planted here are the ones the ruling forbids: the newest-
 * by-id fallback re-appearing, the exact-term lookup loosened, a print path deriving its own answer, a
 * desk given a way to erase a clearance, the refusal that protects an answered clearance, and the
 * ruling marker renamed. The case that planted no defect reports BAD, never a pass.
 *
 *   php storage/app/scratch/mutate-p17-term-enrollment-tests.php
 */

$root = dirname(__DIR__, 3);
chdir($root);

$php = escapeshellarg(getenv('PHP') ?: 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe');
$file = 'tests/Feature/Print/ClearanceSlipTermEnrollmentTest.php';
$model = $root.'/app/Models/Studentclearances.php';
$blade = $root.'/resources/views/prints/clearance-slip.blade.php';
$controller = $root.'/app/Http/Controllers/Clearance/ClearanceController.php';

$run = function (?string $method) use ($php, $file): array {
    $cmd = $php.' artisan test '.escapeshellarg($file);
    if ($method !== null) {
        $cmd .= ' --filter '.escapeshellarg($method);
    }
    $cmd .= ' 2>&1';
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

$guard = 'test_every_print_path_asks_the_one_method_and_nothing_erases_a_clearance';

$cases = [
    [
        'the newest-by-id fallback re-appears',
        'test_a_slip_raised_after_its_term_closed_names_no_enrollment',
        $model,
        "        return \$this->student->enrollments\n            ->first(fn (Enrollments \$enrollment) => \$enrollment->termId === \$termId);",
        "        return \$this->student->enrollments\n            ->first(fn (Enrollments \$enrollment) => \$enrollment->termId === \$termId)\n            ?? \$this->student->enrollments->sortByDesc('enrollmentId')->first();",
        'the branch P-17 ruled against is back: a slip raised after its term closed borrows a later enrollment',
    ],
    [
        'the exact-term lookup loosened',
        'test_the_slip_names_the_enrollment_of_its_own_periods_term',
        $model,
        '->first(fn (Enrollments $enrollment) => $enrollment->termId === $termId)',
        "->sortByDesc('enrollmentId')->first()",
        'P-10’s own claim broken quietly: every slip names the newest enrollment the student ever had',
    ],
    [
        'the blade derives its own answer',
        $guard,
        $blade,
        '$termEnrollment = $clearance->termEnrollment();',
        '$termEnrollment = $clearance->student?->enrollments?->sortByDesc(\'enrolledDate\')->first();',
        'the template re-derives the enrollment inline, so the pinned method no longer describes the paper',
    ],
    [
        'a statement that deletes a clearance, broken across lines',
        $guard,
        $controller,
        '$existing->update([\'overallStatus\' => ClearanceOverallStatus::Pending]);',
        "Studentclearances::where('studentClearanceId', \$existing->studentClearanceId)\n                    ->delete();",
        'the scan only reads one-line statements: a multi-line delete erases the record P-17 says the school keeps',
    ],
    [
        'a variable that is deleted',
        $guard,
        $controller,
        "return back()->with('success', 'Clearance slip generated.');",
        "\$clearance->delete();\n\n        return back()->with('success', 'Clearance slip generated.');",
        'the scan keys on the class name only: a delete through a loaded model erases the record just as well',
    ],
    [
        'the refusal that protects an answered clearance widened',
        'test_a_completed_clearance_survives_the_period_that_follows_it',
        $controller,
        '$existing->overallStatus !== ClearanceOverallStatus::Incomplete',
        '$existing->overallStatus === ClearanceOverallStatus::Pending',
        'generateSlip now resets a clearance a desk has already approved, so the verdict is overwritten',
    ],
    [
        'the ruling marker renamed',
        $guard,
        $model,
        '// Ruled 2026-10-23 (P-17): a clearance is issued when the semester ends',
        '// NOTE: a clearance is issued when the semester ends',
        'the code stops stating where its answer came from, which is how a ruled branch becomes folklore',
    ],
];

try {
    $control = $run(null);
    $say($control['verdict'] === 'GREEN', 'control: the class is green before any defect — '.$control['line']);
    if ($control['verdict'] !== 'GREEN') {
        echo "REFUSED: a mutant run on a red tree proves nothing about the mutants.\n";
        exit(1);
    }

    foreach ($cases as [$label, $method, $path, $from, $to, $why]) {
        printf("\ncase: %s\n", $label);
        if (! $plant($path, $from, $to)) {
            $say(false, $label.': the needle did not match exactly once, so this case planted no defect — '.$why);

            continue;
        }
        $r = $run($method);
        $unplant();
        $say($r['verdict'] === 'RED', $label.': expected RED, got '.$r['verdict'].' — '.$why.' ('.$r['line'].')');
        if ($r['verdict'] === 'BROKEN') {
            $say(false, $label.': the instrument itself did not answer, which is not a red');
        }
    }
} finally {
    $unplant();
}

foreach ([
    [$model, "return \$this->student->enrollments\n            ->first(fn (Enrollments \$enrollment) => \$enrollment->termId === \$termId);"],
    [$model, '// Ruled 2026-10-23 (P-17): a clearance is issued when the semester ends'],
    [$blade, '$termEnrollment = $clearance->termEnrollment();'],
    [$controller, "\$existing->update(['overallStatus' => ClearanceOverallStatus::Pending]);"],
    [$controller, '$existing->overallStatus !== ClearanceOverallStatus::Incomplete'],
] as [$path, $needle]) {
    $say(str_contains((string) file_get_contents($path), $needle), basename($path).' is back to the tree’s own bytes ('.substr($needle, 0, 46).'…)');
}

if ($fail > 0) {
    printf("\n%d case(s) misbehaved.\n", $fail);
    exit(1);
}

echo "\nAll P-17 cases behaved: green on the tree, red on the defect, each naming the contract it broke.\n";
