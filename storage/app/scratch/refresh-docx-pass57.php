<?php
/**
 * Pass 57 — the last two stale pointers the sweep found.
 *
 * ExamPolicy is a small file and its cites were nearly all right; one row pointed at
 * verifyGeneralExam (:57-63) for a claim about courses.requiresRetentionExam, which is the guard
 * at :45-50. And §29 still cited HandleInertiaRequests.php:58-61 for the flash keys, three lines
 * below where that block actually lives since info was shared (:86-91).
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['HandleInertiaRequests.php:58-61', 'HandleInertiaRequests.php:86-91'],
];

$cellEdits = [
    ['Does this program require a retention examination', 'ExamPolicy.php:52-64',
     'ExamPolicy.php:45-50 , the flag on courses', 1],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-07-pass57.docx', $textEdits, $cellEdits, $apply);
