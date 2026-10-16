<?php

/**
 * Pass 130 — "§14R.2" is the acceptance script's numbering, and the document cites it as if it were its own.
 *
 * Lane 37's repo sweep resolved four code comments and this one docx sentence to a section that exists in no
 * document but the walkthrough's: its desk-action rows are numbered 12R.1 … 17.1, and the docx has no heading
 * of that form at all (measured: `grep -cE "^P [0-9]+: [0-9]+[A-Z]\.[0-9]+ " docx-flat.txt` → 0). The
 * document's own convention for this already exists — "§2.8 of the acceptance script" — so the sentence is
 * brought into line with it. The four PHP comments were fixed in the same pass, and
 * `repo-file-line-citations.php` was written because AGENTS.md now carries file:line citations that nothing
 * had ever read.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['the state §14R.2 says no desk can produce',
        'the state the acceptance script’s §14R.2 says no desk can produce'],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-15-pass130.docx',
    $textEdits, [], in_array('--apply', $argv, true));
