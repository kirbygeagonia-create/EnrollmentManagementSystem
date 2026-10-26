<?php

/**
 * Prove the G-1 grade-history tests can fail.
 *
 * The panel (§7.4 of the development prompt) adds one server-side read and one desk surface.
 * A green run of RegistrarGradeHistoryTest says nothing on its own: a test that would also pass
 * against the code it is meant to guard is decoration. So each case plants exactly one defect in
 * the working file, runs ONLY the case that names it, and requires that case to report one failed
 * test — then restores the bytes by hash before the next measurement.
 *
 * Three defects, three promises the panel makes:
 *   filter-ignored  — the academic-year filter stops narrowing the rows.
 *   null-as-pass    — a subject nobody has graded is counted as a pass.
 *   ceiling-forged  — the passing line becomes a constant instead of the grade scale on file.
 *
 * Two readings this harness had to learn the hard way, both recorded in AGENTS.md: exec() answers
 * only the LAST line of a command, so a run whose summary line is blank looks like no run at all;
 * and Pest colours its summary, so the escape sequences sit between "Tests:" and the figure — a
 * reader that does not strip them calls a green suite a broken instrument.
 *
 * The control's expected tally is counted out of the test file itself, never typed here: a
 * hard-coded figure goes quietly stale the day a case is added, and then every leg is judged
 * against a number that describes nothing.
 *
 * Usage: php storage/app/scratch/mutate-g1-grade-history-tests.php
 */

$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
chdir($root);

$target = $root.'/app/Services/AcademicStandingService.php';
$testFile = 'tests/Feature/Registrar/RegistrarGradeHistoryTest.php';

$clean = (string) file_get_contents($target);
$hash0 = hash('sha256', $clean);
$testSource = (string) file_get_contents($root.'/'.$testFile);

if ($clean === '') {
    fwrite(STDERR, "REFUSED: cannot read {$target}.\n");
    exit(1);
}
if ($testSource === '') {
    fwrite(STDERR, "REFUSED: cannot read {$testFile}.\n");
    exit(1);
}

$caseCount = substr_count($testSource, '#[Test]');
if ($caseCount < 1) {
    fwrite(STDERR, "REFUSED: {$testFile} declares no #[Test] method, so there is nothing to judge.\n");
    exit(1);
}

$cases = [
    [
        'filter-ignored',
        '            if ($academicYearId !== null && (int) $row->academicYearId !== $academicYearId) {',
        '            if (false && (int) $row->academicYearId !== $academicYearId) {',
        'the_academic_year_filter_narrows_the_rows_and_the_value_applied_comes_back',
    ],
    [
        'null-as-pass',
        "                'result' => \$isGraded ? ((float) \$row->grade <= \$ceiling ? 'pass' : 'fail') : null,",
        "                'result' => \$isGraded ? ((float) \$row->grade <= \$ceiling ? 'pass' : 'fail') : 'pass',",
        'a_subject_with_no_grade_recorded_is_never_counted_as_a_pass',
    ],
    [
        'ceiling-forged',
        "        \$ceiling = Gradescale::passingCeiling();\n\n        \$rows = DB::table('enrolledsubjects as es')",
        "        \$ceiling = 3.0;\n\n        \$rows = DB::table('enrolledsubjects as es')",
        'the_result_is_read_from_the_grade_scale_on_file_and_the_assumed_line_is_named_when_none_is',
    ],
];

$restore = function () use ($target, $clean, $hash0): void {
    file_put_contents($target, $clean);
    if (hash('sha256', (string) file_get_contents($target)) !== $hash0) {
        fwrite(STDERR, "BAD: the restore did not reproduce the original bytes of {$target}.\n");
        exit(1);
    }
};

// This harness can die on its own assertion; the tree must not stay mutated.
register_shutdown_function($restore);

/**
 * @return array{state: string, word: string, count: int, line: string}
 */
$run = function (?string $filter) use ($php, $testFile): array {
    $cmd = escapeshellarg($php).' artisan test '.escapeshellarg($testFile);
    if ($filter !== null) {
        $cmd .= ' --filter '.escapeshellarg($filter);
    }

    $raw = (string) shell_exec($cmd.' 2>&1');
    $out = (string) preg_replace('/\e\[[0-9;]*m/', '', $raw);

    if (trim($out) === '') {
        return ['state' => 'NO-OUTPUT', 'word' => 'nothing', 'count' => 0, 'line' => 'the run wrote no bytes at all — the invocation is broken, not the suite'];
    }
    if (! preg_match('/Tests:\s+([0-9]+) (passed|failed|incomplete)/', $out, $m)) {
        return ['state' => 'BROKEN', 'word' => 'unread', 'count' => 0, 'line' => 'no Tests: line; tail was: '.substr(trim($out), -300)];
    }

    return [
        'state' => $m[2] === 'passed' ? 'GREEN' : ($m[2] === 'failed' ? 'RED' : 'ODD'),
        'word' => $m[2],
        'count' => (int) $m[1],
        'line' => trim($m[0]),
    ];
};

$failures = 0;

$expect = function (string $label, ?string $filter, string $wantWord, int $wantCount) use ($run, &$failures): void {
    $r = $run($filter);
    if ($r['word'] !== $wantWord || $r['count'] !== $wantCount) {
        echo "BAD  {$label}: expected {$wantCount} {$wantWord}, the run answered {$r['count']} {$r['word']} ({$r['state']}) — {$r['line']}\n";
        $failures++;

        return;
    }

    echo "ok   {$label}        {$r['line']}\n";
};

// Measured first, or every verdict after it is unanchored.
$expect('control', null, 'passed', $caseCount);

foreach ($cases as [$label, $from, $to, $method]) {
    if (strpos($clean, $to) !== false) {
        echo "BAD  {$label}: the replacement text is already in the file, so this mutation would change nothing.\n";
        $failures++;

        continue;
    }

    $occurrences = substr_count($clean, $from);
    if ($occurrences !== 1) {
        echo "BAD  {$label}: the needle matched {$occurrences} place(s), expected exactly 1 — the defect cannot be planted.\n";
        $failures++;

        continue;
    }

    if (substr_count($testSource, $method) !== 1) {
        echo "BAD  {$label}: the case name {$method} is not spelled exactly once in the test file.\n";
        $failures++;

        continue;
    }

    file_put_contents($target, str_replace($from, $to, $clean));
    if (hash('sha256', (string) file_get_contents($target)) === $hash0) {
        echo "BAD  {$label}: the mutation left the file byte-identical, so this run would measure the clean instrument.\n";
        $failures++;
        $restore();

        continue;
    }

    try {
        $expect($label, $method, 'failed', 1);
    } finally {
        $restore();
    }
}

// The reverse direction: a harness that only ever reports red is as useless as one that never does.
$expect('control-after', null, 'passed', $caseCount);

if ($failures > 0) {
    echo "\n{$failures} leg(s) failed to prove the grade-history tests can fail.\n";
    exit(1);
}

echo "\nAll ".(count($cases) + 2).' legs passed: the panel is guarded by tests that bite.\n';
exit(0);
