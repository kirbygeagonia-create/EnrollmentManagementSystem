<?php

/**
 * Pass 207 — §1.1's living row records pass 206 and the corrected reading sheet.
 *
 * Two sentences are added to the row that already carries passes 199-205: the drift that only reading could find
 * (a date-row pointer eight lines past the row it names, landing on live code belonging to the course label), and
 * the reading aid's own defect — it searched the document for each blind NUMBER, so one flagged token reported
 * every citation sharing its digits and the queue read three times its real size.
 *
 * The stale range is described in words rather than quoted. Writing `:72-73` into the row would put a bare number
 * in a paragraph that names no file beside it, which adds two rows to the blind queue to record a fix — the same
 * mistake AGENTS.md caught when it quoted a broken pointer inside the warning about broken pointers.
 *
 *   php storage/app/scratch/refresh-docx-pass207.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass207.php --apply  # write
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

$from = 'A present-tense sentence is aged by the next ruling, not by its own date.';
$to = $from.' Pass 206 found the mirror shape in the same paragraph: a pointer can land on live code that '
    .'describes something else. Its date-row citation had drifted eight lines past the row that prints the Date to be '
    .'Signed, onto the course-label code the copy designation inserted above it, and both ranges are live code — so '
    .'only opening the template called it wrong. The same pass named the owner of the room-capacity range, which '
    .'moved the sweep to 691 citations over 154 files with 126 bare continuations judged and 41 still unattributed. '
    .'The sheet that prints that queue was corrected first: it had searched the document for each blind number, so '
    .'one flagged token reported every citation sharing its digits and the queue read three times its real size; it '
    .'now lists the sweep’s own rows, token and dump line together, because a queue with two definitions of its key '
    .'is the defect this repository has now removed twice.';

$n = substr_count($xml, $from);
printf("  anchor count %d (want 1)\n", $n);
if ($n !== 1) {
    echo "REFUSED: the living row's closing sentence is not addressable as one needle.\n";
    exit(1);
}
if (str_contains($to, '<') || str_contains($to, '>')) {
    echo "REFUSED: the replacement carries markup.\n";
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass207.docx', [[$from, $to]], [], $apply);
