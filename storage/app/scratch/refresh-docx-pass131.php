<?php

/**
 * Pass 131 — lane 38: the demo pipeline is now self-sufficient, and the document still said it was not.
 *
 * P 740 closes its account of `ems:audit:record-states` with "What this batch did not fix is stated where it
 * belongs: the tool still presumes its named demo students already exist, aborting on DEMO-2026-004 when they
 * do not, and it prints that failure while still exiting 0". On 2026-10-16 both halves of that sentence became
 * false, in the same file: the pipeline creates the two rows its own guard named (measured on a schema built
 * from CI's recipe — `✔ Base row created` twice, then twelve rules at zero), and its report now refuses the
 * success banner when it has a finding to report (measured against a planted overpayment: exit 1, banner
 * withheld, exit 0 again once the receipt was restored).
 *
 * The second edit is drift, not doctrine: `AssertNoSilentSkips` grew a third allowlist entry for the new
 * dataset check, which moved `handle()` and the list's own span. Both ranges were measured from the file with
 * `measure-method-span.php` rather than arithmetic — a range one line off still lands on a live line, which is
 * the failure the meaning sweep exists to catch.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['What this batch did not fix is stated where it belongs: the tool still presumes its named demo students '
        .'already exist, aborting on DEMO-2026-004 when they do not, and it prints that failure while still '
        .'exiting 0 — so anything gating on its exit code reads a pass.',
        'What that batch left open is closed on 2026-10-16, in the tool rather than in a note: it creates the two '
        .'rows it used to require — DEMO-2026-001 with the enrollment today’s receipt is filed against, '
        .'DEMO-2026-004 with the completed prior-term load the standing evidence is graded into — and it withholds '
        .'the success banner whenever its own desk report has a finding, exiting 1 instead of returning 0 over the '
        .'warning. A run against an empty schema now fills every desk queue the walkthrough reads, which is what '
        .'lets the MySQL job seed the demo itself, in a step between the two seeders and the legs; and one of those '
        .'legs is now a class that reads this command’s register line by line and refuses every count above zero '
        .'except the ruled record, checked by identity rather than by number '
        .'(DemoDatasetRecordStatesTest.php:112-143, the ruled exception at :146-166, the two base rows at :169-235). '
        .'The backup file stays what demo day opens: a self-seeded Juan Dela Cruz carries no admission, no '
        .'entrance-exam result and no validated ID, so the guide’s intake, examination and ID steps still belong to '
        .'the restored dataset, and what this tool guarantees on its own is only that its rows are reproducible and '
        .'hold no state a desk could not have produced.'],
    ['AssertNoSilentSkips.php:52-101, the allowlist at :39-50',
        'AssertNoSilentSkips.php:59-108, the allowlist at :39-57'],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-16-pass131.docx',
    $textEdits, [], in_array('--apply', $argv, true));
