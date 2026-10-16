<?php

/**
 * Pass 4 — the print chapter: §25's document matrix and §26's remediation table.
 *
 * Both were written when a desk print meant a React page and the PDF path was a queue
 * job nothing dispatched. Every print route now renders a real PDF and writes its own
 * issue row, so each claim here was re-read from the source before it was written:
 * PrintService (slip :59, certificate :115, class cards :137, subject load :195, block
 * schedule :219, enrollment form :239, recordIssue :86-112), the print and download
 * actions (Registrar :292/:314/:344/:368/:380/:406, Clearance :303/:324, Blocking
 * :559/:585), the routes (web.php:108-109, :123-124, :131-136) and the templates.
 */

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$tmp = sys_get_temp_dir().'/ems-docx4-'.uniqid().'.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;

function esc(string $s): string
{
    return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
}

function spanFor(string $xml, string $anchor, string $open, string $close): array
{
    $from = 0;
    while (($pos = strpos($xml, $anchor, $from)) !== false) {
        $start = strrpos(substr($xml, 0, $pos), $open);
        $end = strpos($xml, $close, $pos);
        if ($start !== false && $end !== false
            && strpos(substr($xml, $start, $pos - $start), $close) === false) {
            return [$start, $end + strlen($close)];
        }
        $from = $pos + strlen($anchor);
    }
    return [false, false];
}

function cellEdit(string &$xml, string $anchor, string $cellPrefix, string $to, string $label): void
{
    global $fails;
    [$s, $e] = spanFor($xml, $anchor, '<w:tr', '</w:tr>');
    if ($s === false) {
        $fails++;
        echo "cell FAIL {$label}: row for anchor not found\n";
        return;
    }
    $row = substr($xml, $s, $e - $s);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, PREG_OFFSET_CAPTURE);
    $hits = [];
    foreach ($cm[0] as $i => $cell) {
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell[0], $tm);
        if (strncmp(implode('', $tm[1]), $cellPrefix, min(strlen($cellPrefix), 50)) === 0) {
            $hits[] = $i;
        }
    }
    if (count($hits) !== 1) {
        $cells = [];
        foreach ($cm[0] as $c) {
            preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $c[0], $tm);
            $cells[] = str_replace("\n", '\\n', substr(implode('', $tm[1]), 0, 22));
        }
        $fails++;
        echo "cell FAIL {$label}: matched ".count($hits)."; row holds: ".implode(' ~ ', $cells)."\n";
        return;
    }
    $cell = $cm[0][$hits[0]][0];
    if (substr_count($cell, '<w:p>') !== 1) {
        $fails++;
        echo "cell FAIL {$label}: cell holds ".substr_count($cell, '<w:p>')." paragraphs\n";
        return;
    }
    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $newCell = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $newCell = str_replace('</w:p>', '<w:r>'.$pr[0].'<w:t xml:space="preserve">'.esc($to).'</w:t></w:r></w:p>', $newCell);
    $row = substr_replace($row, $newCell, $cm[0][$hits[0]][1], strlen($cell));
    $xml = substr($xml, 0, $s).$row.substr($xml, $e);
    echo "cell ok   {$label}\n";
}

// ------------------------------------------------------------------ §25 matrix, per row

