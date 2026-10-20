<?php

/**
 * Lane 55 — third pre-flight: count every token and prose needle pass 160 intends to bind, in the XML bytes.
 *
 * Two failure classes are measured here rather than discovered by the writer: a citation token that also occurs
 * somewhere else in the document (so a global replace would corrupt an unrelated pointer), and a sentence copied
 * out of the flat dump that is not contiguous in the bytes because Word split it across two <w:t> runs.
 *
 * Usage: php storage/app/scratch/lane55-preflight-tokens.php
 */

$root = dirname(__DIR__, 3);
$zip = new ZipArchive();
if ($zip->open($root.'/Documentation/EMS Complete Documentation.docx') !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

echo "--- citation tokens ---\n";
foreach ([
    'BlockingController.php:457-538', 'BlockingController.php:469-476', 'BlockingController.php:475',
    'BlockingController.php:573-601', 'BlockingController.php:573', 'BlockingController.php:586',
    'BlockingController.php:594', 'AuditRecordStates.php:309-314', 'AuditRecordStates.php:324-333',
    ':467-478', ':461', ':433', ':588', ':193-252', ':365+', ':339-363', '415-495', ':497-530',
    ':78-91', ':455-458', ':234-320', ':205-232',
] as $t) {
    printf("%2d  %s\n", substr_count($xml, $t), $t);
}

echo "\n--- prose needles ---\n";
foreach ([
    'Blocking has stored no class sessions',
    'the conflict check (§10.4) has never run on seeded data',
    'conflicts against instructor, room, and time are detected before saving',
    'Add a schedules row per subject with an instructor, room, and meeting days/times',
    'the eligibility filter and the per-row guard',
    'The next uncompleted workflow box must be the Blocking box',
    'the room total is weighed before the write',
    'alongside the block total',
    'room check at',
    'no BR number in code',
    'a student with no block assignment is simply unscheduled',
    'Log block-schedule prints',
    'The paper looks identical to a logged print',
    'the one writer behind all of them',
    'Blocking/PrintSchedule',
    'and the one writer PrintService',
    'calls PrintService::recordIssue()',
    'downloadBlockSchedule()',
    'locks the timetable against every schedule mutation',
    'Stamp blockId on all of the',
    'A correction, not a workflow regression',
    'Room capacity check when assigning students',
    'the next uncompleted one',
] as $p) {
    printf("%2d  %s\n", substr_count($xml, $p), mb_substr($p, 0, 74));
}
