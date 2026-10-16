<?php
/**
 * Pass 46 — the four copies of EvaluationController.php:335-357, which are not four of the
 * same rule. Three cite the flexible-load exemption (measured at :571-581); the fourth, in
 * the data-source table, names the curriculum the picker reads (:134-136), so that cell is
 * rewritten whole. ROW 941's copy is left for pass 47: its anchor sits after the needle,
 * which the positional rule cannot address, and the remaining single copy is then unique.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    // §7.3 B-6 — the condition that branches the load.
    ['EvaluationController.php:335-357', 'EvaluationController.php:571-581', 'Continuing-Irregular, Shifter, Transferee'],
    // §13 rule table — fixed block versus flexible load.
    ['EvaluationController.php:335-357', 'EvaluationController.php:571-581', 'Fixed block vs flexible load'],
];

$cellEdits = [
    // §7.x data sources — the row is about WHICH subjects may be proposed, so the cite that
    // pointed at the flexible-load block belongs to the curriculum read instead.
    ['Which subjects may be proposed, and at which year level',
     'EvaluationController.php:335-357',
     'EvaluationController.php:134-136 , :235-256 (curriculum pinning), :585-604 (prerequisite auto-gate)',
     1],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass46.docx', $textEdits, $cellEdits, $apply);
