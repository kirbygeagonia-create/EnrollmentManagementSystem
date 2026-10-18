<?php

/**
 * Pass 143 — §1.1's revision row gains lane 46, and the suite figure is read from the whole run again.
 *
 * `php artisan test` after lane 46: Tests: 736 passed (4629 assertions), Duration: 1453.48s, exit 0. The row
 * still carried 731 / 4602, which was true of the build before the five new cases and is now a count of a build
 * that no longer exists — the same drift the row itself complains about elsewhere.
 *
 * The lane-46 prose is written from the measurements in G-14 and G-15: students.status is
 * enum('active','inactive') and live `ems` holds 24 students, all active; no page, guide or script sends `stage`
 * to exam.index or exam.results; the checker's first run judged 27 bindings and its corrected run 55.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

docxApply(
    $src,
    $root.'/storage/app/scratch/docx-backup-2026-10-17-pass143.docx',
    [
        ['The suite stands at 731 tests and 4602 assertions',
            'The suite stands at 736 tests and 4629 assertions'],

        ['The document itself was swept by line, by meaning and by §-reference after every pass in this batch.',
            'Lane 46 then asked what no earlier lane had: does a filter a desk sets reach the query, and does '
            .'the screen say what it applied? Two screens were lying. The student directory sent `status` while '
            .'StudentController::index consulted only `search`, so any choice listed every student and the '
            .'dropdown reset on the next render — and two of its four options, graduated and dropped, are values '
            .'`students.status` cannot hold: the column is `enum(\'active\',\'inactive\')`, the live directory is '
            .'24 rows and every one is active. Every row of the examination desk links to the results screen with '
            .'`?search=` a School ID that results() never read, while it did read a `stage` key no screen sends '
            .'any more, so the click opened the whole list under a URL promising one student. Both were ruled and '
            .'fixed the same day, a person record stopped being cast with the staff account’s vocabulary '
            .'(App\\Enums\\StudentStatus), and five cases pin them — each proved by deleting one branch and '
            .'watching the case go red. The class is machine-checked now by lane46-filter-contract.php, which '
            .'reports DEAD-SEND, NO-RETURN, FICTION and NO-ROUTE; its first version judged 27 bindings and '
            .'cleared the tree because it read only `route(\'x\', {…})` link objects and never '
            .'`router.get(route(\'x\'), {…})`, the shape where a desk’s filters actually live, so the corrected '
            .'run judges 55 and its seven mutants include the two proving that its scope flag narrows the scan '
            .'and that an empty scan refuses rather than passing. §28.3 gained G-14 and G-15, and recounting the '
            .'table exposed that §29.4’s family row had been left reading 28 while the sentence beside it said 30 '
            .'— the document contradicting itself for three passes, because a row that grows never edits its '
            .'neighbour. The document itself was swept by line, by meaning and by §-reference after every pass in '
            .'this batch.'],
    ],
    [],
    $apply
);
