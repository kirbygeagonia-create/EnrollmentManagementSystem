<?php

/**
 * Pass 21 — put §13.7's citations in the document's own form.
 *
 * The convention is a basename with a line range (ExamController.php:243), which is what the
 * citation sweeper can verify against the repository. The new section was written with full
 * paths in three places and one bare "EvaluationController:574", so those reads as prose
 * rather than as evidence.
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$textEdits = [
    ['app/Policies/ShiftRequestPolicy.php:57', 'ShiftRequestPolicy.php:57'],
    ['app/Models/Shiftingrequests.php:47', 'Shiftingrequests.php:47'],
    ['app/Http/Controllers/Evaluation/ShiftRequestController.php:109', 'ShiftRequestController.php:109'],
    ['(they may carry a partial load, EvaluationController:574)', '(they may carry a partial load, EvaluationController.php:570-574)'],
];

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-04-pass21.docx', $textEdits, [], $apply);
