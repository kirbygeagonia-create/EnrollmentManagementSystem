<?php

/**
 * Pass 125 — pass 124 left one clause of its own sentence stale.
 *
 * §20.5 introduced the three noted rules as "a state somebody has already ruled to keep". Two of them are no
 * longer kept: pass 124 records the 21 key-less print rows as pruned and the seven past-term Clinic records as
 * retired, in the very sentence the clause introduces. A reader reaching "ruled to keep" then met its own
 * opposite three words later.
 *
 * Also re-timed: the same clause in DEPLOYMENT.md and the record-state command's own wording were changed in
 * this batch; the document now matches them.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['while three are noted rather than failed because each counts a state somebody has already ruled to keep:',
        'while three are noted rather than failed because each counts a state somebody has already ruled on:'],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-15-pass125.docx',
    $textEdits, [], in_array('--apply', $argv, true));
