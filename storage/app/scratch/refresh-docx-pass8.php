<?php

/**
 * Pass 8 — the documentNumber claim I wrote in pass 4 overreached, and live data proves
 * it: recordIssue() sequences per (enrollmentId, documentType), but a clearance slip
 * whose student has no enrollment for the period's term, and every block-schedule print,
 * go in with enrollmentId NULL — and MySQL treats NULLs as distinct, so
 * uq_documentprintlog_issue cannot reject a repeat. Queried from ems on 2026-10-02:
 * 19 clearanceSlip rows share one NULL sequence, 2 of 4 clearances are still in that
 * position, and both blockSchedule rows are NULL.
 */

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$tmp = sys_get_temp_dir().'/ems-docx8-'.uniqid().'.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    ['Done 2026-10-01. PrintService::recordIssue() is the only writer:',
        'Partly done 2026-10-01, with a remainder that is the Registrar’s to rule on. PrintService::recordIssue() is now the only writer: it sequences per (enrollmentId, documentType) under lockForUpdate(), and uq_documentprintlog_issue rejects a repeat — but only while an enrollment can be attributed. A clearance slip whose student has no enrollment for the clearance period’s term, and every block-schedule print, go in with enrollmentId NULL, and MySQL treats NULLs as distinct, so the index cannot reject a repeat and those numbers do repeat across different students and different blocks. Read from ems on 2026-10-02: 19 clearanceSlip rows share one NULL sequence, 2 of the 4 clearances on file are still in that position, and both blockSchedule rows are NULL. Giving those documents one honest number needs a key the row actually holds — studentId for a slip, blockId for a roster — which is a schema decision, not a code one.',
        1, '26 documentNumber row: state the exception'],
    ['yes — recordIssue() (ClearanceController.php:312)',
        'yes — recordIssue() (ClearanceController.php:312), though the row carries a NULL enrollment whenever the student has no enrollment for the period’s term (§26)',
        1, '25 slip audit row: name the NULL case'],
    ['yes since 2026-10-01 — recordIssue() writes the row the desk route used to skip (BlockingController.php:571-575)',
        'yes since 2026-10-01 — recordIssue() writes the row the desk route used to skip (BlockingController.php:571-575), attributed to the first seated student, or NULL when the block seats no one',
        1, '25 block audit row: name the representative case'],
];

$fails = 0;
foreach ($edits as [$needle, $to, $want, $label]) {
    $n = substr_count($xml, $needle);
    if ($n !== $want) {
        echo "FAIL {$label}: found {$n}x, want {$want}x\n";
        $fails++;
        continue;
    }
    $xml = str_replace($needle, $to, $xml);
    echo "ok   {$label} ({$n}x)\n";
}

if ($fails > 0) {
    fwrite(STDERR, "not writing\n");
    exit(1);
}
if (! $apply) {
    echo "dry run — pass --apply to write\n";
    exit(0);
}
copy($src, $tmp);
$z2 = new ZipArchive();
$z2->open($tmp);
$z2->addFromString('word/document.xml', $xml);
$z2->close();
rename($tmp, $src);
echo "APPLIED\n";
