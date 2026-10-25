<?php

/**
 * Prove the queue-reads mechanism can fail.
 *
 * `lane70-queue-reads.json` says which UNBOUND rows a human has already opened. A record that cannot be invalidated
 * is worse than no record: it turns a saved verdict into a permanent clearing of whatever pointer happens to share
 * the file. So each leg here breaks the agreement between the record and the queue and requires the tool to say so.
 *
 *   php storage/app/scratch/mutate-lane70-queue-reads.php
 */
$root = dirname(__DIR__, 3);
$scratch = $root.'/storage/app/scratch';
$php = escapeshellarg(PHP_BINARY);
$tool = $scratch.'/lane70-anchor-cites.php';
$reads = $scratch.'/lane70-queue-reads.json';
$tmp = $scratch.'/tmp-lane70-reads';
@mkdir($tmp);
$fails = 0;

$run = static function (string $args) use ($php, $tool, $root): string {
    // cmd.exe gives shell_exec no exit status to read (`$?` is not its variable), and the tool already exits 1 for
    // MOVED, so every leg here asserts on the message. That is the only thing that tells the refusals apart.
    return (string) shell_exec('cd '.escapeshellarg($root).' && '.$php.' '.escapeshellarg($tool).$args.' 2>&1');
};
$check = static function (string $name, bool $ok, string $detail = '') use (&$fails): void {
    printf("  %s %s%s\n", $ok ? 'PASS' : 'BAD ', $name, $detail === '' ? '' : ' — '.$detail);
    if (! $ok) {
        $fails++;
    }
};

if (! is_file($reads)) {
    exit("REFUSED: no reads file — run lane70-make-reads.php first, or this harness proves nothing.\n");
}
$j = json_decode((string) file_get_contents($reads), true);
if (! is_array($j) || ! isset($j['reads']) || count($j['reads']) < 10) {
    exit("REFUSED: the reads file holds fewer than ten records, so a planted defect may not be visible.\n");
}

// 1 — the control: records are honoured and none is stale.
$base = $run('');
if (! preg_match('/queue reads on record: (\d+) of (\d+) UNBOUND.*?(\d+) record\(s\) match no current row/u', $base, $m)) {
    exit("REFUSED: the control run printed no reads line, so the harness cannot compare anything to it.\n");
}
$check('control', (int) $m[1] > 0 && (int) $m[3] === 0, "recorded {$m[1]} rows of {$m[2]}, stale {$m[3]}");
$check('the coverage line adds up to the queue', (int) $m[1] === (int) $m[2], "recorded {$m[1]}, queue {$m[2]}");

// Which keys carry two rows is read out of the control's own line, never recomputed here: the tool owns the definition
// of a row, and a harness that re-derived it would be the second definition this repository keeps having to remove.
$dupKeys = [];
if (preg_match('/carry more than one row — (.*)$/mu', $base, $dm) === 1) {
    $dupKeys = array_map('trim', explode(',', $dm[1]));
}
$rowsOf = static fn (string $k): int => in_array($k, $dupKeys, true) ? 2 : 1;
$covered = static function (string $out): ?int {
    return preg_match('/queue reads on record: (\d+) of/u', $out, $mm) === 1 ? (int) $mm[1] : null;
};

// 2 — a record for a pointer that is NOT in the queue must be reported, never silently dropped: this is what a pass
// that re-points a citation does to the saved verdict, and the honest answer is "read it again".
$keys = array_keys($j['reads']);
$ghost = $tmp.'/ghost.json';
$k2 = json_decode((string) json_encode($j), true);
$first = array_shift($k2['reads']);
$k2['reads'][$first === null ? 'x' : 'app/Http/Controllers/NowhereAtAll.php:999999-999999#deadbeef'] = $first;
file_put_contents($ghost, json_encode($k2));
$out = $run(' --reads='.escapeshellarg($ghost));
$check('a record for no current row is STALE', str_contains($out, 'STALE')
    && str_contains($out, 'NowhereAtAll.php:999999'), '');
$check('the removed record lowers coverage by its own row count, not by one',
    $covered($out) === (int) $m[1] - $rowsOf((string) array_key_first($j['reads'])),
    'now '.$covered($out).' recorded, expected '.((int) $m[1] - $rowsOf((string) array_key_first($j['reads']))));

// 2b — UNITS. Coverage is counted in ROWS, so dropping the record whose sentence the document writes twice has to
// lower the covered count by TWO while a single-row drop lowers it by one. The old harness asserted "-1" for every
// removal, which is exactly what let the tool print "104 of 107" — records over rows — while every row had been read:
// a reader told three rows were still open goes and opens them again, or worse, believes the queue is a claim.
$dupKey = $dupKeys === [] ? null : $dupKeys[0];
if ($dupKey === null) {
    $check('a two-row key is available to plant against', false,
        'the control printed no key carrying more than one row, so the units cannot be proved this run');
} else {
    $k3 = json_decode((string) json_encode($j), true);
    unset($k3['reads'][$dupKey]);
    file_put_contents($tmp.'/dropped-dup.json', json_encode($k3));
    $out = $run(' --reads='.escapeshellarg($tmp.'/dropped-dup.json'));
    $check('dropping a record that answers two rows lowers coverage by two',
        $covered($out) === (int) $m[1] - 2, 'now '.$covered($out).' recorded after dropping '.$dupKey);
    $single = null;
    foreach (array_keys($j['reads']) as $k) {
        if (! in_array($k, $dupKeys, true)) {
            $single = $k;
            break;
        }
    }
    $k4 = json_decode((string) json_encode($j), true);
    unset($k4['reads'][$single]);
    file_put_contents($tmp.'/dropped-single.json', json_encode($k4));
    $out = $run(' --reads='.escapeshellarg($tmp.'/dropped-single.json'));
    $check('dropping a record that answers one row lowers coverage by one',
        $single !== null && $covered($out) === (int) $m[1] - 1, 'now '.$covered($out).' recorded after dropping '.$single);
}

