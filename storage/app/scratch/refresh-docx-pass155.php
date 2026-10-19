<?php

/**
 * Pass 155 — settle the date the document contradicts itself on (owner ruling 2026-10-18: "say both").
 *
 * Three register rows record P-1, P-10 and P-11 as closed 2026-10-02, §25.12's row says the slip's ID line was
 * "Done 2026-10-01", and git says the code for all three landed in 29b5532 on 2026-10-01. Both dates are true:
 * the commit is 10-01, the day the closure was recorded in this document is 10-02. So the register rows now carry
 * the commit beside the recording date, and §25.6 — which pass 153 had written with the recording date — is
 * changed to the date the paper actually changed.
 *
 * Every needle here is one of five strings that occur exactly once; the bare "Closed 2026-10-02" occurs nine
 * times, which is why none of the edits uses it.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$apply = in_array('--apply', $argv, true);

require __DIR__.'/docx-edit-engine.php';

docxApply(
    $root.'/Documentation/EMS Complete Documentation.docx',
    $root.'/storage/app/scratch/docx-backup-2026-10-18-pass155.docx',
    [
        ['Closed 2026-10-02 in the print-fidelity batch.',
            'Closed 2026-10-02 in the print-fidelity batch — the code landed 2026-10-01 in 29b5532.'],
        ['Closed 2026-10-02: the slip takes course, year level',
            'Closed 2026-10-02, code landed 2026-10-01: the slip takes course, year level'],
        ['Closed 2026-10-02: the stamp is derived from overallStatus',
            'Closed 2026-10-02, code landed 2026-10-01: the stamp is derived from overallStatus'],
        ['has been on the paper since 2026-10-02, and the Overall Status row with it',
            'has been on the paper since 2026-10-01, and the Overall Status row with it'],
        ['and, since 2026-10-02, prints HELD',
            'and, since 2026-10-01, prints HELD'],
    ],
    [],
    $apply,
);