$matrix = [
    'Enrollment Form' => [
        ['PrintService::printEnrollmentForm()',
            'PrintService::printEnrollmentForm() (PrintService.php:239) — still no caller: no route and no desk action prints the enrollment form itself'],
        ['only inside that unreachable method',
            'only inside that unreachable method — the form has no issue row because nothing issues it'],
    ],
    'Enrollment Certificate' => [
        ['GET /registrar/',
            'GET /registrar/{enrollment}/print/certificate (web.php:131) and its PDF sibling (web.php:132)'],
        ['Registrar/PrintCertificate',
            'Registrar/PrintCertificate page (RegistrarController.php:292) — the download route answers the same screen with the PDF (RegistrarController.php:368)'],
        ['yes (', 'yes — recordIssue() on both paths (RegistrarController.php:302)'],
        ['Yes — React page only', 'Yes — page, PDF download, audit row'],
    ],
    'Class Card' => [
        ['GET /registrar/',
            'GET /registrar/{enrollment}/print/class-cards (web.php:133) and one PDF per subject (web.php:134)'],
        ['Registrar/PrintClassCards',
            'Registrar/PrintClassCards — one page, every subject (RegistrarController.php:314); downloadClassCard() serves one card (:380)'],
        ['yes, one row per subject',
            'yes — one issue row for the print, written per screen (RegistrarController.php:329)'],
        ['Yes — React page only', 'Yes — page, PDF download, audit row'],
    ],
    'Subject Load' => [
        ['GET /registrar/',
            'GET /registrar/{enrollment}/print/subject-load (web.php:135) and its PDF sibling (web.php:136)'],
        ['Registrar/PrintSubjectLoad',
            'Registrar/PrintSubjectLoad (RegistrarController.php:344); downloadSubjectLoad() serves the PDF (:406)'],
        ['printSubjectLoad()',
            'PrintService::printSubjectLoad() (PrintService.php:195) — renders prints.subject-load, its own template, not the certificate’s'],
        ['yes (', 'yes — recordIssue() (RegistrarController.php:357)'],
        ['Yes — React page only', 'Yes — page, PDF download, audit row'],
    ],
    'Clearance Slip' => [
        ['GET /clearance/',
            'GET /clearance/{clearance}/print (web.php:108) and its PDF sibling (web.php:109)'],
        ['Clearance/PrintSlip',
            'Clearance/PrintSlip (ClearanceController.php:303); downloadSlip() serves the PDF (:324)'],
        ['printClearanceSlip()',
            'PrintService::printClearanceSlip() (PrintService.php:59) — called by both the screen and the download'],
        ['yes (', 'yes — recordIssue() (ClearanceController.php:312)'],
        ['Yes — React page only', 'Yes — page, PDF download, audit row'],
    ],
    'Blocking/PrintSchedule' => [
        ['GET /blocking/',
            'GET /blocking/{block}/print (web.php:123) and its PDF sibling (web.php:124)'],
        ['Blocking/PrintSchedule',
            'Blocking/PrintSchedule (BlockingController.php:559); downloadBlockSchedule() serves the PDF (:585)'],
        ['printBlockSchedule()',
            'PrintService::printBlockSchedule() (PrintService.php:219) — called by both the screen and the download'],
        ['no — the desk route logs nothing',
            'yes since 2026-10-01 — recordIssue() writes the row the desk route used to skip (BlockingController.php:571-575)'],
        ['Yes — but untracked', 'Yes — page, PDF download, audit row'],
    ],
];

foreach ($matrix as $anchor => $edits) {
    foreach ($edits as [$prefix, $to]) {
        cellEdit($xml, $anchor, $prefix, $to, "25 [{$anchor}] ".substr($prefix, 0, 22));
    }
}

// ------------------------------------------------------------------ §26 remediation table

$remedies = [
    ['Print the student ID and the correct term on the slip PDF', 'add a Student ID row',
        'Done 2026-10-01. The slip prints the student’s school ID and takes course, year level and enrollment from the enrollment that matches clearancePeriod.termId, not from student.enrollments[0] — so a returning student’s slip names the term the clearance belongs to.'],
    ['Give the slip its status gate', 'wrap the stamp in a condition on overallStatus',
        'Done 2026-10-01. The stamp is wrapped in a condition on overallStatus (clearance-slip.blade.php:112): approved or waived clears, anything else stays pending on the paper as it does on the record.'],
    ['Log block-schedule prints', 'create the documentprintlog row',
        'Done 2026-10-01. The desk route now creates the documentprintlog row the other desks created, through PrintService::recordIssue() (BlockingController.php:571-575).'],
    ['Make the letterhead read real settings', 'ship a config',
        'Done 2026-10-01. The blades read config("settings.schoolName") and the address and phone rows beside it, so the letterhead is the installed school’s, not a string typed into a template.'],
    ['Name the certificate’s category correctly', 'print studentType',
        'Done 2026-10-01. The certificate prints studentType and academicStanding beside enrollmentType (enrollment-certificate.blade.php:72-80), which is the pair the Registrar actually certifies.'],
    ['Give a clearance requirement text', 'add a name',
        'Done 2026-10-01. clearancerequirements.requirementName carries the obligation and the slip prints it once, so a line reads "Return borrowed books" rather than an office name.'],
    ['controllers above', 'sequence per (enrollment, documentType)',
        'Done 2026-10-01. PrintService::recordIssue() is the only writer: it sequences per (enrollmentId, documentType) under lockForUpdate() and the unique index uq_documentprintlog_issue rejects a collision, so the number means "the nth time this document was issued" and cannot mean two things at once.'],
];

foreach ($remedies as [$anchor, $prefix, $to]) {
    cellEdit($xml, $anchor, $prefix, $to, '26 remediation: '.substr($anchor, 0, 34));
}

echo "\n".($fails === 0 ? "all targets resolved" : "{$fails} failure(s)")."\n";
if ($fails > 0 || ! $apply) {
    echo $fails === 0 ? "dry run — pass --apply to write\n" : "not writing\n";
    exit($fails === 0 ? 0 : 1);
}
copy($src, $tmp);
$z2 = new ZipArchive();
$z2->open($tmp);
$z2->addFromString('word/document.xml', $xml);
$z2->close();
rename($tmp, $src);
echo "APPLIED\n";
