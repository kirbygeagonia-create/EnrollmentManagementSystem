<?php

/**
 * Pass 40 — §25.4's print register, re-read against the source.
 *
 * The table opens by claiming every row was verified against the current source. Eleven rows
 * describe defects the print remediation closed, which is the same failure C-1 was: the register
 * telling a reader the build is behind the build. Each closed row is rewritten in the table's own
 * established shape (the P-9 and P-12 pattern): the finding becomes "Closed <when>, <what it does
 * now>", the evidence names the live file and line, and the consequence says what is left.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

config([
    'database.default' => 'mysql',
    'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.username' => 'root',
    'database.connections.mysql.password' => '',
]);
DB::purge('mysql');

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$esc = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

// Measured first: the closures below cite live facts, so check the two the rows lean on.
$facts = [
    'named requirement lines' => DB::table('clearancerequirements')->whereNotNull('requirementName')->count(),
    'unnamed requirement lines' => DB::table('clearancerequirements')->whereNull('requirementName')->count(),
    'print rows with no key at all' => DB::table('documentprintlog')
        ->whereNull('enrollmentId')->whereNull('studentId')->whereNull('blockId')->count(),
];
printf("live: %s\n", json_encode($facts));

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();
$original = $xml;

$cellText = function (string $x): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $x, $m);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1)));
};

$setCell = function (string $cell, string $text) use ($esc): string {
    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $stripped = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);

    return str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'.
        $esc(preg_replace('/\s+/', ' ', $text)).'</w:t></w:r></w:p>', $stripped);
};

preg_match_all('/<w:tbl>.*?<\/w:tbl>/s', $xml, $tables, PREG_OFFSET_CAPTURE);
$tables2 = [];
foreach ($tables[0] as $t) {
    preg_match('/<w:tr>.*?<\/w:tr>/s', $t[0], $first);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $first[0], $cs);
    if (array_map($cellText, $cs[0]) === ['ID', 'Finding', 'Evidence', 'Consequence', 'Class']) {
        $tables2[] = ['xml' => $t[0], 'at' => $t[1]];
    }
}
if ($tables2 === []) {
    fwrite(STDERR, "REFUSE: §25.4's table was not found by its header row\n");
    exit(1);
}
printf("%d table block(s) carry §25.4's header — the register is split across them\n\n", count($tables2));

/** [id, finding, evidence, consequence] — consequence '' means leave that cell as raised. */
$rows = [
    ['P-1',
        'Closed 2026-10-02 in the print-fidelity batch. The slip prints the student ID in the body of the paper, not only in the HTML title, and the number it carries is issued before the render rather than typed onto it.',
        'clearance-slip.blade.php:58 (the ID row; the title at :6 is beside it, not instead of it). The issuance row is minted by PrintService::recordIssue() before the view is built.',
        'Nothing outstanding for the ID. What a reader must still know about this paper is in P-10 and P-11 below, both also closed.'],
    ['P-3',
        'Closed 2026-10-02, and attributed 2026-10-03: printing the block and schedule writes its own issue row like every other desk print, and the row names the block it covers rather than borrowing an enrollment.',
        'BlockingController.php:572 calls PrintService::recordIssue(); documentprintlog.blockId is the block key added by the ruling 6 batch.',
        'Nothing for the log. The 21 legacy rows that carry no key at all are kept untouched by ruling 6, so §20.5 counts prints and does not reconstruct history from them.'],
    ['P-4',
        'Closed 2026-10-02: there is a subject-load template, and it is the one the subject-load print renders, so the document type in the log and the paper in the hand agree.',
        'PrintService.php:266-275 renders prints.subject-load; resources/views/prints/subject-load.blade.php.',
        'Nothing — a subject-load PDF is a subject load.'],
    ['P-5',
        'Closed 2026-10-02: the screen and the PDF print the same year-level string through one shared formatter, which is the fix that also stopped twelve other screens printing “3 Year”.',
        'PrintCertificate.jsx:47 (formatYearLevel) and enrollment-certificate.blade.php:56.',
        'Nothing. The formatter is the single place the wording lives, so the two renderings cannot drift apart again unnoticed.'],
    ['P-6',
        'Closed 2026-10-02: the number the print just issued is passed to the page and printed in its signature block, so the paper carries the number the log carries.',
        'RegistrarController::printCertificate passes documentNumber; PrintCertificate.jsx:125 prints it and shows an em dash only when there genuinely is none.',
        'Nothing.'],
    ['P-7',
        'Partly closed, and the remainder is named. The letterhead image is no longer a path to a file that does not exist: it is embedded as a data URI, which also removed the round trip into the dev server that once deadlocked the single-threaded print. What is still true is that the school name and the rest of the header read config defaults — config/settings.php does not exist, and no print view consults the settings table — so the printed identity of the school is a code default rather than a maintained record.',
        'clearance-slip.blade.php:30 (PrintAssets::logoDataUri, with onerror hiding) and :31 (config(…schoolName, default)).',
        'Open, and it is a data question: which office maintains the letterhead strings, and where the logo is uploaded. Until then every PDF says the same institution regardless of what an admin edits.'],
    ['P-8',
        'Closed 2026-09-30 and made real 2026-10-04: clearancerequirements carries a requirement name, the checklist is seeded from the named office list, and no line is generated without one.',
        'Migration 2026_09_30_1000000002_add_requirement_name_to_clearancerequirements_table.php:24; the seeder builds from ProvisionalClearanceRequirements::NAMES. Measured on live ems: '.$facts['named requirement lines'].' named lines, '.$facts['unnamed requirement lines'].' unnamed.',
        'Nothing. Each slip now prints what each line actually asks for, which is what made §16.4 readable.'],
    ['P-10',
        'Closed 2026-10-02: the slip takes course, year level and the log’s enrollment from the enrollment of the clearance period’s own term, so a slip issued under one term can no longer describe a different term’s load.',
        'clearance-slip.blade.php:69 reads $termEnrollment; ClearanceController.php:53-60 keys the lookup on studentId plus the period’s termId.',
        'Nothing for the derivation. The window-vs-term question that remains is the acceptance script’s §18 item 1 and is a ruling about which window a term owns, not a code defect.'],
    ['P-11',
        'Closed 2026-10-02: the stamp is derived from overallStatus — approved or waived prints SEAIT CLEARED, anything else prints HELD — NOT YET CLEARED.',
        'clearance-slip.blade.php:109-115.',
        'Nothing. A pending or partially approved clearance can no longer be stamped cleared on paper while the table above the stamp says otherwise.'],
    ['P-13',
        'Closed 2026-10-02: the certificate prints the student type in its own words (First Year, Transferee, Continuing, Shifter) and states the enrollment kind separately as First enrollment / Returning enrollment, so enrollmentType no longer stands in for the category the brief standardises.',
        'enrollment-certificate.blade.php:72 (Str::headline of studentType) and :80 (enrollmentType, labelled).',
        'Nothing.'],
    ['P-14',
        'Closed 2026-10-03 and sequenced 2026-10-04: one path mints every document number. PrintService::recordIssue() takes one above the highest number already issued in that document’s own scope, under a lock, and steps over the numbers the key-less legacy rows already hold, so a re-print continues the series instead of colliding with it.',
        'PrintService.php:105 with the uq_documentprintlog_issue index; the scope is per document type — clearanceSlip by student, blockSchedule by block, everything else by enrollment, falling back to the enrollment when no key was supplied.',
        'One limit stays, and it is the schema’s rather than the code’s: for the '.$facts['print rows with no key at all'].' rows that carry no key at all, MySQL treats NULLs as distinct, so the unique index cannot reject a repeat of those. New prints are safe by the sequencing rule.'],
    ['P-15',
        'Closed 2026-10-02: generatePdf() resolves an installed browser through the same locator the fidelity command uses and sets the Chrome path, so the desk’s PDF and the sample PDF are produced the same way.',
        'PrintService.php:22 and :37-38 (ChromiumLocator::locate, then setChromePath).',
        'Nothing for the code path. Production still needs the browser install DEPLOYMENT.md §6 names, or the print fails at runtime rather than at deploy time.'],
];

