<?php

/**
 * Pass 131b — the sentence pass 131 wrote called the new dataset check "one of those legs", and it is not a
 * walkthrough leg: the four legs walk a student through the desks, while this class reads a report. The
 * document's own numbering of the walkthrough's rows is load-bearing elsewhere (the acceptance script's
 * 12R.1 … 17.1, which pass 130 had to disambiguate for the same reason), so the wording is corrected before
 * anyone goes looking for a leg that is not there.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['and one of those legs is now a class that reads this command’s register line by line',
        'and the same step now also runs a class that reads this command’s register line by line'],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-16-pass131b.docx',
    $textEdits, [], in_array('--apply', $argv, true));
