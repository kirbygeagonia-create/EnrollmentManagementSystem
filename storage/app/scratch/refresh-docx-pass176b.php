<?php

/**
 * Pass 176b — §30.6 kept saying nothing waits on an institutional answer while this batch was opening a row whose
 * whole content is an institutional answer.
 *
 * Pass 176 moved P-16 into the "still owed" sentence and left the paragraph's opening claim standing, so one paragraph
 * both stated that nothing here waits on the school and named an item only the school can settle. Lane 61 cannot see
 * it: that checker asks whether a sentence presents as UNDECIDED something its register has ANSWERED, and this is the
 * mirror — a sentence presenting as settled something still open. The row is the truth, so the paragraph moves.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$textEdits = [
    [
        'Nothing in this section waits on an institutional answer, and that is worth stating plainly, because the '
            .'paragraph this one replaces named four open items.',
        'Four items this section used to put to the school are answered, and the paragraph that asked them is gone. '
            .'One institutional question is still owed here, and it is a name rather than a rule: which spelling of '
            .'the institution’s name the paper carries (P-16).',
    ],
    [
        'The work this document still owes is not institutional either — it is the program and term the enrollment '
            .'form cannot print (P-2), the spelling of the name the letterhead prints (P-16), and the proxy an '
            .'operator must name before HSTS means anything (G-13).',
        'Of the rest, two are acts rather than answers — the program and term the enrollment form cannot print (P-2, '
            .'which also waits on the Registrar to let the blank form reach this repository), and the proxy an operator '
            .'must name before HSTS means anything (G-13) — and the third is the one place left where only the school '
            .'can settle what the paper says (P-16).',
    ],
];

$cells = [];

docxApply($src, $root.'/storage/app/scratch/pass176b-backup.docx', $textEdits, $cells, $apply);
