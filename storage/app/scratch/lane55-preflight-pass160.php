<?php

/**
 * Pass 160 pre-flight — count every needle in word/document.xml before the pass is run.
 *
 * The pass writes through raw-byte offsets, so a needle that lives across two <w:t> runs would be invisible to
 * it and reported as "missing". Reading the same bytes here means a dry run never has to discover that.
 * Read-only: opens the archive, counts, closes.
 *
 * Usage: php storage/app/scratch/lane55-preflight-pass160.php
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
    '>G-24<',
    'lane 53 added G-21 and G-22, and lane 54 added G-23 and G-24, so §28.3 holds 41 data rows',
    'G / D / U / X / N',
    '>41<',
    'fourteen rules over idrequests, workflowsteps, enrollmentworkflow, enrollments, clinicrecords, notifications, documentprintlog, studentassessments, charges and enrollmentstatushistory — a validated request',
    'a receipt on an account that never entered paid — each counted',
    'runs fourteen rules over these tables and reads 0 on every one of them',
    '`ems:audit:record-states` reads 0 across all fourteen rules',
    'The suite stands at 763 tests and 5164 assertions',
    'fourteen rules',
    'The document itself was swept by line, by meaning and by §-reference after every pass in this batch.',
    // ---- the register row's own §-reference cell
    '§28.3 G-23, §28.3 G-24; found 2026-10-19',
];

foreach ($needles as $n) {
    printf("%2d  %s\n", substr_count($xml, $n), mb_substr(str_replace("\n", ' ', $n), 0, 90));
}

echo "\n--- pointers into the two files this batch edited ---\n";
$pointers = [
    'BlockingController.php:469-476',
    'BlockingController.php:457-538',
    'BlockingController.php:475',
    'BlockingController.php:573-601',
    'BlockingController.php:573',
    'BlockingController.php:586',
    'BlockingController.php:594',
    ', room check at :467-478',
    '( :467-478 ), next to the block total ( :461 )',
    ':467-478',
    ':461',
    'cap at :433',
    'serves the PDF ( :588 )',
    'AuditRecordStates.php:46-167, the two box rules at :279-301',
    'AuditRecordStates.php:309-314',
    'AuditRecordStates.php:324-333',
];

foreach ($pointers as $p) {
    printf("%2d  %s\n", substr_count($xml, $p), $p);
}

echo "\n--- the sentences the bare continuations sit in (XML bytes, 90 chars around) ---\n";
foreach ([':467-478', ':461', ':433', ':588'] as $tok) {
    $off = 0;
    while (($at = strpos($xml, $tok, $off)) !== false) {
        printf("  %-9s @%-9d …%s…\n", $tok, $at, preg_replace('/\s+/', ' ', mb_substr($xml, max(0, $at - 78), 150)));
        $off = $at + 1;
    }
}
