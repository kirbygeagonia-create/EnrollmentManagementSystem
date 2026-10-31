<?php

/**
 * Mutant harness for the nullable hop (§28.3 G-40's LEFT join, task #3).
 *
 * The promise under test is narrow and easy to lose silently: a column reached through a hop whose key can be NULL
 * must ORDER the list without DELETING rows from it, and a spec that does not ask for `left` must keep exactly the
 * SQL the twelve already-wired screens were proved on. Both halves are counted, not sequenced — `lane120` proved a
 * sequence alone cannot tell a LEFT join from an INNER one whose fixture happens to be complete.
 *
 * Each leg plants one defect, runs the ONE case that owns it, and requires that case to print `1 failed`. A mutant
 * that does not compile, a needle that matches nothing, and a case that selects no test are all reported BAD — a
 * leg that cannot fail is decoration, which is the rule this repository has paid for repeatedly.
 *
 * Usage: php storage/app/scratch/mutate-g40-left-hop-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
if (! is_file($php)) {
    fwrite(STDERR, "REFUSED: no PHP at {$php}\n");

    exit(1);
}

$target = $root.'/app/Support/ListSort.php';
$test = 'tests/Feature/Sorting/SortHopAcrossANullableKeyTest.php';
if (! is_file($target) || ! is_file($root.'/'.$test)) {
    fwrite(STDERR, "REFUSED: the helper or its case file is missing — nothing to prove.\n");

    exit(1);
}

$original = (string) file_get_contents($target);
$hash0 = hash('sha256', $original);

$restore = function () use ($target, $original, $hash0): void {
    $w = @file_put_contents($target, $original);
    if ($w !== strlen($original) || hash('sha256', (string) file_get_contents($target)) !== $hash0) {
        fwrite(STDERR, "REFUSED: could not restore {$target} — repair it with git checkout and re-run.\n");

        exit(1);
    }
};
register_shutdown_function($restore);

$legs = [
    [
        'name' => 'left-reverted-to-inner',
        'from' => '$query->leftJoin(',
        'to' => '$query->join(',
        'case' => 'a_hop_asked_as_left_keeps_the_rows_that_have_no_one_on_the_far_side',
        'promise' => 'a hop that may find no one still keeps the row',
    ],
    [
        'name' => 'type-not-carried-into-the-hop',
        'from' => "'local' => \$spec['local'], 'type' => \$spec['type'] ?? 'inner'",
        'to' => "'local' => \$spec['local']",
        'case' => 'a_hop_asked_as_left_keeps_the_rows_that_have_no_one_on_the_far_side',
        'promise' => 'the single-hop spelling can ask for a left join at all',
    ],
    [
        'name' => 'left-became-the-default',
        'from' => "if ((\$join['type'] ?? 'inner') === 'left') {",
        'to' => 'if (true) {',
        'case' => 'a_hop_asked_as_left_keeps_the_rows_that_have_no_one_on_the_far_side',
        'promise' => 'a spec that never asked for left keeps the INNER SQL the wired screens were proved on',
    ],
    [
        'name' => 'select-pin-skipped-for-the-hop',
        'from' => "if (\$query->getQuery()->getColumns() === []) {\n                \$query->select(\$query->getModel()->qualifyColumn('*'));",
        'to' => "if (false) {\n                \$query->select(\$query->getModel()->qualifyColumn('*'));",
        'case' => 'the_hop_that_orders_a_nullable_column_still_never_reaches_the_payload',
        'promise' => 'G-51 is a property of joining, so the pin must fire for a LEFT hop too',
    ],
];

function runCase(string $php, string $test, string $case): array
{
    // An empty case means the whole file: the control must run every case, and `--filter=''` selects nothing at
    // all, which a naive harness reads as "green".
    $cmd = escapeshellarg($php).' artisan test '.escapeshellarg($test)
        .($case === '' ? '' : ' --filter='.escapeshellarg($case)).' 2>&1';
    $out = (string) shell_exec($cmd);
    // Pest writes ANSI sequences into captured output; the tally regex must not depend on them.
    $plain = (string) preg_replace('/\e\[[0-9;]*m/', '', $out);
    $failed = preg_match('/Tests:\s+\d+ failed/', $plain) === 1;
    $selected = preg_match('/Tests:\s/', $plain) === 1;

    return [$failed, $selected, $plain];
}

echo "control: the case file must be green before anything is planted\n";
[$f, $s, $o] = runCase($php, $test, '');
if (! $s || $f) {
    fwrite(STDERR, "REFUSED: the control is not green, so no verdict below would mean anything.\n".substr($o, 0, 400)."\n");
    exit(1);
}
echo "  ok   control green\n";

$bad = 0;
foreach ($legs as $leg) {
    $n = substr_count($original, $leg['from']);
    if ($n !== 1) {
        printf("  BAD  %-36s needle occurs %d time(s), wanted 1 — the helper has moved and this leg proves nothing\n", $leg['name'], $n);
        $bad++;

        continue;
    }
    $mutated = str_replace($leg['from'], $leg['to'], $original);
    if ($mutated === $original) {
        printf("  BAD  %-36s MUTANT DID NOT APPLY — the replacement changed no bytes\n", $leg['name']);
        $bad++;

        continue;
    }
    $w = @file_put_contents($target, $mutated);
    if ($w !== strlen($mutated)) {
        printf("  BAD  %-36s PLANT FAILED — the write returned %s, so this leg measured an untouched helper\n", $leg['name'], var_export($w, true));
        $bad++;
        $restore();

        continue;
    }
    // A mutant that will not compile is not a killed defect: check the bytes parse before believing the case.
    exec(escapeshellarg($php).' -l '.escapeshellarg($target).' 2>&1', $lintOut, $lintRc);
    if ($lintRc !== 0) {
        printf("  BAD  %-36s the planted bytes do not parse (%s) — that is not a killed defect\n", $leg['name'], trim(implode(' ', $lintOut)));
        $bad++;
        $restore();
        $lintOut = [];

        continue;
    }
    $lintOut = [];
    [$failed, $selected] = runCase($php, $test, $leg['case']);
    if (! $selected) {
        printf("  BAD  %-36s --filter '%s' selected no test\n", $leg['name'], $leg['case']);
        $bad++;
    } elseif ($failed) {
        printf("  ok   %-36s killed by %s\n", $leg['name'], $leg['case']);
    } else {
        printf("  BAD  %-36s SURVIVED — %s stayed green, so the promise is not read by any case\n", $leg['name'], $leg['case']);
        $bad++;
    }
    $restore();
}

[$f2, $s2, $o2] = runCase($php, $test, '');
if (! $s2 || $f2) {
    fwrite(STDERR, "REFUSED: the trailing control is not green, so this run left the tree or the helper wrong.\n");
    exit(1);
}
echo "  ok   trailing control green, helper restored by hash\n";

printf("%s ALL KILLED: %d leg(s), %d bad\n", $bad === 0 ? 'ok  ' : 'FAIL', count($legs), $bad);
exit($bad === 0 ? 0 : 1);
