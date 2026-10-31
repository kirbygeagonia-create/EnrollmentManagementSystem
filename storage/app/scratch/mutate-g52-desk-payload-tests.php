<?php

/**
 * Proof that §28.3 G-52's desk-payload guards can fail.
 *
 * Three legs, each planting a defect this batch actually met, and each requiring the NAMED case to report
 * `1 failed` rather than merely "not passed" — a mutant that does not compile is not a killed defect:
 *
 *  control   the eight affected classes, green before anything is planted and green again at the end.
 *  leak      Clinic's student load reverted to a bare `'student'` — the exact pre-fix bytes. BOTH the queue's
 *            payload case and the source-side guard must go red: the case proves the payload ships the record,
 *            the guard proves the same fact without a fixture.
 *  badcolumn Accounting's list widened to include `id`, the column `students` has never held. The queue case must
 *            fail on `assertOk()` — that is the assertion this batch added precisely because a 500 and an
 *            unsorted list both read as "the two lists differ" to a case that never asks the response.
 *
 * Files are restored by SHA-1 immediately after each verdict, so no leg measures a tree a previous leg mutated
 * (lane 107's harness lesson), and the final control is the proof the tree came back whole.
 *
 * Usage: php storage/app/scratch/mutate-g52-desk-payload-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = (string) PHP_BINARY;

$argvBad = array_values(array_slice($argv, 1));
if ($argvBad !== []) {
    fwrite(STDERR, "REFUSED: this harness reads no arguments (got ".implode(', ', $argvBad).").\n");

    exit(1);
}

$payloadCase = 'the_queue_loads_the_student_for_the_identity_columns_and_nothing_else';
// The two money queues and the Registrar queue already owned a payload case — `an_order_that_joins_…` — and this
// batch's assertOk and nested-key assertions were added INSIDE it, so filtering them by the new name selects no
// test and the run answers "No tests found", which is not a killed mutant.
$payloadCaseJoined = 'an_order_that_joins_a_relation_ships_the_rows_own_columns_and_nothing_else';
$guardCase = 'no_desk_action_loads_a_whole_student_except_the_sites_the_register_names';

$classes = [
    'tests/Feature/Sorting/ClinicQueueSortTest.php',
    'tests/Feature/Sorting/IDQueueSortTest.php',
    'tests/Feature/Sorting/ClearanceQueueSortTest.php',
    'tests/Feature/Admission/AdmissionQueueSortTest.php',
    'tests/Feature/Sorting/AssessmentQueueSortTest.php',
    'tests/Feature/Sorting/AccountingQueueSortTest.php',
    'tests/Feature/Registrar/RegistrarQueueSortTest.php',
    'tests/Feature/DeskQueue/DeskStudentLoadColumnsTest.php',
];

$strip = static fn (string $s): string => preg_replace('/\e\[[0-9;]*m/', '', $s) ?? $s;

$run = static function (string $cmd) use ($strip): array {
    exec($cmd.' 2>&1', $out);
    $text = $strip(implode("\n", $out));
    // Read the `Tests:` line as a set of counts, not as one rigid pattern. The first cut required
    // `N failed … M passed` in that order, so a run whose line is `Tests: 1 failed (23 assertions)` — a
    // failures-only run, which is exactly what a killed mutant produces — answered "0 failed", and two legs
    // reported their own success as a failure of the guard.
    $failed = 0;
    $passed = 0;
    if (preg_match('/^\s*Tests:\s+(.*)$/m', $text, $lm) === 1) {
        if (preg_match_all('/(\d+)\s+(passed|failed|errored|skipped)/', $lm[1], $cm, PREG_SET_ORDER)) {
            foreach ($cm as $c) {
                if ($c[2] === 'passed') {
                    $passed = (int) $c[1];
                } elseif ($c[2] !== 'skipped') {
                    $failed += (int) $c[1];
                }
            }
        }
    }

    return [
        'text' => $text,
        'failed' => $failed,
        'passed' => $passed,
        'saw_tally' => str_contains($text, 'Tests:') || str_contains($text, 'ERRORS'),
        // A filter that selects no test prints INFO "No tests found" and exits 0. Read as a killed mutant, that is
        // a green over nothing — the shape this repository has been caught by three times.
        'no_tests' => str_contains($text, 'No tests found'),
    ];
};

$fails = 0;
$log = static function (string $name, bool $ok, string $detail) use (&$fails): void {
    if (! $ok) {
        $fails++;
    }
    printf("%s   %-16s %s\n", $ok ? 'PASS' : 'FAIL', $name, $detail);
};

/** @var array<string, array{sha1: string, bytes: string}> $snap file => the bytes each leg started from */
$snap = [];
$snapshot = static function (string $rel) use (&$snap, $root): string {
    $bytes = (string) file_get_contents($root.'/'.$rel);
    $snap[$rel] = ['sha1' => sha1($bytes), 'bytes' => $bytes];

    return $bytes;
};
$restore = static function (string $rel) use (&$snap, $root): void {
    if (! isset($snap[$rel])) {
        return;
    }
    $path = $root.'/'.$rel;
    if (sha1((string) file_get_contents($path)) !== $snap[$rel]['sha1']) {
        file_put_contents($path, $snap[$rel]['bytes']);
    }
    if (sha1((string) file_get_contents($path)) !== $snap[$rel]['sha1']) {
        fwrite(STDERR, "REFUSED: {$rel} did not restore to the bytes the leg started from.\n");

        exit(1);
    }
};