$byId = [];
foreach ($tables[0] as $t) {
    preg_match_all('/<w:tr>.*?<\/w:tr>/s', $t[0], $trs, PREG_OFFSET_CAPTURE);
    foreach ($trs[0] as $row) {
        $cells = [];
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row[0], $cs, PREG_OFFSET_CAPTURE);
        foreach ($cs[0] as $k => $c) {
            $cells[$k] = $c;
        }
        if (count($cells) !== 5) {
            continue;
        }
        // The register is split across two table blocks and only the first repeats the header,
        // so a row is identified by its P-nn id rather than by a header match.
        $id = $cellText($cells[0][0]);
        if (! preg_match('/^P-\d+$/', $id)) {
            continue;
        }
        if (isset($byId[$id])) {
            fwrite(STDERR, "REFUSE: row id {$id} appears in two places; the write would be ambiguous\n");
            exit(1);
        }
        $byId[$id] = ['cells' => $cells, 'at' => $row[1], 'tableAt' => $t[1]];
    }
}
ksort($byId, SORT_NATURAL);
printf("%d finding rows read from the register: %s\n\n", count($byId), implode(', ', array_keys($byId)));

$writes = [];
foreach ($rows as [$id, $finding, $evidence, $consequence]) {
    if (! isset($byId[$id])) {
        fwrite(STDERR, "REFUSE: §25.4 has no row {$id}\n");
        exit(1);
    }
    foreach ([1 => $finding, 2 => $evidence, 3 => $consequence] as $idx => $value) {
        if ($value === '') {
            continue;
        }
        [$cell, $cellAt] = $byId[$id]['cells'][$idx];
        $writes[] = [
            'at' => $byId[$id]['tableAt'] + $byId[$id]['at'] + $cellAt,
            'len' => strlen($cell),
            'to' => $setCell($cell, $value),
            'now' => preg_replace('/\s+/', ' ', $value),
            'cell' => $idx,
            'key' => 'p|'.$id,
            'label' => $id.' cell '.$idx,
        ];
    }
    printf("  %-5s %d cell(s)\n", $id, ($consequence === '') ? 2 : 3);
}

