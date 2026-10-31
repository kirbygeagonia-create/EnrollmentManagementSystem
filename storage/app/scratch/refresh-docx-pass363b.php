<?php

/**
 * Docx pass 363b — the two places that COUNT the register follow the row pass 363 added.
 *
 * §28.1's identifier index says the G family runs `G-1 … G-52`, and §29.4's family table counts 69 rows for
 * G / D / U / X / N. Both are boundary claims, not summaries: a stated range that stopped growing is the same defect
 * as a stale row (lane 64), and this document has been caught leaving both cells behind twice — once when §28.3 grew
 * to G-44 … G-50, once when §25.4's P range moved nine places. They are a separate pass because `docxApply()` re-reads
 * the archive from disk and would discard whatever the row pass wrote in memory.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass363b.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = __DIR__.'/pass363b-backup.docx';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$textEdits = [
    ['G-1 … G-52', 'G-1 … G-53', null, 1],
];
$cellEdits = [
    ['G / D / U / X / N', '69', '70', 1],
];

foreach ([['range cell', 'G-1 … G-52', 1], ['family row anchor', 'G / D / U / X / N', 1], ['count cell text', '>69<', 1]] as [$what, $needle, $want]) {
    $got = substr_count($xml, $needle);
    if ($got !== $want) {
        fwrite(STDERR, sprintf("REFUSED: %s needle occurs %d time(s), wanted %d.\n", $what, $got, $want));

        exit(1);
    }
    printf("ok   %s occurs once\n", $what);
}

docxApply($docx, $backup, $textEdits, $cellEdits, $apply);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen the docx.\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ([
    ['G-1 … G-52', 0],
    ['G-1 … G-53', 1],
    ['>69<', 0],
    ['>70<', 1],
] as [$needle, $want]) {
    $got = substr_count($after, $needle);
    if ($got !== $want) {
        echo sprintf("BAD  [%s] occurs %d, wanted %d — restoring.\n", $needle, $got, $want);
        copy($backup, $docx);

        exit(1);
    }
}

echo "written and read back: the index range and the family count both name the register the document now holds.\n";
