<?php
/**
 * Pass 66 — the last citation the examination sweep could still see.
 *
 * §9's course-specific column said the departmental paper blocked approval "when a result was
 * recorded at all". Since G-6/C-5 an ABSENT result blocks too, wherever the program requires one —
 * which is the half of the rule that made C-5 worth answering, and the half this cell still missed.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$cellEdits = [
    ['Blocks admission approval: the blockers name the failed result',
     'Blocks approval when a course-specific result was recorded at all',
     'Blocks approval when a course-specific result was recorded and is not a pass, and just as firmly '
     .'when none exists and the program requires one (AdmissionPolicy.php:127-140).'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-07-pass66.docx',
    [], $cellEdits, $apply);
