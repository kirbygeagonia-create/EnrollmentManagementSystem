<?php

/**
 * Pass 135 — §20.5's demo-pipeline sentence still said the guide's ID step belongs to the restored dataset.
 *
 * Lane 39 measured what Documentation/ems-demo-backup.sql actually returns, by loading it into a scratch
 * database and walking the guide's §3 as 19 preconditions (storage/app/scratch/lane39-guide-preconditions.php,
 * and lane39-dump-contents.php for the dump itself). Three things this document asserted or implied were wrong:
 *
 *   1. "the guide's … ID steps still belong to the restored dataset". The restored dataset holds TWO idrequest
 *      rows and exactly zero validated-with-photo ones. Its only validated row is idrequest 53 — status
 *      validated, cardPhotoPath NULL, no validator — the row the owner ruled deleted on 2026-10-11, which a
 *      restore puts back. The ID desk's showable record is Kevin Santos (DEMO-2026-017), and it is *this tool*
 *      that writes his validation (tools/seed_full_demo_pipeline.php:2592-2632), so the ID step belongs to the
 *      seeder, not to the backup.
 *   2. "The backup file stays what demo day opens" — true, but one command does not open it. Loaded alone the
 *      snapshot walks 10 of 19, because 10 of its 11 enrollments sit on the retired term 18; its 63 tables
 *      predate 19 migrations and re-create the retired `studentids` while leaving no `shiftingrequests`; and it
 *      brings back 14 records `ems:audit:record-states` calls impossible. migrate + db:seed +
 *      DevReferenceDataSeeder + this tool reach the 18 of 19 the live box carries, and §5 of the guide now
 *      prints that order.
 *   3. The seat collision the relocation could produce. The base rows were created before the G-9 move, so on a
 *      term-18 database the run created a Juan on the demo term, dragged him back to the prior term with the
 *      history it was moving out, and collided Liza's new base row with the row it moved onto that term —
 *      "seat collision: student 56 holds 2 active enrollments in term 10". The creation now runs after the
 *      relocation (tools/seed_full_demo_pipeline.php:346-417, the G-9 block at :271-344): a restored database
 *      prints no "Base row created" line and no collision, and the from-empty path is unchanged at 2 rows, 0
 *      findings, exit 0.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['The backup file stays what demo day opens: a self-seeded Juan Dela Cruz carries no admission, '
        .'no entrance-exam result and no validated ID, so the guide’s intake, examination and ID steps still '
        .'belong to the restored dataset, and what this tool guarantees on its own is only that its rows are '
        .'reproducible and hold no state a desk could not have produced.',
        'The backup file stays what demo day opens, and §5 of the guide now prints the five-command order it '
        .'needs rather than one: measured on a copy of that 2026-09-20 snapshot, loaded alone it walks 10 of the '
        .'guide’s 19 records, because 10 of its 11 enrollments still sit on the retired term 18, and only '
        .'migrate, both seeders and this tool bring it to the 18 of 19 the live box carries. A self-seeded Juan '
        .'Dela Cruz carries no admission and no entrance-exam result, so the guide’s intake and examination '
        .'steps still belong to the restored dataset. Its ID step belongs to neither: the backup holds two ID '
        .'requests and no validated one with a photo — its only validated row is the contradictory request the '
        .'owner ruled deleted on 2026-10-11, which a restore puts back — while the record the desk can show is '
        .'Kevin Santos (DEMO-2026-017), whose validated request and photo this tool itself writes. What this '
        .'tool guarantees on its own is that its rows are reproducible and hold no state a desk could not have '
        .'produced.'],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-16-pass135.docx',
    $textEdits, [], in_array('--apply', $argv, true));
