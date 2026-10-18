<?php

/**
 * Lane 49 — prove lane49-zero-shown-as-absence.php can fail.
 *
 * The checker's claim is narrow: a money or percent figure that is its own guard, so the figure's 0 falls to
 * the dash. Each case puts one of the three fixed sites back to the shape it had before this batch and
 * requires HIDDEN-ZERO to appear again; a case whose anchor does not match exactly once refuses rather than
 * reporting the checker's own clean output as a pass. The restore is unconditional — a guarded restore that
 * ran only once left three mutants in the tree during lane 48 and the next case measured them as findings.
 *
 * Usage: php storage/app/scratch/mutate-lane49-checker.php [--only=percent]
 */

$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$checker = 'storage/app/scratch/lane49-zero-shown-as-absence.php';

$only = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = substr($arg, strlen('--only='));
    }
}

$files = [
    'students' => $root.'/resources/js/Pages/Students/Show.jsx',
    'assessment' => $root.'/resources/js/Pages/Assessment/Show.jsx',
];

$original = [];
$hash0 = [];
foreach ($files as $k => $path) {
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
$restore = function () use ($files, $original, $hash0, &$failures): void {
    foreach ($files as $k => $path) {
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

$run = function (array $env = []) use ($php, $root, $checker): array {
    $pairs = '';
    foreach ($env as $k => $v) {
        // cmd's `set X="Y"` keeps the quotes inside the value, so the whole assignment is quoted instead
        $pairs .= 'set "'.str_replace('"', '', $k.'='.$v).'" && ';
    }
    $out = [];
    $code = 0;
    exec('cd /d '.escapeshellarg($root).' && '.$pairs.escapeshellarg($php).' '.escapeshellarg($root.'/'.$checker).' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};

/**
 * Compare against the number this run measured, never against a number remembered from the last one.
 *
 * A harness that hard-codes "5 presence-tests" fails the moment the checker learns a new shape, and the
 * failure looks like a broken checker rather than a stale expectation.
 */
$judged = function (string $out): array {
    return preg_match('/Judged: (\d+) hidden zero\(s\), (\d+) correct presence-tests/', $out, $m)
        ? [(int) $m[1], (int) $m[2]]
        : [-1, -1];
};

$base = null;
$case = function (string $name, string $key, string $from, string $to, string $needle = 'HIDDEN-ZERO') use ($run, $restore, $original, $files, $judged, &$failures, &$base, $only): void {
    if ($only !== null && ! str_contains($name, $only)) {
        return;
    }
    $hits = substr_count($original[$key], $from);
    if ($hits !== 1) {
        printf("  BAD  %-22s anchor matched %d times, expected 1 — this mutant would sleep\n", $name, $hits);
        $restore();
        $failures++;

        return;
    }
    file_put_contents($files[$key], str_replace($from, $to, $original[$key]));
    if ((string) file_get_contents($files[$key]) === $original[$key]) {
        printf("  BAD  %-22s the mutation left the file byte-identical\n", $name);
        $restore();
        $failures++;

        return;
    }
    [$code, $out] = $run();
    $restore();
    [$zeros, $presence] = $judged($out);
    // the hidden tally must grow by exactly the one planted guard; the presence tally may *shrink* by that
    // same one, because the mutation turns a correct presence-test into the defect it is testing for
    if ($code !== 0 && str_contains($out, $needle) && $zeros === $base[0] + 1 && $presence <= $base[1]) {
        printf("  ok   %-22s the checker saw it again (%d hidden, %d presence-tests left of %d)\n", $name, $zeros, $presence, $base[1]);
    } else {
        printf("  FAIL %-22s expected %d hidden zero(s) and no more than %d presence-tests, got %d/%d (exit %d, needle %s)\n",
            $name, $base[0] + 1, $base[1], $zeros, $presence, $code, str_contains($out, $needle) ? 'present' : 'absent');
        $failures++;
    }
};

echo "Baseline — the fixed tree must read clean first.\n";
[$code, $out] = $run();
$base = $judged($out);
if ($code === 0 && $base[0] === 0 && $base[1] >= 3) {
    printf("  ok   clean tree: 0 hidden zeros, %d presence-tests recognised\n", $base[1]);
} else {
    printf("  BAD  the fixed tree is not what this harness expects (exit %d, judged %s)\n", $code, implode('/', $base));
    $restore();
    exit(1);
}

echo "\nMutants — the three shapes this batch fixed, put back one at a time.\n";
$case('paid-guards-its-sum', 'students',
    "{totalAssessment == null ? '—' : `₱\${totalPaid.toLocaleString('en-PH')}`}",
    "{totalPaid > 0 ? `₱\${totalPaid.toLocaleString('en-PH')}` : '—'}");
$case('assessed-tile-guards-its-sum', 'students',
    "value={assessed.length > 0 ? `₱\${totalAssessed.toLocaleString('en-PH')}` : '—'}",
    "value={totalAssessed > 0 ? `₱\${totalAssessed.toLocaleString('en-PH')}` : '—'}");
$case('percent-guards-itself', 'assessment',
    "row.scholarshipType?.coveragePercent == null ? '—' : `\${row.scholarshipType.coveragePercent}%`",
    "row.scholarshipType?.coveragePercent ? `\${row.scholarshipType.coveragePercent}%` : '—'");

echo "\nIts limits stay visible, and an empty scan refuses.\n";
if (str_contains($out, 'Not read (a dash on the line, no recoverable operand)') && preg_match('/operand\): (\d+)/', $out, $bm) && (int) $bm[1] > 0) {
    printf("  ok   the run prints its %s unread guard(s) instead of hiding them\n", $bm[1]);
} else {
    echo "  FAIL the blind-spot count vanished from the summary\n";
    $failures++;
}

[$code, $scoped] = $run(['EMS_LANE49_PAGES' => 'Students']);
if (str_contains($scoped, 'Pages/Students/Show.jsx') && ! str_contains($scoped, 'Pages/Assessment/Show.jsx')) {
    echo "  ok   EMS_LANE49_PAGES=Students narrowed the scan\n";
} else {
    echo "  FAIL EMS_LANE49_PAGES=Students did not narrow the scan\n";
    $failures++;
}

[$code, $empty] = $run(['EMS_LANE49_PAGES' => 'NoSuchDeskAtAll']);
if ($code !== 0 && str_contains($empty, 'REFUSED')) {
    echo "  ok   an empty scope refused rather than reporting clean\n";
} else {
    printf("  FAIL an empty scope answered exit %d\n", $code);
    $failures++;
}

$restore();
$finished = true;

printf("\n%s\n", $failures === 0 ? 'Every shape this batch fixed, the checker can see again.' : "{$failures} harness failure(s) — the checker is not proven.");

exit($failures === 0 ? 0 : 1);
