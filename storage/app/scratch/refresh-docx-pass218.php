<?php

/**
 * Pass 218 — §1.1 records pass 217 and re-ages the census, without moving it again.
 *
 * The sentence names files but adds no `File.php:NN` citation, on purpose: every earlier record row that stated a
 * figure had to be re-aged by a follow-up pass (205, 209b, 210b, 213b, 216b) because the citations inside it were
 * counted by the next sweep. A record that states no range has nothing to age.
 *
 *   php storage/app/scratch/refresh-docx-pass218.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass218.php --apply  # write
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

$from = 'answered 720 citations over 154 files with 0 line problems and 7 blind numbers left.';
$to = 'answered 723 citations over 154 files with 0 line problems and 4 blind numbers left. Pass 217 took three of '
    .'those seven: the Evaluation issue row was citing that file’s profile update and guardian insert for the '
    .'transaction that writes the signed-form date and creates the workflow, the split-subject row cited an '
    .'unattributed range for the limit comment in the blocking assign, and the student-type row cited an upload '
    .'timestamp for the copy of the applicant type onto the enrollment. The last of those three is why a bare range '
    .'is dangerous as a needle — it matched a different controller’s citation first. Four numbers remain, and each '
    .'has a reason to stay: one is this document’s own example of a citation no checker can see, two point at a '
    .'payload field the cited lines do not hold, and one names no file that states the rule.';

$n = substr_count($xml, $from);
printf("  needle count %d (want 1)\n", $n);
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

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass218.docx', [[$from, $to]], [], $apply);
