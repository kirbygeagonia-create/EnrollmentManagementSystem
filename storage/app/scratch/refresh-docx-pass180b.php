<?php

/**
 * Pass 180b — one sentence in the row pass 180 just wrote overstated what the harness plants.
 * The G-30 cell says the harness "plants six ways the sets could disagree"; the harness has seven legs
 * (six planted set-defects and one that renames a row's table reference). Fix the document to the tool.
 */

$root = dirname(__DIR__, 3);
require_once $root.'/storage/app/scratch/docx-edit-engine.php';
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$from = 'mutate-lane64-empty-tables.php plants six ways the sets could disagree and requires each to refuse';
$to = 'mutate-lane64-empty-tables.php plants seven ways the sets and the rows could disagree with the server and requires each to refuse';
$n = substr_count($xml, $from);
printf("needle occurs %d time(s)\n", $n);
if ($n !== 1) {
    exit("REFUSED: the sentence is not addressable as one contiguous run — nothing written.\n");
}
foreach (['&', '<', '>'] as $c) {
    if (str_contains($to, $c)) {
        exit("REFUSED: the replacement carries {$c}.\n");
    }
}
if (! $apply) {
    echo "DRY RUN. Re-run with --apply.\n";
    exit(0);
}
docxApply($src, $root.'/storage/app/scratch/pass180b-backup.docx', [[$from, $to]], [], true);
echo "pass 180b applied.\n";
