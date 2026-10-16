<?php
/**
 * Pass 68 — the retention screen now answers with the same row the signature is checked against.
 *
 * Found by sweeping the document for CLAIMS rather than file names (the sweep that finally caught
 * B-2), which led to §13.6's sentence about "that enrollment's own term". The enforcement had always
 * read it that way; the display read did not — Evaluation/Show queried the student's most recent
 * retention row for the program with no term filter, so a pass from an earlier term printed a green
 * badge on a screen whose signature was being refused for want of a result this term. One rule, two
 * readers, two answers, and the officer trusts the green one.
 *
 * The controller edit was made deliberately LINE-NEUTRAL (7 lines out, 7 lines in): that file carries
 * twenty-one citations in this document, and inserting five lines above them all would have quietly
 * invalidated every one. The file is 1021 lines before and after, so no range here needed touching.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['until a pass is recorded against that enrollment’s own term',
     'until a pass is recorded against that enrollment’s own term — and the screen now reads that '
     .'same row: Evaluation/Show’s retention badge is queried against the enrollment’s own term as '
     .'well, fixed 2026-10-08, because until then the enforcement filtered by term and the display did '
     .'not, so a pass from an earlier term could print a green badge above a signature being refused '
     .'for want of a result this term. RetentionGateTest now pins both halves of one rule'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-08-pass68.docx',
    $textEdits, [], $apply);
