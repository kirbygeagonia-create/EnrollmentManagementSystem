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
 *   php storage/app/scratch/lane70-blind-sheet.php --dump=<copy>   # read that dump (and let the sweep read it too)
 *   php storage/app/scratch/lane70-blind-sheet.php --sweep=<copy>  # read that sweep's output (the harness's stub)
 */
$root = dirname(__DIR__, 3);
$php = PHP_BINARY;
$sweep = $root.'/storage/app/scratch/sweep-docx-citations.php';
$flat = $root.'/storage/app/scratch/docx-flat.txt';

// Honouring --dump= is what lets a mutant harness plant a defect in a copy: a flag that is accepted but not read
// turns a planted failure into a clean-looking run, which is exactly how lane 41's first harness lied. --sweep=
// exists for the same reason one level up: the refusals below fire when the sweep's OWN output changes shape (a
// row with no dump line, a tally with no rows, a line number that names no such token), and nothing but a stub
// can hand this tool that answer. Both flags are forwarded, so the pair is always read together.
$dumpFlag = '';
foreach ($argv as $a) {
    if (preg_match('/^--dump=(.+)$/', $a, $m) === 1) {
        $flat = $m[1];
        $dumpFlag = ' --dump='.escapeshellarg($m[1]);
    }
    if (preg_match('/^--sweep=(.+)$/', $a, $m) === 1) {
        $sweep = $m[1];
    }
}

if (! is_file($sweep)) {
    printf("REFUSED: the sweep to read is not a file (%s) — no output, so no blind spot can be listed.\n", $sweep);
    exit(1);
}

exec(escapeshellarg($php).' '.escapeshellarg($sweep).$dumpFlag.' 2>&1', $out, $rc);

if ($rc !== 0 && $rc !== 1) {
    printf("REFUSED: the sweep exited %d — that is neither a clean run nor a reported problem, so its output is\n"
        ."       not a measurement. Last line: %s\n", $rc, (string) end($out));
    exit(1);
}

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
    // Quote the sweep rather than just saying nothing was parsed: its own first line usually names the real cause
    // (a missing dump, a bad flag), and a refusal that hides it sends the reader to the wrong tool. Measured:
    // `--dump=<nonexistent>` exits 1 here with "no blind-spot tally" alone, while the sweep had already said
    // "the dump … is missing or too short".
    echo "REFUSED: the sweep printed no blind-spot tally, so nothing here was read. Its own output was:\n";
    foreach (array_slice($out, 0, 6) as $l) {
        echo '  '.$l."\n";
    }
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

// Each row the sheet is about to print is a claim about a physical line: "the sweep says this token sits on dump
// line N". Check that before printing any row. Without the check, an off-by-one in either tool's line counter
// paints an empty sentence, which reads exactly like a pointer written with no context — and it also lets a
// --dump= that the sweep never read pass unnoticed, because a copy's line number names nothing in the real file.
// A row that cannot be located refuses the whole run; a blank row is not evidence.
$unlocatable = [];
foreach ($hits as $h) {
    if ($h['line'] < 1 || $h['line'] > count($lines)) {
        $unlocatable[] = sprintf('%s: the sweep names dump line %d, but the dump has %d line(s).',
            $h['token'], $h['line'], count($lines));

        continue;
    }
    if (strpos($fold($lines[$h['line'] - 1]), $h['token']) === false) {
        $unlocatable[] = sprintf('%s: dump line %d does not carry that token — its own text reads "%s".',
            $h['token'], $h['line'], mb_substr($fold($lines[$h['line'] - 1]), 0, 60));
    }
}
if ($unlocatable !== []) {
    printf("REFUSED: %d of %d blind row(s) could not be located in %s, so none is printed. The dump and the sweep\n"
        ."       are not the same reading — re-run dump-docx-flat.php, or check what --dump= was pointed at.\n",
        count($unlocatable), count($hits), $flat);
    foreach ($unlocatable as $u) {
        echo '  '.$u."\n";
    }
    exit(1);
}

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
