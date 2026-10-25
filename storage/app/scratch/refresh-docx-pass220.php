<?php

/**
 * Pass 220 — the record row for pass 219, which drains the queue to one pointer that stays by design.
 *
 * Two edits at two distinct offsets: the census the row states, and its closing sentence. Neither adds a
 * `File.php:NN` citation, so the figures will not need re-aging by a follow-up pass.
 *
 *   php storage/app/scratch/refresh-docx-pass220.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass220.php --apply  # write
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

$edits = [
    ['answered 723 citations over 154 files with 0 line problems and 4 blind numbers left.',
        'answered 725 citations over 154 files with 0 line problems and 1 blind number left.'],
    ['Four numbers remain, and each has a reason to stay: one is this document’s own example of a citation no '
        .'checker can see, two point at a payload field the cited lines do not hold, and one names no file that '
        .'states the rule.',
        'One number remains, and it stays by design: it is this document’s own example of a citation no checker '
        .'can see, quoted inside the sentence that explains why such pointers are dangerous. Pass 219 found the last '
        .'two owners, and they were the eighth and ninth wrong targets — the Evaluation issue guard whose message '
        .'names the shift flow, and the payload field the labels row attributed to a notification’s message line '
        .'instead of the array some dozens of lines below it. Reading a cited span against the claim in its own '
        .'sentence is the only instrument that sees this shape, because the lines are live and the file is right.'],
];
foreach ($edits as $i => [$from, $to]) {
    $n = substr_count($xml, $from);
    printf("  needle %d count %d (want 1)\n", $i + 1, $n);
    if ($n !== 1) {
        printf("REFUSED: needle %d is seen %d time(s).\n", $i + 1, $n);
        exit(1);
    }
    foreach (['<', '>'] as $ch) {
        if (str_contains($to, $ch)) {
            echo "REFUSED: a replacement carries markup.\n";
            exit(1);
        }
    }
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass220.docx', $edits, [], $apply);