$plant = static function (string $rel, string $from, string $to) use ($root, $snapshot): bool {
    $bytes = $snapshot($rel);
    $n = substr_count($bytes, $from);
    if ($n !== 1) {
        fwrite(STDERR, sprintf("  leg anchor matched %d time(s) in %s, wanted exactly 1 — %s\n", $n, $rel, substr($from, 0, 60)));

        return false;
    }
    file_put_contents($root.'/'.$rel, str_replace($from, $to, $bytes));

    return true;
};

try {
    // ── control ────────────────────────────────────────────────────────────────────────────────────────────────
    $r = $run(escapeshellarg($php).' artisan test '.implode(' ', array_map('escapeshellarg', $classes)));
    $controlPassed = $r['saw_tally'] && $r['failed'] === 0 && $r['passed'] > 0;
    $log('control', $controlPassed, $r['saw_tally']
        ? sprintf('%d passed / %d failed across %d classes', $r['passed'], $r['failed'], count($classes))
        : 'the run printed no `Tests:` line, so nothing was measured');
    if (! $controlPassed) {
        echo substr($r['text'], -1500)."\n";

        fwrite(STDERR, "REFUSED: a red control refuses the whole run — a harness cannot prove a guard over a suite that is already failing.\n");

        exit(1);
    }

    // ── leg 1: the leak, planted as the pre-fix bytes ──────────────────────────────────────────────────────────
    $rel1 = 'app/Http/Controllers/Clinic/ClinicController.php';
    $from1 = "'student:studentId,schoolIdNumber,lastName,firstName,middleName'";
    $to1 = "'student'";
    if (! $plant($rel1, $from1, $to1)) {
        $log('leak-payload-case', false, 'anchor did not apply (the harness is old, not the code)');
    } else {
        $r = $run(escapeshellarg($php).' artisan test tests/Feature/Sorting/ClinicQueueSortTest.php --filter='
            .escapeshellarg($payloadCase));
        $log('leak-payload-case', $r['failed'] === 1, $r['saw_tally']
            ? sprintf('%d failed, %d passed', $r['failed'], $r['passed'])
            : ($r['no_tests'] ? 'NO TESTS SELECTED — the filter named a case that does not exist, so this leg cannot kill anything' : 'no tally'));
        $r = $run(escapeshellarg($php).' artisan test tests/Feature/DeskQueue/DeskStudentLoadColumnsTest.php'
            .' --filter='.escapeshellarg($guardCase));
        $named = str_contains($r['text'], 'ClinicController::index');
        $log('leak-source-guard', $r['failed'] === 1 && $named, $r['saw_tally']
            ? sprintf('%d failed, names ClinicController::index: %s', $r['failed'], $named ? 'yes' : 'NO')
            : ($r['no_tests'] ? 'NO TESTS SELECTED — the filter named a case that does not exist, so this leg cannot kill anything' : 'no tally'));
        $restore($rel1);
    }

    // ── leg 2: the column `students` never had ────────────────────────────────────────────────────────────────
    $rel2 = 'app/Http/Controllers/Accounting/AccountingController.php';
    // Anchored on the whole call, because `enrollment.student:<5 cols>` occurs three times in this file (the queue
    // and both money lists on the daily report) — a bare-literal anchor is not unique, and the first cut of this
    // leg found three matches and refused itself rather than planting in the wrong statement.
    $from2 = "Studentassessments::with(['enrollment.student:studentId,schoolIdNumber,lastName,firstName,middleName', 'enrollment.course', 'enrollment.term'])";
    $to2 = "Studentassessments::with(['enrollment.student:id,studentId,schoolIdNumber,lastName,firstName,middleName', 'enrollment.course', 'enrollment.term'])";
    if (substr_count((string) file_get_contents($root.'/'.$rel2), $from2) !== 1) {
        $log('bad-column-case', false, 'anchor not unique in Accounting — the harness is old, not the code');
        // Still snapshot so the restore below is a no-op rather than a wipe.
        $snapshot($rel2);
    } elseif (! $plant($rel2, $from2, $to2)) {
        $log('bad-column-case', false, 'anchor did not apply');
    } else {
        $r = $run(escapeshellarg($php).' artisan test tests/Feature/Sorting/AccountingQueueSortTest.php'
            .' --filter='.escapeshellarg($payloadCaseJoined));
        // Measured, not assumed: on SQLite a `students.id` that the table has never held does NOT 500 — it arrives
        // as an extra payload key, so `assertOk()` passes and the exact key-set assertion is what refuses. On the
        // school's MySQL the same list is a query error and the status assertion fires. The two guards cover
        // different halves of the same defect on different drivers, which is why both are in the case.
        $namedKey = str_contains($r['text'], "=> '\"id\"'") || str_contains($r['text'], 'Unknown column');
        $log('bad-column-case', $r['failed'] === 1 && $namedKey, $r['saw_tally']
            ? sprintf('%d failed, the extra `id` key named in the diff: %s', $r['failed'], $namedKey ? 'yes' : 'NO')
            : ($r['no_tests'] ? 'NO TESTS SELECTED — the filter named a case that does not exist, so this leg cannot kill anything' : 'no tally'));
        $restore($rel2);
    }

    // ── final control: the tree came back ─────────────────────────────────────────────────────────────────────
    $r = $run(escapeshellarg($php).' artisan test '.implode(' ', array_map('escapeshellarg', $classes)));
    $log('control-again', $r['saw_tally'] && $r['failed'] === 0, $r['saw_tally']
        ? sprintf('%d passed / %d failed', $r['passed'], $r['failed'])
        : 'no tally — the run executed nothing, which is not a pass');
    // The tree is read back directly rather than through git: `shell_exec` cannot reach git from PHP on this box,
    // and a check that silently returns nothing is the cleanest-looking lie this repository knows.
    $dirty = [];
    foreach (array_keys($snap) as $rel) {
        if (sha1((string) file_get_contents($root.'/'.$rel)) !== $snap[$rel]['sha1']) {
            $dirty[] = $rel;
        }
    }
    $clinic = (string) file_get_contents($root.'/'.$rel1);
    $acct = (string) file_get_contents($root.'/'.$rel2);
    // Restored means: the narrowed string is back where each leg planted, and the planted bytes are gone.
    // The first cut also demanded that no bare `'student'` remain in Clinic at all — but Clinic's own detail
    // screen still loads one, and §28.3 G-52 says so; a tree-clean check that contradicts the register reports a
    // dirty tree on a clean one.
    if (substr_count($clinic, $from1) !== 1 || substr_count($acct, $from2) !== 1 || substr_count($acct, $to2) !== 0) {
        $dirty[] = 'a planted shape is still on disk although the snapshots matched';
    }
    $log('tree-clean', $dirty === [], $dirty === []
        ? 'both controllers read back the narrowed bytes; every snapshot matches'
        : implode(' | ', $dirty));
} finally {
    foreach (array_keys($snap) as $rel) {
        $restore($rel);
    }
}

printf("\n%s leg(s) reported, %d failure(s).\n", 6, $fails);
if ($fails > 0) {
    exit(1);
}
echo "ALL KILLED: every planted defect was reported by the case that names it, and the tree is as it was found.\n";
