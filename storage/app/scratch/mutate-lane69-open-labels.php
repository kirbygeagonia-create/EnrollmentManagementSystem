<?php

/**
 * Prove `lane69-open-inventory.php`'s column rule can fail.
 *
 * The inventory now decides whether an open word is a *verdict* by reading the column heading the cell sits under,
 * so the two rows it used to report as owed work — a walkthrough's `Open the queue` and §29.4's `Open decisions`
 * family label — are printed as labels instead. Every leg is a claim about that discrimination, planted in a COPY
 * through `--dump=` (a flag this tool honours only since this batch, which is why `dump-is-read` exists at all):
 *
 *   control              0 OPEN, 2 LABELS, 0 UNRESOLVED
 *   planted-verdict      `Waits on …` under a column headed Verdict must be OPEN, not a label
 *   planted-action       the same words under a column headed Action must be a LABEL — the rule reads the heading, not the position
 *   planted-misaligned   a row with a different cell count than its header cannot be resolved: UNRESOLVED, never silently cleared
 *   dump-is-read         a copy with one more verdict row must answer MORE than the control, proving --dump= reaches the walk
 *   byte-identical       a copy that changes nothing must change no verdict
 *   short-dump / missing the refusals, told apart by their own messages
 *   control-again        the real document is untouched
 *
 *   php storage/app/scratch/mutate-lane69-open-labels.php
 */
$root = dirname(__DIR__, 3);
$tmp = $root.'/storage/app/scratch/mut-lane69-labels';
if (! is_dir($tmp)) {
    mkdir($tmp, 0777, true);
}
$real = $root.'/storage/app/scratch/docx-flat.txt';
$base = (string) file_get_contents($real);
if (strlen($base) < 100000) {
    echo "REFUSED: the dump is not the document.\n";
    exit(1);
}

$runs = 0;
$fails = 0;

// OPEN and LABELS always print their heading and their count, so a missing figure there means the tool answered
// nothing and is reported as null, which fails the leg. UNRESOLVED only prints when it holds rows, so absent
// legitimately means zero.
$counts = function (string $out): array {
    $c = [];
    foreach (['OPEN', 'LABELS'] as $k) {
        $c[$k] = preg_match('/=== '.preg_quote($k, '/').'[^\n]*\n(?:.*\n)*?  count: (\d+)/u', $out, $m) === 1
            ? (int) $m[1]
            : null;
    }
    $c['UNRESOLVED'] = preg_match('/=== UNRESOLVED[^\n]*\n(?:.*\n)*?  count: (\d+)/u', $out, $m) === 1
        ? (int) $m[1]
        : 0;

    return $c;
};

$report = function (string $name, bool $ok, string $detail) use (&$fails, &$runs): void {
    $runs++;
    if (! $ok) {
        $fails++;
    }
    printf("%s   %-22s %s\n", $ok ? 'PASS' : 'FAIL', $name, $detail);
};

$run = function (array $args) use ($root): array {
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/storage/app/scratch/lane69-open-inventory.php');
    foreach ($args as $a) {
        $cmd .= ' '.escapeshellarg($a);
    }
    exec($cmd.' 2>&1', $out, $rc);

    return [$rc, implode("\n", $out)];
};

[$rc0, $out0] = $run([]);
$c0 = $counts($out0);
$report('control', $rc0 === 0 && $c0['OPEN'] === 0 && $c0['LABELS'] === 2 && $c0['UNRESOLVED'] === 0,
    sprintf('OPEN %s, LABELS %s, UNRESOLVED %s (exit %d)', var_export($c0['OPEN'], true), var_export($c0['LABELS'], true), var_export($c0['UNRESOLVED'], true), $rc0));

