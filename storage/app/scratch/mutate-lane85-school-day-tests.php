<?php

/**
 * Lane 85: prove the school-day stamp tests can fail.
 *
 * Three desk paths read the machine's UTC civil day where the school's civil day is meant: the
 * issuer's `formIssuedDate`, the certificate's printed fallback, and the Reference Data terms
 * Active/Inactive filter. Each was fixed to `SchoolTime::today()`, and each has a case that is
 * green on the fixed bytes. A green case proves nothing until the defect it names makes it red —
 * so this harness plants exactly that defect, one file at a time, and requires the named case to
 * report `1 failed`.
 *
 * It refuses rather than reporting a pass:
 *  - when a target file already carries one of its own mutant shapes (two overlapping runs of a
 *    mutating harness once wrote a mutant into the tree permanently);
 *  - when a needle matches nothing (a mutant that cannot land is a mutant that sleeps);
 *  - when the written bytes equal the snapshot (a no-op mutation measures nothing);
 *  - when the suite prints no `Tests:` line (a broken instrument is not a result).
 *
 * Run: php storage/app/scratch/mutate-lane85-school-day-tests.php
 */

$root = dirname(__DIR__, 3);
chdir($root);

$issuer = 'app/Services/EnrollmentIssuer.php';
$registrar = 'app/Http/Controllers/Registrar/RegistrarController.php';
$refdata = 'app/Http/Controllers/Admin/ReferenceDataController.php';
$printTest = 'tests/Feature/Print/PrintControllerTest.php';
$refTest = 'tests/Feature/Admin/ReferenceDataCrudTest.php';

$cases = [
    [
        'label' => 'the issue stamp falls back to the machine\'s day',
        'file' => $issuer,
        'from' => "'formIssuedDate' => SchoolTime::today(),",
        'to' => "'formIssuedDate' => now()->toDateString(),",
        'testFile' => $printTest,
        'method' => 'an_issued_form_is_stamped_on_the_schools_day_not_the_machines',
    ],
    [
        'label' => 'the certificate dates itself in UTC',
        'file' => $registrar,
        'from' => '?? SchoolTime::today(),',
        'to' => '?? now()->toDateString(),',
        'testFile' => $printTest,
        'method' => 'the_certificate_dates_itself_on_the_schools_day_when_the_form_carries_no_issue_date',
    ],
    [
        'label' => 'the term filter asks the daemon what day it is',
        'file' => $refdata,
        'from' => '$today = SchoolTime::today();',
        'to' => '$today = now()->toDateString();',
        'testFile' => $refTest,
        'method' => 'the_terms_status_filter_answers_on_the_schools_day_not_the_machines',
    ],
];

$php = PHP_BINARY;

/** Run one Pest method (or the whole file when no method is given) and return [0, stripped output]. */
$runOne = function (string $testFile, string $method) use ($php): array {
    $cmd = escapeshellarg($php).' artisan test '.escapeshellarg($testFile).' --colors=never';
    if ($method !== '') {
        $cmd .= ' --filter='.escapeshellarg($method);
    }
    $out = (string) shell_exec($cmd.' 2>&1');
    $out = (string) preg_replace('/\e\[[0-9;]*m/', '', $out);

    return [0, $out];
};

/** Read the tally line the suite itself printed. */
$tally = function (string $out): ?string {
    if (preg_match('/Tests:\s+([^\r\n]+)/u', $out, $m) === 1) {
        return trim($m[1]);
    }

    return null;
};

$failures = 0;
$snapshots = [];

$read = static fn (string $f): string => (string) file_get_contents($f);

// Refuse up front if the tree does not carry the fix at the site under test: the needle is the
// fixed bytes, so a missing needle means either the batch never landed or another mutating run is
// mid-flight and already swapped it. Both must stop the run before anything is written.
foreach ($cases as $c) {
    $here = $read($c['file']);
    $hits = substr_count($here, $c['from']);
    if ($hits !== 1) {
        fwrite(STDERR, "REFUSED: {$c['file']} contains the needle \"{$c['from']}\" {$hits} time(s), expected exactly 1.\n"
            ."Either the fix is not in the tree this harness reads, or another run has a mutant planted.\n");
        exit(1);
    }
}

