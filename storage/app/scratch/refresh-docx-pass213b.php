<?php

/**
 * Pass 213b — the record row's own citation moved the figure the record states.
 *
 * Pass 209 wrote its citation census from the reading taken before it, and passes 209b and 210 each added
 * citations of their own (the two re-pointed ranges, then the slip's named date row). This re-ages both figures in
 * the living revision row to what the sweep answers now: 707 citations over 154 files, and 21 blind numbers.
 * Pass 205's lesson, applied a third time — a §1.1 record row moves the census the record states.
 *
 *   php storage/app/scratch/refresh-docx-pass210b.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass210b.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    ['answered 710 citations over 154 files', 'answered 711 citations over 154 files'],
];
foreach ($edits as $i => [$from, $to]) {
    $n = substr_count($xml, $from);
    printf("  needle %d count %d (want 1) — %s\n", $i + 1, $n, $from);
    if ($n !== 1) {
        printf("REFUSED: needle %d is not addressable as one needle, so the figure would be half-aged.\n", $i + 1);
        exit(1);
    }
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass213b.docx', $edits, [], $apply);
