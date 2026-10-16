<?php

/**
 * Pass 127 — the retired rows were not stale history, they were the seeder's own evidence, and the seeder
 * has been changed to write the two acts behind every box it signs.
 *
 * What the day measured. The record-state review found seven past-term enrollments holding a completed Clinic
 * box with no assessment and an ID box with no validated request; the owner ruled to retire them, and the
 * report went to 0. `tools/seed_full_demo_pipeline.php` was re-run twenty minutes later (it is a standing gate
 * — AGENTS.md asks for it after any change) and the notes read 5 and 6 again: enrollments 1063-1067, the same
 * five students on the same term 2, created in the same second. The G-2 step at :1518 seats a completed year
 * behind a returning student as the evidence for their year level, and its guard asks only whether the student
 * holds a record on that term — a dropped one holds nothing, so dropping freed the seat and the tool refilled
 * it, signing every box on the way (`$completeWorkflow`).
 *
 * So pass 124/126's "retired through the Registrar drop" was an incomplete explanation of why the note is 0,
 * and the walkthrough's item 8 called these rows a standing limit. Both are corrected here and in DEPLOYMENT.md
 * §8 step 7. The fix is in the generator: `attest_completed_desks()` writes the health assessment (§14R.2) and
 * the validated card request (§16) for each record the tool completes, dated into the record's own term rather
 * than the run, skipping any act a desk already wrote. Rehearsed twice on `ems_lane36_rehearse` — first run
 * attested the five, second run reported every prior-term record already holding its act — and live `ems` then
 * read the Clinic rule 0 and the ID rule 1, which is enrollment 53 by ruling.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['the seven past-term records the demo pipeline had walked through the Clinic and ID boxes before it wrote the acts that sign them were retired through the Registrar drop.',
        'the seven past-term records whose Clinic and ID boxes the demo pipeline had signed before it wrote the acts behind them were retired through the Registrar drop. Retiring them did not close the note on its own: those rows are the seeder’s own year-level evidence (G-2), its guard asks only whether the student still holds a record on that term, and a dropped one holds nothing — so the next run of the tool re-created five of the seven within the hour, signing every box on the way. Since 2026-10-15 the seeder writes the act behind each box it signs (the assessment that signs the Clinic box and the validated request that signs the ID box, dated into the record’s own term and skipped where a desk already wrote one), which is why the count is closed rather than merely quiet for an afternoon.'],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-15-pass127.docx',
    $textEdits, [], in_array('--apply', $argv, true));