// 3 — a reads file that names nothing must not turn the queue into a clean document.
file_put_contents($tmp.'/empty.json', json_encode(['_date' => 'x', 'reads' => new stdClass]));
$out = $run(' --reads='.escapeshellarg($tmp.'/empty.json'));
$check('an empty reads file leaves every row unread', str_contains($out, 'queue reads on record: 0 of '.$m[2]), '');

// 4 — the collector must agree with the tally. Delete its calls from a copy of the tool and the run has to refuse,
// because a records file keyed by a queue the tool mis-counted would clear rows nobody read.
$srcTool = (array) file($tool);
$needle = '$markUnbound($own';
$muted = 0;
foreach ($srcTool as $i => $l) {
    if (! str_contains((string) $l, $needle) || str_contains((string) $l, 'static function')) {
        continue;
    }
    // Every site is muted, not one: which branch reaches UNBOUND depends on today's document, so a leg aimed at a
    // site that happens to carry no rows would mutate nothing and report the control's clean answer as a failure to
    // complain — the lane 44 sleeping mutant in a new costume.
    $indent = (string) preg_match('/^(\s*)/u', (string) $l, $gm) ? $gm[1] : '';
    $srcTool[$i] = $indent."/* mutant: this site no longer registers its row */;\n";
    $muted++;
}
if ($muted < 4) {
    exit("REFUSED: only $muted collector site(s) were found in the tool, so this leg could mutate nothing and report the control's answer as a failure.\n");
}
// The copy must sit in `storage/app/scratch` beside the tool: the tool computes its project root as
// `dirname(__DIR__, 3)`, so a copy one directory deeper cannot find docx-cite-attribution.php and dies on a
// require — which a leg that only looks for REFUSED would read as "the mutant behaved".
$mutTool = $scratch.'/tmp-lane70-reads-mutant.php';
file_put_contents($mutTool, implode('', $srcTool));
$out = (string) shell_exec('cd '.escapeshellarg($root).' && '.$php.' '.escapeshellarg($mutTool).' 2>&1');
@unlink($mutTool);
$check('a collector that sees fewer rows than it tallies refuses',
    str_contains($out, 'REFUSED') && str_contains($out, 'the collector saw'), '');

// 5 — and the untouched copy must not refuse, which is what makes leg 4 a mutation and not a broken reader.
$sameTool = $scratch.'/tmp-lane70-reads-same.php';
file_put_contents($sameTool, implode('', (array) file($tool)));
$out = (string) shell_exec('cd '.escapeshellarg($root).' && '.$php.' '.escapeshellarg($sameTool).' 2>&1');
@unlink($sameTool);
$check('a byte-identical copy answers like the control', ! str_contains($out, 'REFUSED')
    && str_contains($out, 'queue reads on record: '.$m[1].' of'), '');

// 6 — plant the UNITS defect, not a random one: put the record count back where the row count belongs. This is the
// exact shape the tool had when it printed "104 of 107" for a queue in which every row had been opened, and a harness
// whose coverage legs all pass without it cannot tell a read from a record. The copy must sit beside the tool, because
// the tool resolves the project root as dirname(__DIR__, 3).
$srcBytes = (string) file_get_contents($tool);
$unitsRevert = [
    "    \$coveredRows, \$counts['UNBOUND'], count(\$stale));" => "    count(\$matched), \$counts['UNBOUND'], count(\$stale));",
];
$unitsTool = $scratch.'/tmp-lane70-reads-units.php';
$mutBytes = $srcBytes;
$applied = 0;
foreach ($unitsRevert as $from => $to) {
    $at = substr_count($mutBytes, $from);
    if ($at !== 1) {
        exit("REFUSED: the units revert matched $at copy(ies) of its anchor in the tool, so this leg would mutate nothing and report the control's answer as a failure.\n");
    }
    $mutBytes = str_replace($from, $to, $mutBytes);
    $applied++;
}
if ($applied < 1 || $mutBytes === $srcBytes) {
    exit("REFUSED: the planted units defect changed no bytes, so the leg below would measure the unfixed tool.\n");
}
file_put_contents($unitsTool, $mutBytes);
$outUnits = (string) shell_exec('cd '.escapeshellarg($root).' && '.$php.' '.escapeshellarg($unitsTool).' --reads='.escapeshellarg($reads).' 2>&1');
@unlink($unitsTool);
$bad = preg_match('/queue reads on record: (\d+) of (\d+) UNBOUND/', $outUnits, $bm) === 1
    ? [(int) $bm[1], (int) $bm[2]] : null;
$check('the planted record-count numerator reports rows that were read as still unread',
    $bad !== null && $bad[0] < $bad[1], $bad === null ? 'the mutant printed no reads line' : "mutant says {$bad[0]} of {$bad[1]}, the tool says {$m[1]} of {$m[2]}");
$check('and the fixed tool reports the queue fully covered', (int) $m[1] === (int) $m[2], "{$m[1]} of {$m[2]}");

array_map('unlink', glob($tmp.'/*'));
@rmdir($tmp);
printf("\n%d leg(s) failed.\n", $fails);
exit($fails > 0 ? 1 : 0);
