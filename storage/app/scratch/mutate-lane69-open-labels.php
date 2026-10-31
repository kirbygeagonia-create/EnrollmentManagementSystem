<?php

/**
 * Prove `lane69-open-inventory.php`'s discrimination can fail, and that it agrees with the other tool that reads
 * verdicts.
 *
 * The previous version hard-coded "0 OPEN, 2 LABELS, 0 UNRESOLVED" as its control. That was true the day it was
 * written and became a failing control the moment §28.3 gained open rows — a verifier whose expectation is a frozen
 * reading is indistinguishable from a broken tool. So the control asserts a RELATION instead: the ids this inventory
 * reports as OPEN must equal the ids `lane61-open-claims.php` names from the register cells, and every planted leg
 * is judged as a DELTA against the control measured moments earlier.
 *
 *   control                 the two readers name the same open ids, and no row is UNRESOLVED
 *   planted-verdict         `Waits on …` under a Verdict column must be OPEN (+1)
 *   planted-action          the same words under an Action column must be a LABEL, never OPEN
 *   planted-misaligned      a synthetic row shorter than its header, which no archive row matches: UNRESOLVED
 *   planted-leading-verb    a cell LEADING `Open …` whose own sentence later says "answered" must be OPEN — the
 *                           shape that hid §28.3 G-41 while lane 61 named it
 *   planted-closed-lead     a cell leading `Closed …` that later says "Open" must stay out of OPEN
 *   dump-is-read            one more verdict row must answer MORE, proving --dump= reaches the walk
 *   byte-identical          a copy that changes nothing changes no verdict (bytes and hashes, not filesize)
 *   enum-tiebreak-off       --no-archive must surface the rows whose empty cells the flattening lost
 *   unknown-flag-refused    a flag the tool does not read is refused by name
 *   short-dump / missing    the refusals, told apart by their own messages
 *   control-again           the real document is untouched
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

$openIds = function (string $out): array {
    $section = preg_match('/=== OPEN[^\n]*\n(.*?)\n  count:/us', $out, $m) === 1 ? $m[1] : '';
    // The id is read from each row's LEADING CELL, never from the printed row as text. A §28.3 row's own prose
    // cross-references its neighbours — G-52's cell says "the contrast with G-51 is the whole lesson" — and a
    // collector that sweeps the whole line harvests that mention as a claimed open id, which is lane 61's rule
    // ("an id is judged only inside the sentence holding the open-words") violated inside this harness.
    $ids = [];
    foreach (explode("\n", $section) as $line) {
        if (preg_match('/^\s+ROW \d+ \(column .*\):\s*([A-Z]-\d+)\b/u', $line, $lm) === 1) {
            $ids[] = $lm[1];
        }
    }
    $ids = array_values(array_unique($ids));
    sort($ids);

    return $ids;
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

// The other reader of verdict cells. It indexes the register's own verdict column, so agreement between the two is
// a claim about the document rather than about either parser.
$runLane61 = static function () use ($root): array {
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/storage/app/scratch/lane61-open-claims.php').' 2>&1', $o);
    if (preg_match('/leading an open verb: ([^\n]*)/u', implode("\n", $o), $m) !== 1) {
        return [];
    }
    $ids = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
    sort($ids);

    return $ids;
};

[$rc0, $out0] = $run([]);
$c0 = $counts($out0);
$ids0 = $openIds($out0);
$ids61 = $runLane61();
$report('control', $rc0 === 0 && $c0['UNRESOLVED'] === 0 && $ids61 !== [] && $ids0 === $ids61,
    sprintf('inventory [%s] vs lane61 [%s], UNRESOLVED %s (exit %d)',
        implode(',', $ids0), implode(',', $ids61), var_export($c0['UNRESOLVED'], true), $rc0));

// LEGS on a copy. Each synthetic table is a P marker plus two consecutive ROW lines: the first row of a run is its
// header. Ids are Z-n so they cannot collide with a register id the control already counted, and the rows are absent
// from document.xml, which is what makes the tiebreak legs mean what they say.
$cases = [
    ['planted-verdict', "ROW 99001: Finding | Verdict | Who must act\nROW 99002: Z-1 | Waits on the Registrar for the window | Registrar\n", ['OPEN' => 1, 'LABELS' => 0, 'UNRESOLVED' => 0]],
    ['planted-action', "ROW 99001: # | Desk | Action | Who must act\nROW 99002: 1 | Registrar | Waits on the Registrar for the window | Registrar\n", ['OPEN' => 0, 'LABELS' => 1, 'UNRESOLVED' => 0]],
    ['planted-misaligned', "ROW 99001: Finding | Verdict | Who must act\nROW 99002: Z-907 | Waits on the Registrar for the window\n", ['OPEN' => 0, 'LABELS' => 0, 'UNRESOLVED' => 1]],
    ['planted-leading-verb', "ROW 99001: Finding | Verdict | Who must act\nROW 99002: Z-2 | Open 2026-10-29. Whether the check belongs here is answered nowhere in the repository. | Nobody\n", ['OPEN' => 1, 'LABELS' => 0, 'UNRESOLVED' => 0]],
    ['planted-closed-lead', "ROW 99001: Finding | Verdict | Who must act\nROW 99002: Z-3 | Closed 2026-10-29. It began life Open and the ruling was recorded. | Nobody\n", ['OPEN' => 0, 'LABELS' => 0, 'UNRESOLVED' => 0]],
];
foreach ($cases as [$name, $plant, $want]) {
    $f = $tmp.'/'.$name.'.txt';
    file_put_contents($f, $base."\n".'P 99000: harness table'."\n".$plant);
    [$rc, $out] = $run(['--dump='.$f]);
    $c = $counts($out);
    $ok = $rc === 0
        && $c['OPEN'] === $c0['OPEN'] + $want['OPEN']
        && $c['LABELS'] === $c0['LABELS'] + $want['LABELS']
        && $c['UNRESOLVED'] === $c0['UNRESOLVED'] + $want['UNRESOLVED'];
    $report($name, $ok, $rc !== 0 ? 'exit '.$rc : sprintf('OPEN %s (delta %d, want +%d), LABELS %s (delta %d, want +%d), UNRESOLVED %d (want +%d)',
        var_export($c['OPEN'], true), (int) $c['OPEN'] - (int) $c0['OPEN'], $want['OPEN'],
        var_export($c['LABELS'], true), (int) $c['LABELS'] - (int) $c0['LABELS'], $want['LABELS'],
        $c['UNRESOLVED'] - $c0['UNRESOLVED'], $want['UNRESOLVED']));
    unlink($f);
}

// LEG: a prose cross-reference inside an OPEN row is NOT a second open item. This batch's G-52 row says "the
// contrast with G-51 is the whole lesson", and the old collector swept the printed row with `/\b([PXCESG]-\d+)\b/u`,
// so the control reported the inventory claiming G-51 while lane 61, which reads the leading cell, did not. That is
// the same disagreement this harness exists to catch — but from the harness's own parser.
//
// The mention is planted as a G-id on purpose, and the first draft of this leg used a second Z-id: the old class was
// `[PXCESG]`, so a Z-62 could never have been harvested by it and the leg would have passed on the broken parser —
// a leg that cannot fail is decoration. `G-907` names no row in the register, so its appearance can only be the prose.
$f61 = $tmp.'/prose-mention.txt';
file_put_contents($f61, $base."\n".'P 99100: harness table'."\nROW 99101: Finding | Verdict | Who must act\n"
    ."ROW 99102: Z-61 | Open 2026-10-30. The contrast with G-907 is the whole lesson, and G-907 is a closed row. | Nobody\n");
[$rc61, $out61] = $run(['--dump='.$f61]);
$ids61 = $openIds($out61);
$report('prose-mention-not-an-id', $rc61 === 0 && in_array('Z-61', $ids61, true) && ! in_array('G-907', $ids61, true),
    'ids on the copy: '.implode(',', $ids61).' (want Z-61 present, G-907 absent — an id named only in prose is not a finding)');
unlink($f61);

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

// LEG: the archive tiebreak must be observable. Asked to judge the dump alone, the rows whose empty cells the
// flattening dropped have to come back as UNRESOLVED — a leg that passed either way would be decoration.
[$rc6, $out6] = $run(['--no-archive']);
$c6 = $counts($out6);
$report('enum-tiebreak-off', $rc6 === 0 && $c6['UNRESOLVED'] > $c0['UNRESOLVED'] && str_contains($out6, 'NOT CONSULTED'),
    sprintf('--no-archive answers UNRESOLVED %d against the archive-bound %d', $c6['UNRESOLVED'], $c0['UNRESOLVED']));

// LEG: a flag the tool does not read is refused by name, not accepted and ignored.
[$rc7, $out7] = $run(['--archve=typo']);
$report('unknown-flag-refused', $rc7 === 1 && str_contains($out7, 'unknown argument'),
    strtok($out7, "\n") ?: 'exit '.$rc7);

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
