<?php

/**
 * Docx pass 329b — the two places that COUNT §28.3 after G-52, and today's §1.1 row, which must name it.
 *
 * A register row that is added and a index row that is left behind is the defect lane 64 names: nothing re-opens the
 * index when a register grows, and a stated range that stopped growing reads as completeness. §28.1 still says
 * `G-1 … G-51` and §29.4 still counts 68 while the rows now answer 69.
 *
 * The third edit is the one lane 61 and lane 68 both teach: a ruling word, or in this case a new row, must reach the
 * document's own running record, not only the row it decides. Today's §1.1 entry closes by explaining why yesterday's
 * dated row is left alone; it now also states that the same live read produced G-52, so a reader of §1.1 does not
 * have to discover the newest register row by scanning §28.3.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass329b.php [--apply]
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

$textEdits = [
    // §28.1's identifier index.
    ['G-1 … G-51', 'G-1 … G-52'],
    // Today's §1.1 row: the same live read produced one more register row, and §1.1 has to say so.
    [
        'it records the ruling’s first three screens, not today’s.',
        'it records the ruling’s first three screens, not today’s. The same reading of the live desk payloads — the one that proved the pinned row carries no credential column under any order — found that the queues still eager-load the whole student to paint a name, so a cashier receives that student’s birthdate and contact number in a payload no screen of theirs reads; that is G-52, registered open, and its row says why the fix is three `with` clauses rather than a question for the school.',
    ],
];

$cellEdits = [
    // §29.4's family table: the G/D/U/X/N row.
    ['notification gaps', '68', '69'],
];

docxApply($docx, $root.'/storage/app/scratch/pass329b-dry.docx', $textEdits, $cellEdits, $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the written archive.\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$ids = [];
$at = 0;
while (($s = strpos($after, '<w:tr', $at)) !== false) {
    $e = strpos($after, '</w:tr>', $s) + 7;
    $at = $e;
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($after, $s, $e - $s), $cm);
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cm[0][0] ?? '', $t);
    $first = trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $t[1]), ENT_QUOTES | ENT_XML1)));
    if (preg_match('/^([GDUXN]-\d+)$/', $first, $m)) {
        $ids[] = $m[1];
    }
}
$rows = count($ids);

// Read back the way the document is actually written: a sentence lives across SEVERAL `<w:t>` runs, so a raw
// `substr_count` over the XML reports zero for text that is present — which is how this pass first "failed" its own
// correct write. Flatten each row's cells, then search that.
$flatRows = [];
$at2 = 0;
while (($s2 = strpos($after, '<w:tr', $at2)) !== false) {
    $e2 = strpos($after, '</w:tr>', $s2) + 7;
    $at2 = $e2;
    $flatRows[] = flattenRow(substr($after, $s2, $e2 - $s2));
}
$haystack = implode(' ~~ ', $flatRows);

function flattenRow(string $row): string
{
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $row, $t);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode(' ', $t[1]), ENT_QUOTES | ENT_XML1)));
}

$checks = [
    ['G-1 … G-52', 1, 'the index range moved'],
    ['G-1 … G-51', 0, 'the retired range must be gone'],
    ['is that G-52, registered open', 1, 'the §1.1 row names the new row'],
];
$bad = 0;
foreach ($checks as [$needle, $want, $why]) {
    $n = substr_count($haystack, $needle);
    printf("  %-28s %d (want %d)  %s\n", substr($needle, 0, 28), $n, $want, $why);
    if ($n !== $want) {
        $bad++;
    }
}
$cellSeen = substr_count($after, '>69</w:t>') + substr_count($after, 'xml:space="preserve">69<');
printf("  family cell reads 69: %d run(s); rows answer %d\n", $cellSeen, $rows);
if ($cellSeen < 1 || $rows !== 69) {
    $bad++;
}
if ($bad > 0) {
    fwrite(STDERR, "REFUSED: {$bad} read-back check(s) failed after the write.\n");

    exit(1);
}

echo 'written, re-read: the index, the count and §1.1 all agree with the rows the register holds.'."\n";
