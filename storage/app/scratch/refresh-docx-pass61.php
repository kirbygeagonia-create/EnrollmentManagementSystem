<?php
/**
 * Pass 61 — the two sentences §28.1 and §1.1 still owed the 2026-10-07 rulings.
 *
 * §28.1's G-6 row closed the mechanism on 2026-10-01 and then handed the remaining question to
 * C-5, which is now answered; leaving the hand-off in place would point a reader at a register
 * row that no longer asks anything. §1.1's revision table labels 2026-10-04–06 "(this issue)",
 * which cannot be true of two issues at once — the label moves in this pass and the new row is
 * inserted by pass 62, which is the row-cloning script rather than the shared engine because the
 * engine's container guard insists <w:tr> does not move.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$cellEdits = [
    // §28.1 G-6 — the flag exists, and the set that sets it is no longer an open question.
    ['An absent Course-Specific Examination result did not block admission approval',
     'Closed in code; which programs set the flag is C-5',
     'Closed in code, and C-5 answered on 2026-10-07: BSCrim, BSCE, BSEE, BSA and BSSW carry the flag'],

    // §1.1 — the previous issue stops claiming to be this one. The anchor is the row's own
    // description, because the engine addresses a cell by something inside its row and the
    // "1.1 Revision Record" heading sits outside the table.
    ['The owner’s 2026-10-02 rulings become desk actions',
     '2026-10-04–06 (this issue)', '2026-10-04–06'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-07-pass61.docx',
    [], $cellEdits, $apply);
