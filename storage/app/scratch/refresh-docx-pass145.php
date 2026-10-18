<?php

/**
 * Pass 145 — one re-pointed citation, and the suite's own size corrected.
 *
 * The first edit is a pointer the line sweep caught: §5's transition table cites AssessmentController.php:161-178
 * for "computing an assessment does not move the status", and after this batch's edit line 161 is blank. The
 * claim is about the commented-out transition inside compute(), so the range is re-pointed at the whole method
 * (119-183), whose line 180 is the comment that proves the sentence.
 *
 * The second is a number this session wrote down before measuring it. The authoritative run is the whole-suite
 * `artisan test` that finished after the last source change: 743 tests, 4,802 assertions, exit 0. The 4,814 that
 * had been written was arithmetic from an unfinished run's partial log. A document that states a test count has
 * to state the one a run printed.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['AssessmentController.php:161-178', 'AssessmentController.php:119-183', 1],
    ['The suite stands at 743 tests and 4814 assertions', 'The suite stands at 743 tests and 4802 assertions', 1],
];

docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-18-pass145.docx', $textEdits, [], in_array('--apply', $argv, true));
