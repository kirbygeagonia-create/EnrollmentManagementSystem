<?php

/**
 * Docx pass 327 — remove the duplicated title and the literal pipe from pass 326's §1.1 row.
 *
 * Pass 326 built the row from a two-value array, but its first draft had supplied the body as
 * `'<title> | <body>'` and only the date cell was later merged. So cell 1 opens by repeating cell 0's title,
 * separated by a literal `|` — which this document has twice before turned into a mis-split row: the flat dump
 * joins cells with `' | '`, so a pipe inside a cell is indistinguishable from a cell boundary for anything that
 * counts columns in the dump. Measured in document.xml: the row holds 2 cells and 1 in-cell pipe; the G-51 row
 * beside it holds 5 and 0.
 *
 * The needle is contiguous because pass 326 wrote that cell as ONE run. The two cells do not share the phrase:
 * cell 0 says "the audit of the join", cell 1 says "the audit of that wiring", so the count check below is what
 * makes the replacement safe rather than lucky.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass327.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

require __DIR__.'/docx-edit-engine.php';

$from = 'The ENUM ruling reached the three queues that had withheld their columns for their type, and the audit of that wiring found a leak underneath it | Student Type and Final Standing';
$to = 'Student Type and Final Standing';

$readCells = static function (string $xml): array {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $xml, $cm);

    $out = [];
    foreach ($cm[0] as $c) {
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $c, $t);
        $out[] = trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $t[1]), ENT_QUOTES | ENT_XML1)));
    }

    return $out;
};

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (an error CODE is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$hits = substr_count($xml, $from);
printf('needle occurrences in document.xml: %d (want 1)%s', $hits, PHP_EOL);
if ($hits !== 1) {
    fwrite(STDERR, "REFUSED: the duplicated title does not occur exactly once — read the row before editing it.\n");

    exit(1);
}

// Locate the row now, so the after-state can be compared cell by cell rather than by absence of a string.
$before = null;
$at = 0;
while (($s = strpos($xml, '<w:tr', $at)) !== false) {
    $e = strpos($xml, '</w:tr>', $s) + 7;
    $at = $e;
    $cells = $readCells(substr($xml, $s, $e - $s));
    if (($cells[0] ?? '') !== '' && str_starts_with($cells[0], '2026-10-31 ')) {
        $before = $cells;
        break;
    }
}
if ($before === null) {
    fwrite(STDERR, "REFUSED: no §1.1 row begins with `2026-10-31 ` — pass 326's row is not where this pass expects.\n");

    exit(1);
}
printf('row before: %d cell(s), %d pipe(s) inside cells%s', count($before), substr_count(implode('', $before), '|'), PHP_EOL);
foreach ($before as $i => $c) {
    printf("  cell %d head: %s%s", $i, substr($c, 0, 70), PHP_EOL);
}

docxApply($docx, $root.'/storage/app/scratch/pass327-dry.docx', [[$from, $to]], [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open after the write.\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$at = 0;
$now = null;
while (($s = strpos($after, '<w:tr', $at)) !== false) {
    $e = strpos($after, '</w:tr>', $s) + 7;
    $at = $e;
    $cells = $readCells(substr($after, $s, $e - $s));
    if (($cells[0] ?? '') !== '' && str_starts_with($cells[0], '2026-10-31 ')) {
        $now = $cells;
        break;
    }
}
if ($now === null) {
    fwrite(STDERR, "REFUSED: the row disappeared — restore pass 326's newest backup by hand.\n");

    exit(1);
}

printf('row after: %d cell(s), %d pipe(s) inside cells%s', count($now), substr_count(implode('', $now), '|'), PHP_EOL);
printf('  cell 1 now opens: %s%s', substr($now[1], 0, 70), PHP_EOL);

$bad = 0;
if (count($now) !== count($before)) {
    echo "  FAIL the row gained or lost a cell\n";
    $bad++;
}
if (substr_count(implode('', $now), '|') !== 0) {
    echo "  FAIL a pipe still sits inside a cell\n";
    $bad++;
}
if (substr_count($after, $from) !== 0) {
    echo "  FAIL the duplicated title still occurs\n";
    $bad++;
}
if (! str_starts_with($now[1], 'Student Type and Final Standing are now headers')) {
    echo "  FAIL cell 1 no longer opens with the body sentence\n";
    $bad++;
}
if ($bad > 0) {
    fwrite(STDERR, "REFUSED: {$bad} read-back check(s) failed after the write.\n");

    exit(1);
}

echo 'written, re-read: two cells, no in-cell pipe, title stated once.'."\n";
