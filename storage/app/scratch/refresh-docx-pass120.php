<?php

/**
 * Pass 120 — the sixth drifted citation, and the one that shows what the meaning sweep is for.
 *
 * Two places in the document say the confirming act lives at `EvaluationController.php:945-993`. Measured:
 * `confirmClearance()` is declared on 958 and closes on 999, so 945-957 is the tail of `approvedPassSlip()`
 * and its doc-comment — the front edge of the cited range sits in a different method entirely. The line sweep
 * passed it, because 945 and 999 are both inside a 1,034-line file and neither line is blank.
 *
 * Both copies are corrected together, and the engine only allows that when the caller states how many it
 * expects: it counted 2, so `expect = 2` binds the edit to exactly those two and refuses if a third appears.
 * This is the case the uniqueness rule was written for — the same false claim, restated in two sections.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    // [needle, replacement, anchor, expected occurrences]
    [':945-993', ':958-999', null, 2],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-15-pass120.docx',
    $textEdits, [], in_array('--apply', $argv, true));
