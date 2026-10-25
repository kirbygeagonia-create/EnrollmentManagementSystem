<?php

/**
 * Lane 70: the reading sheet for the sweep's own blind spots.
 *
 * The bounds sweep (`sweep-docx-citations.php`) attributes a bare `:NN` to the file named most recently before it
 * on the same dump line, within 160 characters, and prints anything further as UNATTRIBUTED with the dump line it
 * sits on. This tool reads THAT list — token and line together — and prints, for each entry, the whole sentence
 * owning it plus every file named anywhere on the line. It does not re-derive which pointers are blind: an
 * earlier version searched the dump for each blind *number* and so reported `:44-62`, `:441` and every other line
 * carrying those digits, printing ~11 rows for a token the sweep flagged once. Two definitions of one word is the
 * defect lane 69 spent a batch removing for year levels, and a reading aid that over-reports sends a reader
 * chasing pointers that were never unattributed.
 *
 *   php storage/app/scratch/lane70-blind-sheet.php
 */
$root = dirname(__DIR__, 3);
$php = PHP_BINARY;
$sweep = $root.'/storage/app/scratch/sweep-docx-citations.php';
$flat = $root.'/storage/app/scratch/docx-flat.txt';

exec(escapeshellarg($php).' '.escapeshellarg($sweep).' 2>&1', $out, $rc);

$tally = null;
$hits = [];
foreach ($out as $l) {
    if (preg_match('/(\d+) blind spot\(s\)/', $l, $m) === 1) {
        $tally = (int) $m[1];
    }
    if (preg_match('/UNATTRIBUTED (:\d+(?:-\d+)?) near "(.*)" on dump line (\d+)/', $l, $m) === 1) {
        $hits[] = ['token' => $m[1], 'context' => $m[2], 'line' => (int) $m[3]];
    }
}

if ($tally === null) {
    echo "REFUSED: the sweep printed no blind-spot tally, so nothing here was read.\n";
    exit(1);
}
if (count($hits) !== $tally) {
    printf("REFUSED: the sweep tallies %d blind spot(s) but %d could be parsed — a dropped row is a lost pointer.\n",
        $tally, count($hits));
    exit(1);
}
if (! is_file($flat)) {
    echo "REFUSED: no flat dump to read. Run dump-docx-flat.php first.\n";
    exit(1);
}

$lines = explode("\n", (string) file_get_contents($flat));
$fileRe = '/[A-Za-z0-9_.\-\/]+\.(?:php|jsx|js|md|json|neon|xml)/';

// The sweep's context is a byte window out of the dump with newlines and tabs folded to spaces; fold the same way
// so the two agree on where the token actually sits.
$fold = static fn (string $s): string => preg_replace('/ +/', ' ', strtr($s, ["\n" => ' ', "\r" => ' ', "\t" => ' ']));

usort($hits, static fn (array $a, array $b): int => [$a['line'], $a['token']] <=> [$b['line'], $b['token']]);

// Most blind pointers are a range the document ALSO cites in full elsewhere — the cell that names its file and the
// cell that only carries the number are usually the same sentence in two places. Indexing the named citations gives
// a candidate owner read out of the document itself; it stays a candidate until the line is opened, and a range
// more than one file claims is reported as such rather than guessed at.
$twin = [];
foreach ($lines as $raw) {
    if (preg_match_all('/([A-Za-z0-9_.\-\/]+\.(?:php|jsx|js|md)):(\d+(?:-\d+)?)/', $raw, $cm) === 0) {
        continue;
    }
    foreach ($cm[2] as $k => $rng) {
        $twin[$rng][basename($cm[1][$k])] = ($twin[$rng][basename($cm[1][$k])] ?? 0) + 1;
    }
}

printf("%d blind pointer(s) over %d dump line(s) — these are the sweep's own rows, read in document order.\n",
    count($hits), count(array_unique(array_column($hits, 'line'))));

foreach ($hits as $h) {
    $raw = $lines[$h['line'] - 1] ?? '';
    $folded = $fold($raw);
    $at = strpos($folded, $h['context']);
    if ($at === false) {
        $at = strpos($folded, $h['token']);
    }
    $start = $at === false ? 0 : (int) strrpos(substr($folded, 0, $at), '.');
    $sent = trim(mb_substr($folded, $start, 300));
    preg_match_all($fileRe, $raw, $fm);
    $files = array_values(array_unique($fm[0]));
    $rng = ltrim($h['token'], ':');
    $elsewhere = $twin[$rng] ?? [];
    arsort($elsewhere);
    $owner = $elsewhere === []
        ? 'no other copy of this range is named anywhere'
        : implode(', ', array_map(static fn ($f, $n) => "$f x$n", array_keys($elsewhere), $elsewhere));
    printf("\n%-8s dump line %-5d  files on the line: %s\n            cited elsewhere as: %s\n            %s\n",
        $h['token'], $h['line'], $files === [] ? 'NONE' : implode(', ', array_slice($files, 0, 4)), $owner, $sent);
}

echo "\nEach row is a pointer the sweep could not attribute, not a pointer known to be wrong. Name its file in the\n"
    ."sentence — verified against the line it claims — and the existing bounds sweep judges it from then on.\n";
