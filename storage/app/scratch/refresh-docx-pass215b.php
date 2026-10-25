<?php

/**
 * Pass 215b — repair the citation pass 215 welded.
 *
 * Pass 215 wanted to re-point the §6 row's `:328` and used the needle `:328 )` because it was unique in
 * document.xml. It was unique — and it was the wrong one. The print paragraph at §25 cites
 * `ClearanceController.php:328`, where `ClearanceController.php` and `:328 )` sit in adjacent runs with nothing
 * between them, so replacing that tail with `AdmissionController.php:411 )` produced
 * `ClearanceController.phpAdmissionController.php:411`, one token naming no file. The bounds sweep found it
 * immediately ("1 name(s) not in the repository") and moved the file count from 154 to 155 for a phantom.
 *
 * This is lane 69's prefix rule from the other side: a needle does not have to be a prefix of a longer needle to
 * collide with it — it only has to be a substring of another citation's tail. Uniqueness in the file is necessary
 * and not sufficient; the match also has to be the citation the pass means to edit. §6's row is addressed through
 * the cell kind instead, and `:328` stays as it is until then.
 *
 *   php storage/app/scratch/refresh-docx-pass215b.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass215b.php --apply  # write
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

$from = 'ClearanceController.phpAdmissionController.php:411 )';
$to = 'ClearanceController.php:328 )';
$n = substr_count($xml, $from);
printf("  welded token count %d (want 1)\n", $n);
if ($n !== 1) {
    echo "REFUSED: the damage is not where this pass expects it.\n";
    exit(1);
}
if (substr_count($xml, 'ClearanceController.php:328') !== 4) {
    printf("REFUSED: %d other copies of the true citation are on file; this pass was written against 4.\n",
        substr_count($xml, 'ClearanceController.php:328'));
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass215b.docx', [[$from, $to]], [], $apply);
