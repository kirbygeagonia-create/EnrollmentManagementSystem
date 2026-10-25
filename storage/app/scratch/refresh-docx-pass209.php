<?php

/**
 * Pass 209 — §1.1's living row records passes 208 and 208b.
 *
 * The finding worth recording is not the count. Two register rows cited a range that is live code in the right
 * file belonging to a different construct: §13's elective row pointed at the standing-override message
 * (EvaluationController.php:542-558) for the rule that refuses a proposal outside the min–max band, which is
 * :603-617; §6's staff row pointed at the curriculum block for the seeding of staff8 and the office heads, which
 * is DevReferenceDataSeeder.php:782-827. Both ranges sit inside the file the sentence names, so the bounds sweep
 * had nothing to refuse — only reading the sentence against the method finds this shape, and it is now the third
 * time in three passes.
 *
 *   php storage/app/scratch/refresh-docx-pass209.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass209.php --apply  # write
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

$from = 'because a queue with two definitions of its key is the defect this repository has now removed twice.';
$to = $from.' Passes 208 and 208b then worked the queue itself, and two of its rows turned out to cite the wrong '
    .'construct rather than the wrong line: the elective row reached for the rule that refuses a proposal outside '
    .'the min–max band and quoted the standing-override message two blocks earlier, while that check is '
    .'EvaluationController.php:603-617; the staff row said the seeder creates staff8 and one office head per office '
    .'and quoted the curriculum block, while that seeding is DevReferenceDataSeeder.php:782-827. Both cited ranges '
    .'are live code in the very file the sentence names, so no bounds check could call either wrong, and neither '
    .'false range is repeated here — a record that quotes a broken pointer as a citation hands the next reader the '
    .'defect it is describing. Eighteen more pointers gained a file name, which moved the sweep to 704 citations '
    .'over 154 files and the queue from 41 blind numbers to 23. The needles were tested before being written by '
    .'storage/app/scratch/lane70-probe-needles.php, which prints how many times a phrase occurs in the document — '
    .'a pass that discovers its needle is five separate runs only after it refuses itself wastes the pass, and a '
    .'positional anchor is used only when the anchor is unique.';

$n = substr_count($xml, $from);
printf("  anchor count %d (want 1)\n", $n);
if ($n !== 1) {
    echo "REFUSED: the living row's closing sentence is not addressable as one needle.\n";
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass209.docx', [[$from, $to]], [], $apply);
