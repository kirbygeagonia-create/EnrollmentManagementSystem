<?php

/**
 * Pass 126 — the print rule became an `impossible`, and the severity comment moved the lines §20.5 cites.
 *
 * The owner ruled on 2026-10-15 that the 21 key-less print rows be pruned. Once they were gone there was no
 * longer a decision waiting on that rule — nothing this application can print leaves all three of
 * enrollmentId, studentId and blockId NULL (a slip carries its student, a roster its block, everything else
 * its enrollment), so a key-less row is a write path and belongs with the nine that fail the run. The rule
 * was promoted, and the test that pinned it as a note was rewritten to pin it as a failure — red first
 * (`an_unattributable_printed_paper_is_impossible_and_fails_the_run` expected exit 1 and got 0), then green.
 *
 * Consequences for this paragraph: "three are noted" is now two, and the file grew by four lines when the
 * promotion recorded its reason in the code, so both cited ranges moved — measured from the running file,
 * not by arithmetic: handle() now ends at :155 and the two box rules run :267-289.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    // The two ranges, re-measured after the severity comment landed inside handle()'s findings array.
    ['AuditRecordStates.php:44-151, the two box rules at :263-285',
        'AuditRecordStates.php:44-155, the two box rules at :267-289'],

    // Three noted rules become two, with the promotion stated where the reader would otherwise look for the
    // print rule and find it missing.
    ['while three are noted rather than failed because each counts a state somebody has already ruled on: printed papers attributable to nobody (§25 P-14), a Clinic box signed with no health assessment behind it, an ID box signed with no validated request behind it — with enrollment 53 standing in that last state, its contradictory request row deleted on 2026-10-11 and its signature left standing by ruling.',
        'while two are noted rather than failed because each counts a state somebody has already ruled on — a Clinic box signed with no health assessment behind it, and an ID box signed with no validated request behind it, which is enrollment 53 alone, its contradictory request row deleted on 2026-10-11 with its signature left standing by ruling. The third of those once-noted rules, printed papers attributable to nobody, was promoted to the failing kind on 2026-10-15: it had been a note only because 21 legacy rows sat in that state and pruning or attributing them was somebody’s call rather than a code defect (§25 P-14). That call is made, the rows are behind a manifest, and no paper this application prints leaves all three keys NULL, so a key-less row names a write path.'],

    // The history sentence still stands, but it described three notes; say so rather than let it read as two.
    ['A report tool counts those and leaves them. They measured 21, 7 and 8 on 2026-10-12;',
        'A report tool counts those and leaves them. The three notes measured 21, 7 and 8 on 2026-10-12;'],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-15-pass126.docx',
    $textEdits, [], in_array('--apply', $argv, true));
