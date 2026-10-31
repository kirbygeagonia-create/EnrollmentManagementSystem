<?php

/**
 * Docx pass 326b — the two places that COUNT §28.3 after pass 326 opened G-51.
 *
 * §28.1's identifier index reads `G-1 … G-50` and §29.4's family table counts the G/D/U/X/N rows at 67. A stated
 * range that stopped growing is the same defect as a stale row (AGENTS.md, lane 64): nothing re-opens the index when
 * a register gains a row, and every bounds check agrees with a document that counts itself short.
 *
 * They are a SEPARATE pass from 326 because `docxApply()` opens the archive itself — a pass that rewrote
 * document.xml in memory and then handed the file to the engine would have its own work discarded silently while
 * the engine reported its edits as applied.
 *
 * The verdict is not the edit: after the write the script counts the §28.3 id rows from document.xml and refuses
 * when the cell and the rows disagree, so this pass cannot leave §29.4 stating a number the register does not hold.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass326b.php [--apply]
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
    // §28.1's identifier index: the G family now runs to G-51.
    ['G-1 … G-50', 'G-1 … G-51'],
];

$cellEdits = [
    // §29.4's family table, the row whose own third cell ends "…notification gaps".
    ['notification gaps', '67', '68'],
];

docxApply($docx, $root.'/storage/app/scratch/pass326b-dry.docx', $textEdits, $cellEdits, $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the written archive (an error CODE is truthy).\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};

$rows = 0;
$ids = [];
$at = 0;
while (($start = strpos($after, '<w:tr', $at)) !== false) {
    $end = strpos($after, '</w:tr>', $start);
    if ($end === false) {
        break;
    }
    $end += 7;
    $at = $end;
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($after, $start, $end - $start), $cm);
    $first = $flatten($cm[0][0] ?? '');
    if (preg_match('/^([GDUXN]-\d+)$/', $first, $m)) {
        $ids[] = $m[1];
    }
}
$rows = count($ids);

$cellSeen = substr_count($after, '>68</w:t>') + substr_count($after, 'xml:space="preserve">68<');
$rangeSeen = substr_count($after, 'G-1 … G-51');

printf("the family cell now reads 68 (%d run(s) carry that text), the index range reads G-1 … G-51 %d time(s), and the rows answer %d\n", $cellSeen, $rangeSeen, $rows);

if ($rows !== 68) {
    fwrite(STDERR, "REFUSED: §29.4 would state 68 while the rows answer {$rows}.\n");

    exit(1);
}
if ($cellSeen < 1 || $rangeSeen < 1) {
    fwrite(STDERR, "REFUSED: the write reported success and the bytes name neither the new cell nor the new range.\n");

    exit(1);
}
if (str_contains($after, 'G-1 … G-50') || substr_count($after, 'notification gaps') < 1) {
    fwrite(STDERR, "REFUSED: the retired range `G-1 … G-50` is still in the document.\n");

    exit(1);
}
// Order is read from the row's own neighbours, never from arithmetic on the list: `rows - 8` is a guess about how
// many D/U/X/N rows follow, and a register that gains a row in another family would make it wrong while the
// document still read fine.
$at51 = array_search('G-51', $ids, true);
if ($at51 === false || ($ids[$at51 - 1] ?? null) !== 'G-50' || ($ids[$at51 + 1] ?? null) !== 'D-1') {
    fwrite(STDERR, 'REFUSED: G-51 is not between G-50 and D-1 in the id sequence'
        .($at51 === false ? ' (it is absent).' : ': '.json_encode(array_slice($ids, max(0, $at51 - 2), 4))).".\n");

    exit(1);
}

echo 'written, re-read, and the two counts agree with the rows the register holds.'."\n";