// LEGS on a copy. Each synthetic table is two consecutive ROW lines: the first line of a run is its header.
$cases = [
    ['planted-verdict', "ROW 99001: Finding | Verdict | Who must act\nROW 99002: Z-1 | Waits on the Registrar for the window | Registrar\n", ['OPEN' => 1, 'LABELS' => 2]],
    ['planted-action', "ROW 99001: # | Desk | Action | Who must act\nROW 99002: 1 | Registrar | Waits on the Registrar for the window | Registrar\n", ['OPEN' => 0, 'LABELS' => 3]],
    ['planted-misaligned', "ROW 99001: Finding | Verdict | Who must act\nROW 99002: Z-1 | Waits on the Registrar for the window\n", ['OPEN' => 0, 'LABELS' => 2, 'UNRESOLVED' => 1]],
];
foreach ($cases as [$name, $plant, $want]) {
    $f = $tmp.'/'.$name.'.txt';
    file_put_contents($f, $base."\n".'P 99000: harness table'."\n".$plant);
    [$rc, $out] = $run(['--dump='.$f]);
    $c = $counts($out);
    $ok = $rc === 0;
    foreach ($want as $k => $v) {
        $ok = $ok && $c[$k] === $v;
    }
    $report($name, $ok, $rc !== 0 ? 'exit '.$rc : sprintf('OPEN %s, LABELS %s, UNRESOLVED %s (wanted %s)',
        var_export($c['OPEN'], true), var_export($c['LABELS'], true), var_export($c['UNRESOLVED'], true),
        implode('/', array_map('strval', $want))));
}

// LEG: --dump= is read, not merely accepted.
$f1 = $tmp.'/copy-with-one-more.txt';
file_put_contents($f1, $base."\n".'P 99999: harness'."\nROW 99998: Finding | Verdict\nROW 99999: Z-9 | Provisional until the board names the programs\n");
[$rc1, $out1] = $run(['--dump='.$f1]);
$c1 = $counts($out1);
$report('dump-is-read', $rc1 === 0 && $c1['OPEN'] === $c0['OPEN'] + 1 && str_contains($out1, 'Z-9'),
    $c1['OPEN'] === null ? 'no tally' : sprintf('OPEN %d on the copy against %d on the real dump', $c1['OPEN'], $c0['OPEN']));

// LEG: a byte-identical copy changes no verdict. Compare BYTES — a one-digit count and a `C-5`→`C-6` swap are the
// same length and would pass a filesize() check without changing anything.
$f2 = $tmp.'/identical.txt';
file_put_contents($f2, $base);
clearstatcache(true, $f2);
[$rc2, $out2] = $run(['--dump='.$f2]);
$report('byte-identical-copy', $rc2 === 0 && $out2 === $out0
    && hash_file('sha256', $f2) === hash_file('sha256', $real),
    $out2 === $out0 ? 'same verdicts as the control' : 'a byte-identical copy answered differently');

// LEG: a dump too short to be the document must refuse, not report an empty register.
$f3 = $tmp.'/short.txt';
file_put_contents($f3, "ROW 1: a | b\nROW 2: c | d\n");
[$rc3, $out3] = $run(['--dump='.$f3]);
$report('short-dump-refuses', $rc3 === 1 && str_contains($out3, 'REFUSED'), strtok($out3, "\n") ?: 'exit '.$rc3);

// LEG: a missing dump refuses, and names the path it was handed so a reader is not sent to the wrong tool.
[$rc4, $out4] = $run(['--dump='.$tmp.'/does-not-exist.txt']);
$report('missing-dump-refuses', $rc4 === 1 && str_contains($out4, 'REFUSED') && str_contains($out4, 'does-not-exist'),
    strtok($out4, "\n") ?: 'exit '.$rc4);

[$rc5, $out5] = $run([]);
$report('control-again', $rc5 === 0 && $out5 === $out0, $out5 === $out0 ? 'the real document answers as it did before' : 'the control moved');

array_map('unlink', glob($tmp.'/*') ?: []);
@rmdir($tmp);

printf("\n%d legs, %d failure(s).\n", $runs, $fails);
if ($fails > 0) {
    echo "A leg that cannot fail is not an instrument.\n";
    exit(1);
}
