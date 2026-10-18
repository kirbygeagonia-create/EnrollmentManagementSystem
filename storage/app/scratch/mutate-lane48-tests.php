<?php

/**
 * Lane 48 — prove DeskTileNumberSourceTest can fail.
 *
 * The test's claim is that every number a desk tile shows arrives from the handler, typed, and answering the
 * set the label names. Each case below breaks one half of that claim in the real controllers, runs the named
 * test, and requires it to go red. A test that stays green against all six is not testing the claim.
 *
 * Restore is unconditional and hash-verified: a guard that made it a no-op after the first call leaves the
 * next case measuring a tree that still carries the previous defect, and the harness would report its own
 * residue as a pass.
 *
 * Usage: php storage/app/scratch/mutate-lane48-tests.php [--only=NULL-ANSWER]
 */

$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$suite = 'tests/Feature/DeskTileNumberSourceTest.php';

$only = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = substr($arg, strlen('--only='));
    }
}

$targets = [
    'admission' => $root.'/app/Http/Controllers/Admission/AdmissionController.php',
    'accounting' => $root.'/app/Http/Controllers/Accounting/AccountingController.php',
    'blocking' => $root.'/app/Http/Controllers/Blocking/BlockingController.php',
    'exam' => $root.'/app/Http/Controllers/Exam/ExamController.php',
    'reference' => $root.'/app/Http/Controllers/Admin/ReferenceDataController.php',
    'audit' => $root.'/app/Http/Controllers/Admin/UserManagementController.php',
];

$original = [];
$hash0 = [];
foreach ($targets as $k => $path) {
    $src = @file_get_contents($path);
    if ($src === false) {
        fwrite(STDERR, "REFUSED: cannot read {$path}\n");
        exit(1);
    }
    $original[$k] = $src;
    $hash0[$k] = hash('sha256', $src);
}

$failures = 0;
$finished = false;

$restore = function () use ($targets, $original, $hash0, &$failures): void {
    foreach ($targets as $k => $path) {
        file_put_contents($path, $original[$k]);
        if (hash('sha256', (string) file_get_contents($path)) !== $hash0[$k]) {
            printf("  BAD  restore of %s did not produce the original bytes\n", $k);
            $failures++;
        }
    }
};
register_shutdown_function(function () use ($restore, &$finished): void {
    if (! $finished) {
        fwrite(STDERR, "\nThe run ended before restoring — restoring now.\n");
        $restore();
    }
});

$run = function (string $filter) use ($php, $root, $suite): array {
    $out = [];
    $code = 0;
    // an empty --filter is not "no filter" to PHPUnit, so the flag is only added when one was asked for
    $which = $filter === '' ? '' : ' --filter '.escapeshellarg($filter);
    exec('cd /d '.escapeshellarg($root).' && '.escapeshellarg($php).' artisan test '.escapeshellarg($suite).$which.' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};

$verdict = function (string $out): string {
    foreach (explode("\n", $out) as $l) {
        if (preg_match('/Tests:.*(failed|passed)/', $l)) {
            return trim(preg_replace('/\s+/', ' ', $l));
        }
    }

    return 'no test summary — the run died: '.trim(preg_replace('/\s+/', ' ', mb_substr($out, 0, 160)));
};

/** Break one claim, run the named test, and require it to fail. */
$case = function (string $name, string $filter, string $key, string $from, string $to) use ($run, $restore, $original, $targets, $verdict, &$failures, $only): void {
    if ($only !== null && ! str_contains($name, $only)) {
        return;
    }
    $hits = substr_count($original[$key], $from);
    if ($hits !== 1) {
        printf("  BAD  %-26s anchor matched %d times, expected 1 — this mutant would sleep\n", $name, $hits);
        $restore();
        $failures++;

        return;
    }
    file_put_contents($targets[$key], str_replace($from, $to, $original[$key]));
    if ((string) file_get_contents($targets[$key]) === $original[$key]) {
        printf("  BAD  %-26s the mutation left the file byte-identical\n", $name);
        $restore();
        $failures++;

        return;
    }
    [$code, $out] = $run($filter);
    $restore();
    if ($code !== 0) {
        printf("  ok   %-26s the test went red — %s\n", $name, $verdict($out));
    } else {
        printf("  FAIL %-26s the test stayed green while the claim was broken — %s\n", $name, $verdict($out));
        $failures++;
    }
};

echo "Baseline — the test file must pass on the unmutated tree before any mutant is believed.\n";
[$code, $out] = $run('');
if ($code === 0) {
    printf("  ok   unmutated: %s\n", $verdict($out));
} else {
    printf("  BAD  the test is already red, so nothing below proves anything — %s\n", $verdict($out));
    $restore();
    exit(1);
}

echo "\nMutants — each breaks one claim, and the named test must notice.\n";
// every variable in an anchor is written `\$`: a double-quoted `$var` interpolates to the empty string here,
// which plants an anchor that cannot match anything and a mutant that sleeps
$case('KEY-NOT-ANSWERED', 'the_queue_desks_answer_zero', 'admission',
    "                'total' => (int) \$statusCounts->sum(),\n", '');
$case('NULL-ANSWERED', 'the_money_desks', 'accounting',
    "'totalAssessed' => (float) (\$summary->totalAssessed ?? 0),", "'totalAssessed' => null,");
$case('STRING-ANSWERED', 'the_queue_desks_answer_zero', 'blocking',
    "'capacity' => (int) \$setRows->sum('maxStudents'),", "'capacity' => (string) \$setRows->sum('maxStudents'),");
$case('RATE-COERCED-TO-ZERO', 'a_rate_the_server', 'exam',
    "'passRate' => \$scoredCount > 0 ? (int) round(\$passCount / \$scoredCount * 100) : null,",
    "'passRate' => \$scoredCount > 0 ? (int) round(\$passCount / \$scoredCount * 100) : 0,");
$case('COUNT-NOT-THE-TABLE', 'the_admin_desks', 'reference',
    "'offices' => Offices::count(),", "'offices' => 0,");
$case('TOTAL-NOT-THE-LOG', 'the_admin_desks', 'audit',
    "'total' => \$logs->total(),", "'total' => \$logs->perPage(),");

$restore();
$finished = true;

printf("\n%s\n", $failures === 0 ? 'The test feels every break this lane claims to prevent.' : "{$failures} harness failure(s) — the test is not proven.");

exit($failures === 0 ? 0 : 1);
