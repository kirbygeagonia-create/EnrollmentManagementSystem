<?php

/**
 * Lane 56 — count every needle pass 161 intends to bind, in word/document.xml, before the pass is run.
 *
 * Same reason as lane 55's: the writer searches raw bytes, and Word splits a sentence across <w:t> runs wherever
 * styling changes, so a needle copied out of the flat dump can be absent from the bytes that must carry it.
 * Read-only: opens the archive, counts, closes.
 *
 * Usage: php storage/app/scratch/lane56-preflight-pass161.php
 */

$root = dirname(__DIR__, 3);
$zip = new ZipArchive();
if ($zip->open($root.'/Documentation/EMS Complete Documentation.docx') !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$needles = [
    'The suite stands at 769 tests and 5186 assertions',
    'sixteen rules where it ran fourteen',
    'runs sixteen rules over these tables and reads 0 on every one of them',
    '`ems:audit:record-states` reads 0 across all sixteen rules',
    'sixteen rules over idrequests, workflowsteps, enrollmentworkflow, enrollments, clinicrecords, notifications, documentprintlog, studentassessments, charges, enrollmentstatushistory, schedulemeetings, schedules and blocks — a validated request',
    'a receipt on an account that never entered paid, two schedules of one block at one hour, and a room or an officer in two schedules at one hour — each counted',
    '`ems:audit:record-states` runs sixteen rules over these tables and reads 0 on every one of them, because none asks whether a receipt’s own status agrees with the balance it left.',
    'Left OPEN and not changed here: closing it is the register’s usual pair',
    'Owner — mark the seeded receipt `partial` in `record_demo_payment()` (tools/seed_full_demo_pipeline.php:1429-1447) and the hand-written block above it, add the rule to `ems:audit:record-states`, or leave the demo’s installment as a receipt that reads settled and say so in the guide.',
    'because the tool never writes a `partial` row itself (that is §28.3 G-24) and live `ems` held none on 2026-10-18, so the divergence needed a desk act to exist.',
    'The document itself was swept by line, by meaning and by §-reference after every pass in this batch.',
    ':205-232',
    ':234-320',
];

foreach ($needles as $n) {
    printf("%2d  %s\n", substr_count($xml, $n), mb_substr(str_replace("\n", ' ', $n), 0, 88));
}
