<?php

/**
 * Pass 153 — §25.6 re-read against the current print code (lane 52, owner ruling 2026-10-18).
 *
 * The section describes the clearance slip line by line, and it was written while P-1, P-10 and P-11 were open.
 * The register closed all three on 2026-10-02 — rows 2246 and 2247 even cite the Blade — but §25.6 was never
 * brought back in line, so the document contradicted itself: it denied the paper a Student ID row the template
 * prints at 57-58, said the program line still took `student.enrollments[0]` when it reads `termEnrollment()`
 * at 65, and said the PDF never reads `overallStatus` and stamps CLEARED unconditionally when it prints the
 * status at 61-62 and stamps HELD at 112-116.
 *
 * Every sentence here is edited inside its own run, not as a whole paragraph: the code spans are separate
 * Consolas runs, and the flat dump joins runs with spaces, which is why a whole-sentence needle matches the dump
 * and matches nothing in word/document.xml. Needles that are not unique in the document (the Blade citation also
 * appears in §25.12, ` twice (` appears twice, `:41` five times, `, ` 5,143 times) are addressed by an anchor
 * that occurs once in the paragraph that owns them, so the position — not a longer quote — settles which copy.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$apply = in_array('--apply', $argv, true);

require __DIR__.'/docx-edit-engine.php';

$idAnchor = 'three-column panel (';              // x1, owns para "Identity."
$periodAnchor = '$clearance-&gt;student-&gt;enrollments-&gt;first()'; // x1, owns para "Academic period."
$stateAnchor = ' The desk page prints ';          // x1, owns para "Clearance state."

$textEdits = [
    // ---- Identity.
    ['); the PDF prints Semester, Academic Year, Student Name, “Course &amp; Year” and Date to be Signed (',
        '); the PDF prints Semester, Academic Year, Student Name, Student ID, Overall Status, Course &amp; Year and Date to be Signed ('],
    ['clearance-slip.blade.php:38-55', 'clearance-slip.blade.php:38-74', $idAnchor],
    [') with ', ') — the ID row ', $idAnchor],
    ['no Student ID row', ' that P-1 recorded as missing has been on the paper since 2026-10-02, and the Overall Status row with it'],
    [' (P-1).', ' (P-1 closed).'],

    // ---- Academic period.
    [':39,43', ':46,50, and the Date to be Signed at :72-73'],
    [') — which is correct and is what makes the slip term-specific (§16). The program line does not follow that path: it uses ',
        ') — which is correct and is what makes the slip term-specific (§16). The program line follows that same path, reading '],
    ['$clearance-&gt;student-&gt;enrollments-&gt;first()', '$clearance-&gt;termEnrollment()'],
    [' (P-10).', ' at :65 (P-10 closed).', $periodAnchor],

    // ---- Clearance state.
    [' twice (', ' in its footer (', $stateAnchor],
    [':41', ':47', $stateAnchor],
    [', ', ') and again in the card (', $stateAnchor],
    [':84-97', ':93'],
    ['); the PDF never reads it and stamps “SEAIT CLEARED” unconditionally (P-11). A pending slip therefore looks cleared once converted to PDF.',
        '), whose badge renders a third at :101-102; the PDF reads it too (:61-62) and, since 2026-10-02, prints HELD — NOT YET CLEARED unless the status is approved or waived (:112-116), so a pending slip no longer looks cleared on paper (P-11 closed).'],

    // ---- Receipt.
    [':83-85', 'clearance-slip.blade.php:103-105'],
    ['PrintSlip.jsx:89-94', 'PrintSlip.jsx:94-99'],

    // ---- The revision row: the tail pass 151 left as a promise is now kept.
    ['Two pointers in §25.6 were already wrong before this batch and are left for that section’s own re-read, because the sentence around them needs re-measuring too, not only its numbers.',
        'Two pointers in §25.6 were already wrong before this batch, and that section has now had its own re-read: its rows still described the paper as it was before the rulings of 2026-10-02 closed P-1, P-10 and P-11, so the Identity, Academic-period, Clearance-state and Receipt lines were rewritten against the current Blade and JSX and every line number they cite was re-measured.'],
];

docxApply(
    $root.'/Documentation/EMS Complete Documentation.docx',
    $root.'/storage/app/scratch/docx-backup-2026-10-18-pass153.docx',
    $textEdits,
    [],
    $apply,
);
