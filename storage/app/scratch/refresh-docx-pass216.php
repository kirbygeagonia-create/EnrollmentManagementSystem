<?php

/**
 * Pass 216 — §1.1 records passes 214 and 215, and re-ages the figure the previous record row stated.
 *
 * One edit, not two: the sentence whose figures need re-aging is the sentence the new record would append to, and
 * one offset cannot carry two edits.
 *
 *   php storage/app/scratch/refresh-docx-pass216.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass216.php --apply  # write
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

$from = 'answered 711 citations over 154 files with 0 line problems and 16 blind numbers left.';
$to = 'answered 718 citations over 154 files with 0 line problems and 7 blind numbers left. Passes 214 and 215 '
    .'then took the register rows whose pointers name no file at all. Six gained an owner read back from the line: '
    .'the Blocking eligibility filter and its per-row guard, the next-box-must-be-Blocking check, and the audit '
    .'observer’s comment and self-service exclusion. Two more were pointed at the wrong thing, which is the sixth '
    .'and seventh of that shape: the settlement row quoted a docblock about covered accounts for the rule that the '
    .'enrollment moves to paid and the Accounting box is signed only when the balance reaches zero — that is '
    .'AccountingController.php:236-245 — and the student-type row quoted an upload record’s timestamp for the copy '
    .'of the applicant type onto the enrollment, which is AdmissionController.php:411. One pointer is left '
    .'unattributed on purpose: the register’s own record quotes a bare line number as an example of a citation no '
    .'checker can see, and naming a file there would make the example a claim. Pass 215 also taught a lesson the '
    .'sweep caught before anything was read: its needle was unique in the file but belonged to a different '
    .'citation — the tail of a run-split reference two files away — and the write welded the two names into one '
    .'token that no file answers; the bounds sweep refused it as a name not in the repository, and pass 215b '
    .'restored the citation. Uniqueness in document.xml is necessary and not sufficient: check what a match sits '
    .'after, and take a run-split citation through the cell kind rather than a bare tail.';

$n = substr_count($xml, $from);
printf("  anchor count %d (want 1)\n", $n);
if ($n !== 1) {
    echo "REFUSED: the tail to re-age is not addressable as one needle.\n";
    exit(1);
}
foreach (['<', '>'] as $ch) {
    if (str_contains($to, $ch)) {
        echo "REFUSED: the replacement carries markup.\n";
        exit(1);
    }
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass216.docx', [[$from, $to]], [], $apply);
