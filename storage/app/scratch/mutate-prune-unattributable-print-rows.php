<?php

/**
 * Mutation-proof for prune-unattributable-print-rows.php.
 *
 * Every guard in that script is a claim that a specific wrong world gets stopped. A guard that cannot fail
 * has checked nothing, so each one is broken here in a copy of the real file — the anchor that carries it is
 * replaced once and only once, asserted — and the copy is run against `ems_lane36_rehearse`. The mutant must
 * misbehave (the run it was written to refuse now succeeds, or the deletion it was written to bound now
 * reaches further) and the unmutated script must behave, in the same run, as the control.
 *
 * Nothing here deletes from live `ems`: the only schema written is the clone, and its print table is reset
 * from `ems` between the destructive mutants. Asserted after every step: `ems.documentprintlog` is still 81.
 *
 * usage: php storage/app/scratch/mutate-prune-unattributable-print-rows.php
 */

$root = dirname(__DIR__, 3);
$php = PHP_BINARY;
$script = $root.'/storage/app/scratch/prune-unattributable-print-rows.php';
$dump = 'C:/Users/ADMIN/ems-backups/ems-pre-lane36-printprune-2026-10-15.sql';
$src = file_get_contents($script);

$emsTotal = 81;
$unattributable = 21;
$attributed = 60;
$clone = 'ems_lane36_rehearse';

$fails = 0;
$report = function (string $verdict, string $what, string $detail) use (&$fails): void {
    if ($verdict === 'PASS') {
        printf("  PASS     %-26s %s\n", $what, $detail);

        return;
    }
    printf("  SURVIVED %-26s %s\n", $what, $detail);
    $fails++;
};

/** Run a PHP command, returning [stdout+stderr, exit code]. The database comes from the process env. */
$run = function (array $cmd, ?string $db) use ($root): array {
    putenv($db === null ? 'DB_DATABASE' : 'DB_DATABASE='.$db);
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $desc, $pipes, $root);
    $out = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    foreach ($pipes as $p) {
        fclose($p);
    }
    $code = proc_close($proc);

    return [$out, $code];
};

$mysql = function (string $sql) use ($root, $php, $run): array {
    // Backslashes: CreateProcess rejects a forward-slash application name with error 193, which arrives here
    // as "CreateProcess failed" long before the SQL is ever parsed.
    return $run([$php, str_replace('/', '\\', $root.'/storage/app/scratch/mutate-prune-run-sql.php'), $sql], null);
};

$counts = function (string $schema) use ($mysql, &$report): array {
    // COALESCE because SUM over zero rows is NULL, and the runner prints an empty string for that — a table
    // this script has just emptied then measures as -1, which reads like a broken harness rather than a clean
    // delete. Measured: the whole-table mutant printed the right thing and was reported wrong.
    [$out, $code] = $mysql("SELECT COUNT(*) AS total, COALESCE(SUM(enrollmentId IS NULL AND studentId IS NULL AND blockId IS NULL),0) AS unattr FROM {$schema}.documentprintlog;");
    if ($code !== 0) {
        $report('SURVIVED', "measure {$schema}", trim($out));

        return ['total' => -1, 'unattr' => -1];
    }
    preg_match('/total:\s*(\d+)\s*unattr:\s*(\d+)/', $out, $m);

    return ['total' => (int) ($m[1] ?? -1), 'unattr' => (int) ($m[2] ?? -1)];
};

$emsOk = function () use ($counts, $emsTotal, &$report): void {
    $c = $counts('ems');
    if ($c['total'] !== $emsTotal) {
        $report('SURVIVED', 'live ems untouched', "ems now has {$c['total']} print rows, expected {$emsTotal}");
    }
};

echo "=== lane 36 prune mutation-proof (writes only to {$clone}) ===\n";

