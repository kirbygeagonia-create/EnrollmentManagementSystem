<?php

/**
 * Pass 222b — my own grammar defect from pass 222.
 *
 * The needle started at "both still labelled provisional", so the replacement landed after the sentence's existing
 * "and" and the row read "both seeded and and neither is marked provisional". A docx edit is judged by reading the
 * result back, not by the diff: this pass exists because the re-dumped row was read, not because a checker caught it.
 *
 *   php storage/app/scratch/refresh-docx-pass222b.php [--apply]
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
printf("  needle [seeded and and neither] occurs %d time(s) in document.xml (want 1)\n", substr_count($xml, 'seeded and and neither'));
if (substr_count($xml, 'seeded and and neither') !== 1) {
    echo "REFUSED: the doubled conjunction is not where this pass expects it.\n";
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass222b.docx',
    [['seeded and and neither', 'seeded and neither', null, 1]], [], $apply);
