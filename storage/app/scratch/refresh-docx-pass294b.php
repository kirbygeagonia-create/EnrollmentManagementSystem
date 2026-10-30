<?php

/**
 * Pass 294b — the figure pass 294 wrote is one out, because the batch moved while the row was in the document.
 *
 * `lane69-open-inventory.php` answered 4 labels when the §1.1 row was typed and 5 after the leading-verb repair,
 * since the two §24 value rows that had been mis-split now reach the heading test. This file had already warned that
 * a register row you add moves every count the register states; here the count that moved was the instrument's own,
 * written into the document minutes earlier. So: re-measure, then correct the digit — never the reading around it.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass294b.php [--apply]
 */

$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', array_slice($argv, 1), true);

$from = 'G-38, G-40, G-41), 4 labels, 0 unresolved';
$to = 'G-38, G-40, G-41), 5 labels, 0 unresolved';

$zip = new ZipArchive;
if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$n = substr_count($xml, $from);
printf("needle occurs %d time(s) (want 1)\n", $n);
if ($n !== 1) {
    fwrite(STDERR, "REFUSED: nothing written.\n");

    exit(1);
}

docxApply($docx, $root.'/storage/app/scratch/pass294b-backup-'.date('Ymd-His').'.docx', [[$from, $to, null, 1]], [], $apply);
