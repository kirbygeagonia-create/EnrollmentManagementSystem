<?php

/**
 * Pass 180c — the living §1.1 revision row must carry the lane the register row carries.
 *
 * No totals are added: lane 58's rule is that a row which must outlive the run it was read from names one
 * surface or states no total, and this batch changes no suite figure.
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

$from = 'P-16, the spelling of the name the letterhead prints, stays the school’s to state.';
$to = $from.' Lane 64 then asked the question none of the claim checkers asks: whether a list the document '
    .'calls a set actually equals the set. §24.17’s empty-table list, §24.18’s count of it and §28.1’s range for '
    .'the G family had all been remembered rather than re-counted; §28.3 G-30 records what COUNT(*) answered '
    .'instead, and storage/app/scratch/lane64-empty-table-inventory.php --verify now judges those sets against '
    .'the server, with mutate-lane64-empty-tables.php planting seven ways they could disagree.';
$n = substr_count($xml, $from);
printf("needle occurs %d time(s)\n", $n);
if ($n !== 1) {
    exit("REFUSED: the living revision row is not addressable as one contiguous run — nothing written.\n");
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
docxApply($src, $root.'/storage/app/scratch/pass180c-backup.docx', [[$from, $to]], [], true);
echo "pass 180c applied.\n";
