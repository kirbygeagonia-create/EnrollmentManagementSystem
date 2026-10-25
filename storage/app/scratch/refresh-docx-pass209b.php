<?php

/**
 * Pass 209b — the record's own two citations moved the census it reports.
 *
 * Pass 209 wrote "704 citations over 154 files" from the reading taken before it, and the row it just wrote cites
 * EvaluationController.php:603-617 and DevReferenceDataSeeder.php:782-827 — two citations the next sweep counted.
 * This is pass 205's lesson applied again: a §1.1 record row moves the figure the record states, so the figure is
 * re-aged here rather than left describing an older build.
 *
 *   php storage/app/scratch/refresh-docx-pass209b.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass209b.php --apply  # write
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

$from = 'moved the sweep to 704 citations over 154 files';
$to = 'moved the sweep to 706 citations over 154 files';
$n = substr_count($xml, $from);
printf("  needle count %d (want 1)\n", $n);
if ($n !== 1) {
    echo "REFUSED: the figure to re-age is not addressable as one needle.\n";
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass209b.docx', [[$from, $to]], [], $apply);
