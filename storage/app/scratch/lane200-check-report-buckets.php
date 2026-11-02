<?php

/**
 * Does `FINAL-REPORT-ADS.md`'s two id lists still equal what the ADS census answers?
 *
 * The report states the same partition twice in prose — a Built list and an Implemented list — while
 * `lane169-read-ads-status.php` computes it from the traceability matrix's own lead words. Every batch that
 * closes a demonstration moves one row from one bucket to the other, and the report's rows do not move
 * themselves: measured 2026-11-02, the hand-kept Built list was silently omitting four ids the census counted
 * as built. This is lane 64's membership defect for a table that has no table.
 *
 * Membership is read from the list BEFORE the row's em-dash, never from the whole row: the trailing clause
 * names the ids that LEFT the bucket ("P01, P06 and P16 left this list on 2026-11-02"), and harvesting those
 * would report a correct document as the defect. That misread happened in the first draft of this checker.
 *
 * Refusals, each named: `lane169` printing no built/implemented bucket, a marker phrase absent from the
 * report, an id list that is empty, an unknown flag, and `--report=` pointing at a file that is not there.
 *
 * Usage: php storage/app/scratch/lane200-check-report-buckets.php [--report=<path>]
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$reportPath = $root.'/Documentation/FINAL-REPORT-ADS.md';

foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if (preg_match('/^--report=(.*)$/', $arg, $m)) {
        $reportPath = $m[1];
    } else {
        fwrite(STDERR, 'REFUSED: this tool does not understand '.escapeshellarg($arg).".\n");
        exit(1);
    }
}

if (! is_file($reportPath)) {
    fwrite(STDERR, "REFUSED: --report names '{$reportPath}', which is not a file.\n");
    exit(1);
}

$censusOut = (string) shell_exec('"'.$php.'" storage/app/scratch/lane169-read-ads-status.php 2>&1');
$bucket = [];
foreach (explode("\n", $censusOut) as $line) {
    if (preg_match('/^\s+(built|implemented)\s+(\d+)\s+(.*)$/u', $line, $m)) {
        preg_match_all('/P\d{1,2}\b/', $m[3], $ids);
        $found = array_values(array_unique($ids[0]));
        sort($found);
        if (count($found) !== (int) $m[2]) {
            fwrite(STDERR, "REFUSED: the census says {$m[2]} rows in the `{$m[1]}` bucket but printed "
                .count($found)." id(s) on that line — the line was read wrong, so no verdict below would mean anything.\n");
            exit(1);
        }
        $bucket[$m[1]] = $found;
    }
}
foreach (['built', 'implemented'] as $want) {
    if (! isset($bucket[$want])) {
        fwrite(STDERR, "REFUSED: lane169 printed no `{$want}` bucket, so the ground truth is missing rather than empty.\n");
        exit(1);
    }
}

$doc = (string) file_get_contents($reportPath);

/** Membership is the row's SECOND cell and nothing else. An earlier draft cut at the first em-dash, which read
 *  the Built row as eight ids because a description inside that cell carries a dash beside a capture name. */
$readRow = static function (string $marker) use ($doc, $reportPath): array {
    $at = strpos($doc, $marker);
    if ($at === false) {
        fwrite(STDERR, "REFUSED: the marker '{$marker}' is not in {$reportPath} — the row moved, so this checker is stale, not the report.\n");
        exit(1);
    }
    $end = strpos($doc, "\n", $at);
    $row = substr($doc, $at, ($end === false ? strlen($doc) : $end) - $at);
    $cells = array_map('trim', explode('|', $row));
    $cells = array_values(array_filter($cells, static fn (string $c): bool => $c !== ''));
    if (count($cells) !== 2) {
        fwrite(STDERR, "REFUSED: the row at '{$marker}' has ".count($cells)." cell(s), expected 2 — the table's shape moved, and a membership read off the wrong cell is not a measurement.\n");
        exit(1);
    }
    preg_match_all('/P\d{1,2}\b/', $cells[1], $m);
    $ids = array_values(array_unique($m[0]));
    sort($ids);
    if ($ids === []) {
        fwrite(STDERR, "REFUSED: the cell at '{$marker}' names no ids, which is a reader's failure, not an empty bucket.\n");
        exit(1);
    }

    return $ids;
};

$builtDoc = $readRow('Built (the census');
$implDoc = $readRow('Implemented, lead word');

$bad = 0;
foreach ([
    'built' => [$bucket['built'], $builtDoc],
    'implemented' => [$bucket['implemented'], $implDoc],
] as $name => [$census, $written]) {
    if ($census === $written) {
        printf("%-12s AGREES — %d id(s): %s\n", $name, count($census), implode(', ', $census));

        continue;
    }
    $bad++;
    printf("%-12s DISAGREE\n   census: %s\n   report: %s\n   missing from the report: %s   extra in the report: %s\n",
        $name, implode(', ', $census), implode(', ', $written),
        implode(', ', array_diff($census, $written)) ?: 'none',
        implode(', ', array_diff($written, $census)) ?: 'none');
}

if ($bad > 0) {
    fwrite(STDERR, "{$bad} bucket(s) in the report do not equal what the census answers. Re-take them from "
        ."`storage/app/scratch/lane169-read-ads-status.php`; do not type ids into either row by hand.\n");
    exit(1);
}

echo "VERDICT: both id lists equal the buckets the census computes from the matrix's own lead words.\n";
exit(0);
