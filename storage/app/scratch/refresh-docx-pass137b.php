<?php

/**
 * Pass 137b — §29.4's family count, moved again the same day pass 136 moved it.
 *
 * Pass 136 added G-10 and this section's own sentence closed by saying "§28.3 holds 27 data rows". Pass 137
 * added G-11 eleven minutes later, so that sentence falsified itself — exactly the failure the rewritten
 * paragraph is now about, reproduced within one working day. Measured again the same way: the enclosing
 * <w:tbl> counted by its </w:tr>, header excluded.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['After this batch’s G-10, §28.3 holds 27 data rows.',
        'G-10 was followed the same day by G-11, so §28.3 holds 28 data rows — and the sentence that reported '
        .'27 was stale within one working pass, which is the honest reason the count has to be re-read from the '
        .'table rather than inherited from the last edit.'],
];

$cellEdits = [
    ['Enforcement, reference-data, screen, authorization and notification gaps', '27', '28'],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-16-pass137b.docx',
    $textEdits, $cellEdits, in_array('--apply', $argv, true));
