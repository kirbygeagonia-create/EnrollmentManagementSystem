<?php

/**
 * Pass 134 — §25.8 still counted the print rows that lane 36 deleted, and §23.6.2 pointed at two sections
 * that never say the thing they are cited for.
 *
 * Found by reading a citation rather than trusting it. §23.6.2's print-log row — "it is a count of print
 * events, and each print adds a row" — carries (and cites) §20.5, which lists what the audit trail does NOT
 * capture and never mentions documentprintlog's row count; and its "where it is handled" cell says §25.7,
 * which is "New / Old Student is not student type". The section that does state it is §25.8: "Every print
 * through a desk route inserts its own row" (§25.8, measured live today at 60 rows). Both sweeps cleared
 * these — §20.5 and §25.7 exist, the ids resolve — which is exactly the class AGENTS.md says only reading
 * catches.
 *
 * Then the numbers. §25.8 still reads "80 rows on live ems: 19 clearance slips … and 4 block schedules" and
 * "Twenty-one rows carry none of the three keys — the legacy pile ruling 6 keeps and reserves instead of
 * deleting or repairing". Measured on live ems 2026-10-16: 60 rows — 21 class cards, 18 subject loads, 18
 * certificates, 2 block schedules, 1 clearance slip — and the key-less pile is gone, pruned behind a manifest
 * on 2026-10-15 (the same batch that promoted the print rule from ruled note to impossible, §25 P-14, and
 * which P-14's own last cell already reflects: a key-less row now names a write path). The document was
 * describing its own superseded state two sections away from the correction.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['80 rows on live ems: 19 clearance slips, 21 class cards, 18 certificates, 18 subject loads and 4 block schedules',
        '60 rows on live ems, re-measured 2026-10-16: 21 class cards, 18 subject loads, 18 certificates, '
        .'2 block schedules and 1 clearance slip'],

    ['Twenty-one rows carry none of the three keys — the legacy pile ruling 6 keeps and reserves instead of '
        .'deleting or repairing — and that is exactly the set the unique index over ( enrollmentId , documentType , '
        .'documentNumber ) cannot see, because MySQL treats their NULLs as distinct (§25, P-14).',
        'No row on this install carries none of the three keys: the twenty-one that did were pruned behind a '
        .'manifest on 2026-10-15, the owner reversing ruling 6’s keep-and-reserve with the same call that '
        .'promoted the rule to the failing kind. What the index cannot see is unchanged — for any row whose key '
        .'is NULL, MySQL treats those NULLs as distinct, so a key-less print hides from ( enrollmentId , '
        .'documentType , documentNumber ) — which is why one appearing now names a write path rather than the '
        .'legacy pile (§25, P-14).'],
];

// §23.6.2's print-log row: the claim sits in the third cell, the "where it is handled" pointer in the fourth.
$cellEdits = [
    ['One print log per printed document', 'It is not meant to be unique',
        'It is not meant to be unique: it is a count of print events, and each print adds a row (§25.8)'],
    ['One print log per printed document', '§25.7', '§25.8'],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-16-pass134.docx',
    $textEdits, $cellEdits, in_array('--apply', $argv, true));