$reset = function () use ($mysql, $clone, $emsTotal, &$report, $counts): void {
    [$out, $code] = $mysql("DELETE FROM {$clone}.documentprintlog; INSERT INTO {$clone}.documentprintlog SELECT * FROM ems.documentprintlog;");
    $c = $counts($clone);
    if ($code !== 0 || $c['total'] !== $emsTotal) {
        $report('SURVIVED', 'reset clone from ems', trim($out).' | clone at '.$c['total']);
    }
};

// Rehearsal first, every time: a previous run of this harness leaves the clone pruned, and a proof that
// starts from a set the script has already deleted proves nothing.
$reset();

$before = $counts($clone);
printf("clone reset to ems: total %d / unattributable %d; ems at total %d\n", $before['total'], $before['unattr'], $counts('ems')['total']);
if ($before['unattr'] !== $unattributable) {
    fwrite(STDERR, "the clone is not the reviewed set — abort before any mutant runs\n");
    exit(1);
}

/** Write one mutant, asserting the anchor appears exactly the stated number of times. */
$mutant = function (string $name, string $anchor, string $replace, int $expectCount = 1) use ($src, $root): ?string {
    $n = substr_count($src, $anchor);
    if ($n !== $expectCount) {
        fwrite(STDERR, "anchor for mutant [{$name}] matched {$n} time(s), expected {$expectCount} — the real script moved\n");
        exit(1);
    }
    $path = $root.'/storage/app/scratch/mutant-prune-'.$name.'.php';
    file_put_contents($path, str_replace($anchor, $replace, $src));

    return $path;
};

// ---------------------------------------------------------------- guard: the complement split
// Drop the enrollmentId term and the set silently grows by the two rows that carry an enrollmentId but no
// student or block — rows the script's own `attributed` count already claims. Only the split assertion
// stands between that predicate and deleting real, attributable prints, so this mutant must be caught there.
$p = $mutant('widened-predicate', "->whereNull('enrollmentId')->whereNull('studentId')->whereNull('blockId')\n    ->orderBy('printLogId')->get();", "->whereNull('studentId')->whereNull('blockId')\n    ->orderBy('printLogId')->get();");
[$out, $code] = $run([$php, $p, $dump, '23'], $clone);
$ok = str_contains($out, 'split does not add up') && str_contains($out, '60 attributed + 23 unattributable != 81 total') && $code === 1;
$report($ok ? 'PASS' : 'SURVIVED', 'split assertion', $ok ? 'refused the overlapping set' : 'exit '.$code.': '.trim(preg_replace('/\s+/', ' | ', substr($out, 0, 300))));
$emsOk();

// ------------------------------------------------------------ guard: the manifest is re-read
$p = $mutant('manifest-truncated', "'rows' => \$rows->map(fn (\$r) => (array) \$r)->all(),", "'rows' => \$rows->take(5)->map(fn (\$r) => (array) \$r)->all(),");
[$out, $code] = $run([$php, $p, $dump, '21', '--apply'], $clone);
$c = $counts($clone);
$ok = str_contains($out, 'manifest on disk does not match') && $code === 1 && $c['unattr'] === $unattributable;
$report($ok ? 'PASS' : 'SURVIVED', 'manifest re-read', $ok ? 'refused before deleting' : 'exit '.$code.', clone unattributable now '.$c['unattr'].': '.trim(preg_replace('/\s+/', ' | ', substr($out, 0, 300))));
$emsOk();

// ---------------------------------------------------------------------- guard: the --apply gate
$p = $mutant('no-apply-gate', 'if (! $apply) {', 'if (false) {');
[$out, $code] = $run([$php, $p, $dump, '21'], $clone);
$c = $counts($clone);
$ok = str_contains($out, 'PRUNED 21 rows') && $c['unattr'] === 0;
$report($ok ? 'PASS' : 'SURVIVED', '--apply gate', $ok ? 'without the gate a bare invocation deletes (then reset)' : 'exit '.$code.', clone unattributable '.$c['unattr'].': '.trim(preg_replace('/\s+/', ' | ', substr($out, 0, 300))));
$reset();
$emsOk();