foreach ([...array_column($cases, 'file'), ...array_column($cases, 'testFile')] as $f) {
    $snapshots[$f] = $read($f);
}

try {
    echo "=== control: both test files are green on the fixed bytes ===\n";
    foreach (array_unique(array_column($cases, 'testFile')) as $testFile) {
        [, $out] = $runOne($testFile, '');
        $t = $tally($out);
        // A file that executed nothing is not a green file: require a positive passed count.
        if ($t === null || str_contains($t, 'failed') || ! preg_match('/^\s*(\d+) passed/', $t, $n) || (int) $n[1] < 1) {
            fwrite(STDERR, "REFUSED: control for {$testFile} is not a green run (tally: ".($t ?? 'none printed').")\n");
            exit(1);
        }
        printf("  ok   %-46s %s\n", basename($testFile), $t);
    }

    echo "\n=== planted defects: each must turn its own case red ===\n";
    foreach ($cases as $c) {
        // str_replace() has no limit parameter — its fourth argument is the by-reference $count, so
        // the preg_replace shape fatals. Uniqueness is checked first, then one replace is exact.
        $count = substr_count($snapshots[$c['file']], $c['from']);
        $bytes = $count === 1 ? str_replace($c['from'], $c['to'], $snapshots[$c['file']]) : $snapshots[$c['file']];
        if ($count !== 1) {
            fwrite(STDERR, "BAD    {$c['label']}: needle matched {$count} time(s), expected exactly 1\n");
            $failures++;

            continue;
        }
        if ($bytes === $snapshots[$c['file']]) {
            fwrite(STDERR, "BAD    {$c['label']}: the mutation changed no byte\n");
            $failures++;

            continue;
        }
        file_put_contents($c['file'], $bytes);

        [, $out] = $runOne($c['testFile'], $c['method']);
        $t = $tally($out);

        if ($t === null) {
            fwrite(STDERR, "BAD    {$c['label']}: the suite printed no tally — broken instrument, not a result\n");
            $failures++;
        } elseif (! str_contains($t, '1 failed')) {
            fwrite(STDERR, "SURVIVED {$c['label']}: case reported \"$t\", expected \"1 failed\"\n");
            $failures++;
        } else {
            printf("  ok   %-52s %s\n", $c['label'], $t);
        }

        file_put_contents($c['file'], $snapshots[$c['file']]);
    }

    echo "\n=== control again: the restores are byte-exact and the cases are green ===\n";
    foreach ($cases as $c) {
        if ($read($c['file']) !== $snapshots[$c['file']]) {
            fwrite(STDERR, "BAD    {$c['file']} is not the bytes this harness read\n");
            $failures++;

            continue;
        }
        [, $out] = $runOne($c['testFile'], $c['method']);
        $t = $tally($out);
        if ($t === null || str_contains($t, 'failed')) {
            fwrite(STDERR, "BAD    {$c['method']} did not return to green (".($t ?? 'no tally').")\n");
            $failures++;

            continue;
        }
        printf("  ok   %-46s %s\n", $c['method'], $t);
    }
} finally {
    // Restore every touched file unconditionally: a guard that makes the restore a no-op after the
    // first call leaves each later mutant in the tree.
    foreach ($snapshots as $f => $bytes) {
        if ($read($f) !== $bytes) {
            file_put_contents($f, $bytes);
            echo "restored {$f}\n";
        }
    }
}

echo $failures === 0
    ? "\nOK — every school-day stamp has a case that fails the moment the machine's day is used.\n"
    : "\n{$failures} case(s) failed.\n";

exit($failures === 0 ? 0 : 1);
