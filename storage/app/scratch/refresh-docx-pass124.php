<?php

/**
 * Pass 124 — lane 36: the 21 key-less print rows were pruned and 7 enrollments were retired, so six
 * passages that state them as a present fact are now false, and one citation names the wrong register.
 *
 * What changed under the owner's rulings of 2026-10-15:
 *   - the 21 `documentprintlog` rows carrying no enrollmentId, studentId or blockId were deleted, each first
 *     written to a manifest behind a verified dump (`prune-unattributable-print-rows.php`, mutation-proved);
 *   - the seven superseded past-term enrollments were dropped through `RegistrarController::drop`, so the
 *     Clinic-box-without-record rule fell from 7 to 0 and the ID-box rule from 8 to 1 (enrollment 53 alone);
 *   - both rules now exclude a dropped enrollment, because a retired record is not a decision waiting to be
 *     made and a count that can never reach 0 trains the operator to skim the number that means work.
 *
 * So every "the 21 rows are kept", "remain outside it", "cannot reject a repeat of those" is a claim about
 * data that is gone. Each is rewritten to the standing rule rather than the retired rows — the schema's
 * NULL-distinct limit is still true of any key-less row, and that is the half worth keeping.
 *
 * The mis-citation: `AuditRecordStates.php` and DEPLOYMENT.md both attribute the key-less-print hazard to
 * "§28 G-9". §28's G-9 is the demo term offering no curriculum subject at the levels the students sat
 * (row 2526, closed by ruling 1); the print-log limit is §25 P-14 — which is how the document cites it
 * itself in §27's BR8 row. §18's command inventory carried the same wrong reference, and is corrected here.
 *
 * `:263-280` moved: AuditRecordStates.php grew by six lines when both box rules gained their status guard,
 * so the two box methods now run to :285. Measured with the line sweep, not arithmetic.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    // §20.5 command inventory: the citation, then the register the three noted rules belong to.
    ['two box rules at :263-280', 'two box rules at :263-285'],
    ['printed papers attributable to nobody (§28 G-9), the past-term records the demo pipeline had walked through the Clinic and ID boxes before it wrote the acts that sign them, and enrollment 53, whose contradictory request row was deleted on 2026-10-11 with its signature left standing.',
        'printed papers attributable to nobody (§25 P-14), a Clinic box signed with no health assessment behind it, an ID box signed with no validated request behind it — with enrollment 53 standing in that last state, its contradictory request row deleted on 2026-10-11 and its signature left standing by ruling.'],
    ['A report tool counts those and leaves them.',
        'A report tool counts those and leaves them. They measured 21, 7 and 8 on 2026-10-12; on live ems they read 0, 0 and 1 on 2026-10-15, because the 21 key-less print rows were pruned behind a manifest and the seven past-term records the demo pipeline had walked through the Clinic and ID boxes before it wrote the acts that sign them were retired through the Registrar drop. Both box rules now exclude a dropped enrollment — a retired record is not work any desk will pick up, and a note that can never reach 0 teaches the operator to skim the one number that means work — so a non-zero count there is a write path, not the demo past.'],

    // §25.4 P-3: the consequence column.
    ['The 21 legacy rows that carry no key at all are kept untouched by ruling 6, so §20.5 counts prints and does not reconstruct history from them.',
        'The 21 legacy rows that carried no key at all were pruned on 2026-10-15 under the owner’s record-state ruling, each written to a manifest before it was deleted, so §20.5 counts prints and does not reconstruct history from them.'],

    // §25.4 P-14: the consequence column.
    ['for the 21 rows that carry no key at all, MySQL treats NULLs as distinct, so the unique index cannot reject a repeat of those. New prints are safe by the sequencing rule.',
        'for any row whose key is NULL, MySQL treats NULLs as distinct, so the unique index cannot reject a repeat among those. The 21 such rows live ems carried were pruned on 2026-10-15, so the exception sits on no data today; new prints are safe by the sequencing rule, and a key-less row appearing now is a print logged without a key — a write path to fix, not legacy data to tolerate.'],

    // §25.8: the numbering paragraph.
    ['the 21 legacy rows with no key at all remain outside it, because MySQL treats their NULLs as distinct, which is why they are reserved rather than repaired (ruling 6).',
        'any row with no key at all stays outside it, because MySQL treats their NULLs as distinct — which is why the 21 such rows were reserved rather than repaired under ruling 6, and were pruned on 2026-10-15 rather than left to keep the exception alive.'],

    // §26.4 requirements row (BR8).
    ['What the schema cannot reject is a repeated documentNo among the 21 legacy rows whose key is NULL, because MySQL treats NULLs as distinct (§25 P-14).',
        'What the schema cannot reject is a repeated documentNo among any rows whose key is NULL, because MySQL treats NULLs as distinct (§25 P-14) — and the 21 legacy rows in that condition were pruned on 2026-10-15, so nothing sits there now.'],

    // §28.4 change-impact row.
    ['stepping over the numbers the key-less legacy rows hold. What the schema still cannot reject is a repeat among those 21 NULL-keyed rows, because MySQL treats NULLs as distinct.',
        'stepping over the numbers any key-less row holds. What the schema still cannot reject is a repeat among NULL-keyed rows, because MySQL treats NULLs as distinct; the 21 such rows live ems held were pruned on 2026-10-15, so that condition now has no rows behind it.'],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-15-pass124.docx',
    $textEdits, [], in_array('--apply', $argv, true));