printf("\n%d cells to rewrite\n", count($writes));

if (! $apply) {
    echo "\nDRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass40.docx');
usort($writes, fn ($a, $b) => $b['at'] <=> $a['at']);
foreach ($writes as $w) {
    $xml = substr_replace($xml, $w['to'], $w['at'], $w['len']);
}

foreach (['<w:tbl>', '</w:tbl>', '<w:tr>', '</w:tr>', '<w:tc>', '</w:tc>'] as $tag) {
    $moved = substr_count($xml, $tag) - substr_count($original, $tag);
    if ($moved !== 0) {
        fwrite(STDERR, "REFUSE WRITE: {$tag} moved by {$moved}, expected 0\n");
        exit(1);
    }
}
echo "guard  containers unmoved; only cell text changed\n";
if (libxml_use_internal_errors(true) && simplexml_load_string($xml) === false) {
    fwrite(STDERR, "REFUSE WRITE: document.xml does not parse\n");
    exit(1);
}
echo "guard  document.xml parses\n";

// Re-read the table from the finished document and require every rewritten cell in its row.
preg_match_all('/<w:tbl>.*?<\/w:tbl>/s', $xml, $after, PREG_OFFSET_CAPTURE);
$rowsNow = [];
foreach ($after[0] as $t) {
    preg_match_all('/<w:tr>.*?<\/w:tr>/s', $t[0], $body);
    foreach ($body[0] as $b) {
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $b, $cc);
        $v = array_map($cellText, $cc[0]);
        if (count($v) === 5 && preg_match('/^P-\d+$/', $v[0])) {
            $rowsNow['p|'.$v[0]] = $v;
        }
    }
}
$verified = 0;
foreach ($writes as $w) {
    $got = $rowsNow[$w['key']][$w['cell']] ?? null;
    if ($got !== $w['now']) {
        fwrite(STDERR, "REFUSE WRITE: {$w['label']} reads \"".mb_substr((string) $got, 0, 50)."\", expected \"".mb_substr($w['now'], 0, 50)."\"\n");
        exit(1);
    }
    $verified++;
}
echo "guard  all {$verified} rewritten cells read their new text in their own row\n";

$z = new ZipArchive();
$z->open($src, ZipArchive::CREATE);
$z->addFromString('word/document.xml', $xml);
$z->close();

// Verify the bytes landed: an earlier pass printed WROTE over a rename that had failed because
// Word had the file locked.
$z4 = new ZipArchive();
if ($z4->open($src) !== true) {
    fwrite(STDERR, "REFUSE: the written file cannot be re-opened\n");
    exit(1);
}
$back = $z4->getFromName('word/document.xml');
$z4->close();
if ($back !== $xml) {
    fwrite(STDERR, "REFUSE: the file on disk is not what was written\n");
    exit(1);
}
echo "guard  the file on disk holds the edited document.xml byte for byte\n";

echo "\nWROTE {$src}\n";
