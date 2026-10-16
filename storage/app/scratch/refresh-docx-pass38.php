<?php

/**
 * Pass 38 — §3.1 and §4.5 carry the definition of the categories, and one of them said the exact
 * opposite of the ruling taken today: "A Shifter becomes Irregular … not by definition."
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$textEdits = [
    ['They arrive with prior college credits from another school.',
        'They arrive with prior college credits from another school, and that arrival is itself the reason they are Irregular: ruled 2026-10-06 (C-2), a student coming in from another institution is issued irregular rather than being left undecided for a desk to notice.'],

    ['A Shifter becomes Irregular when the credit mapping leaves them off-sequence — not by definition.',
        'A Shifter is Irregular by definition — ruled 2026-10-06 (C-2), reversing this row’s earlier “not by definition”: changing program, inside the department or into another one, is itself the departure from the sequence. What the credit mapping decides is the load and the year level, not the standing.'],

    ['departs from the normal sequence because of failed or retaken subjects, dropped subjects, credited subjects, delayed subjects, shifting history, or another documented academic condition.',
        'departs from the normal sequence. Two conditions settle it by definition — arriving from another school, and changing program (C-2, ruled 2026-10-06) — and the record is issued irregular for either. The rest the evaluating department reads off the grades and states: failed or retaken subjects, dropped subjects, credited subjects, delayed subjects, or another documented academic condition.'],
];

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass38.docx', $textEdits, [], $apply);
