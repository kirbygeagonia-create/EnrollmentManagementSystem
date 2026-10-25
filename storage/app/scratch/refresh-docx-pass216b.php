<?php

/**
 * Pass 216b — the record's own two citations moved the census it states.
 *
 * Pass 216 wrote "718" from the reading taken before it, then named AccountingController.php:236-245 and
 * AdmissionController.php:411 inside the same sentence, which the next sweep counted as 720. Passes 205, 209b,
 * 210b and 213b are this same correction: the loop is expected, and the figure is re-aged rather than left
 * describing an older build.
 *
 *   php storage/app/scratch/refresh-docx-pass216b.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass216b.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$from = 'answered 718 citations over 154 files with 0 line problems and 7 blind numbers left.';
$to = 'answered 720 citations over 154 files with 0 line problems and 7 blind numbers left.';

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$n = substr_count($xml, $from);
printf("  needle count %d (want 1)\n", $n);
if ($n !== 1) {
    echo "REFUSED: the figure is not addressable as one needle.\n";
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass216b.docx', [[$from, $to]], [], $apply);
