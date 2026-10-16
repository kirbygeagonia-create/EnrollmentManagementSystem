<?php

/**
 * Pass 28 — the sentences that count the inventory.
 *
 * Pass 26 added the fourteen missing edges and pass 27 re-derived the census and the rule split.
 * This pass corrects every prose figure that was a reading of the old 86, plus §24.1's own foreign
 * key row, which no longer has to apologize for an inventory it now carries.
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$textEdits = [
    ['(86 constraints)', '(100 constraints)'],
    ['in the system (15 inbound keys)', 'in the system (21 inbound keys)'],
    ['`subjects` (5), `courses` (6) and `academicterms` (6) are reference data that enrollment rows outlive.',
        '`subjects` (5), `courses` (8) and `academicterms` (7) are reference data that enrollment rows outlive.'],
    ['`enrollments` carries 9 inbound keys and `students` 9.',
        '`enrollments` carries 11 inbound keys and `students` 11.'],
    [' column sums to 86, which is the whole foreign-key inventory in §23.3. The table count sums to 62, of which 54 are EMS domain tables — the figure the cover line and §30.1 use.',
        ' column sums to 100, which is the whole foreign-key inventory in §23.3, and the Keys in column sums to 58, each edge counted once at the module its parent table belongs to. The table count sums to 63, of which 55 are EMS domain tables and 8 Laravel framework tables — §24.1 carries the same reading, and the cover line still quotes the domain figure. All three columns were re-added from information_schema on 2026-10-05, the day §23.3 gained the fourteen edges the rulings had introduced.'],
    ['86 constraints, all resolving to a real parent table and column',
        '100 constraints, all resolving to a real parent table and column'],
    ['56 of 62 tables agree column for column; 6 differ',
        '57 of 63 tables agree column for column; 6 differ'],
    ['376 columns documented for 54 domain tables',
        '409 columns documented for 55 domain tables'],
    ['53 Eloquent models for 54 domain tables',
        '54 Eloquent models for 55 domain tables'],
];

$cellEdits = [
    ['Foreign keys documented', '86 → 100', '100'],
    ['Foreign keys documented', '§23.3’s inventory was compiled at 86',
        'Re-read on 2026-10-05 against information_schema, in both directions: the inventory now carries all 100 referential edges the server holds, no edge is listed that the server does not have, and no delete or update rule reads differently from its column. The fourteen rows the rulings had added without the chapter noticing are payments.refundedBy, studentscholarships.statusChangedBy, enrollments.clearanceConfirmedBy, documentprintlog.studentId and documentprintlog.blockId, plus shiftingrequests’ own nine. §23.8’s census and §23.4’s rule split were derived from the same reading on the same day.'],
];

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-05-pass28.docx', $textEdits, $cellEdits, $apply);