// ------------------------------------------------------------------ control: the real script
[$out, $code] = $run([$php, $script, $dump, '21', '--apply'], $clone);
$c = $counts($clone);
$ok = $code === 0 && str_contains($out, 'PRUNED 21 rows; 60 attributed rows untouched') && $c['total'] === $attributed && $c['unattr'] === 0;
$report($ok ? 'PASS' : 'SURVIVED', 'CONTROL real prune', $ok ? 'clone at 60 attributed, 0 unattributable' : 'exit '.$code.', clone '.$c['total'].'/'.$c['unattr'].': '.trim(preg_replace('/\s+/', ' | ', substr($out, 0, 400))));

// The audit that reported the note must now be silent about it, on the same clone. The command prints every
// rule on its own line with its count, and a line stays printed at 0 — so the claim to test is the number at
// the end of that line, not whether the sentence appears.
[$out, $code] = $run([$php, 'artisan', 'ems:audit:record-states'], $clone);
$noteCount = preg_match('/printed paper attributable to no enrollment[^\n]*?(\d+)\s*$/mi', $out, $m) ? (int) $m[1] : -1;
$gone = $code === 0 && $noteCount === 0;
$report($ok && $gone ? 'PASS' : 'SURVIVED', 'record-states note gone', $gone
    ? 'the print rule now counts 0 on the pruned clone'
    : 'exit '.$code.', note count read as '.$noteCount.': '.trim(preg_replace('/[^\n]*printed paper[^\n]*/i', '', substr($out, 0, 200))));
$emsOk();

// Re-running the prune must now refuse: the set it was told to find is gone.
[$out, $code] = $run([$php, $script, $dump, '21'], $clone);
$ok = str_contains($out, 'found 0 unattributable rows, expected 21') && $code === 1;
$report($ok ? 'PASS' : 'SURVIVED', 're-run refuses', $ok ? 'a second pass stops at the count guard' : 'exit '.$code.': '.trim(preg_replace('/\s+/', ' | ', substr($out, 0, 300))));

// ------------------------------------------------------------------ guard: the delete is bounded
// The last mutant, and the one that costs the clone its whole print table: widen the DELETE to ignore the
// set and the surviving-count check still balances (81 - 81 = 0), so only "an attributed row was lost"
// can catch it. That is the guard the live run depends on.
$reset();
$p = $mutant('delete-ignores-the-set', "->whereIn('printLogId', \$rows->pluck('printLogId'))->delete();", '->delete();');
[$out, $code] = $run([$php, $p, $dump, '21', '--apply'], $clone);
$c = $counts($clone);
$ok = str_contains($out, 'an attributed row was lost') && str_contains($out, 'restore from') && $code === 1 && $c['total'] === 0;
$lines = preg_grep('/REFUSE|post:|problem/', explode("\n", $out) ?: []);
$report($ok ? 'PASS' : 'SURVIVED', 'bounded delete', $ok
    ? 'whole-table delete reported, refused, exit 1'
    : 'exit '.$code.', clone now '.$c['total'].'/'.$c['unattr'].' >> '.implode(' >> ', array_map('trim', $lines)));
$emsOk();

// Leave the clone in the state the live run is about to produce: pruned.
$reset();
[$out, $code] = $run([$php, $script, $dump, '21', '--apply'], $clone);
$c = $counts($clone);
$report($code === 0 && $c['total'] === $attributed && $c['unattr'] === 0 ? 'PASS' : 'SURVIVED', 'clone left pruned', 'clone at '.$c['total'].'/'.$c['unattr']);
$emsOk();

foreach (glob($root.'/storage/app/scratch/mutant-prune-*.php') ?: [] as $f) {
    unlink($f);
}

printf("\n%s\n", $fails === 0
    ? 'all five guards failed for their mutant and the control passed — the real script is proved by what it refuses'
    : "{$fails} mutant(s) SURVIVED — do not run the prune on live ems until each is explained");
exit($fails === 0 ? 0 : 1);
