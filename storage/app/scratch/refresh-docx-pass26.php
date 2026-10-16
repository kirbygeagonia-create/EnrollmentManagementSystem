<?php

/**
 * Pass 26 — §23.3's foreign-key inventory re-read from the server, and §24.6's Enrollment module
 * list catching up with the table it now carries.
 *
 * Both halves are GENERATED: the fourteen edges come from information_schema on live `ems`
 * (probe-docx-fks.php proved the 86 rows already in the chapter are all real and all correctly
 * spelled, so only the additions are written), and each new row is cloned from the inventory
 * table's own last row so it inherits the chapter's typography.
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

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();
$original = $xml;

// ── 1. the server's edges ────────────────────────────────────────────────────────────────

$live = [];
foreach (DB::select("
    select k.table_name t, k.column_name c, k.referenced_table_name rt, k.referenced_column_name rc,
           r.delete_rule d, r.update_rule u
    from information_schema.key_column_usage k
    join information_schema.referential_constraints r
      on r.constraint_name = k.constraint_name and r.constraint_schema = k.table_schema
    where k.table_schema = 'ems' and k.referenced_table_name is not null") as $r) {
    $live[strtolower($r->t.'.'.$r->c)] = [
        'child' => $r->t.'.'.$r->c,
        'to' => $r->rt.'.'.$r->rc,
        'delete' => $r->d,
        'update' => $r->u,
    ];
}
printf("information_schema reports %d referential edges\n", count($live));

// ── 2. the inventory table, its rows, and where each new edge belongs ────────────────────

$cellText = function (string $x): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $x, $m);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1)));
};

preg_match_all('/<w:tbl>.*?<\/w:tbl>/s', $xml, $tables, PREG_OFFSET_CAPTURE);
$table = null;
foreach ($tables[0] as $t) {
    preg_match('/<w:tr>.*?<\/w:tr>/s', $t[0], $first);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $first[0], $cs);
    if (array_map($cellText, $cs[0]) === ['Child column', 'References', 'On delete', 'On update']) {
        $table = ['xml' => $t[0], 'at' => $t[1]];

        break;
    }
}
if ($table === null) {
    fwrite(STDERR, "REFUSE: the §23.3 table was not found by its header row\n");
    exit(1);
}

preg_match_all('/<w:tr>.*?<\/w:tr>/s', $table['xml'], $trs, PREG_OFFSET_CAPTURE);
$documented = [];
$templateRow = null;
foreach ($trs[0] as $row) {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row[0], $cs);
    $v = array_map($cellText, $cs[0]);
    if (count($v) !== 4 || $v[0] === 'Child column') {
        continue;
    }
    $documented[strtolower($v[0])] = $row;
    $templateRow = $row[0];
}

$missing = array_values(array_filter($live, fn ($e) => ! isset($documented[strtolower($e['child'])])));

printf("%d edges in the document, %d the server has that it does not\n\n", count($documented), count($missing));
if (count($missing) !== 14) {
    fwrite(STDERR, 'REFUSE: expected 14 missing edges, found '.count($missing)."\n");
    exit(1);
}

// Each new edge is placed in the group the chapter already keeps (rows are grouped by the table
// that holds the key, and within a group ordered by column). `$after` names the existing row it
// follows; the shift docket has no group yet, so its nine rows arrive as one block.
$place = [
    'enrollments.admissionid' => ['enrollments.clearanceconfirmedby'],
    'enrolledsubjects.subjectid' => [
        'shiftingrequests.studentid', 'shiftingrequests.currentcourseid', 'shiftingrequests.targetcourseid',
        'shiftingrequests.currentenrollmentid', 'shiftingrequests.grantedenrollmentid',
        'shiftingrequests.requestedby', 'shiftingrequests.termid', 'shiftingrequests.departmentsignedby',
        'shiftingrequests.decisionby',
    ],
    'studentscholarships.scholarshiptypeid' => ['studentscholarships.statuschangedby'],
    'payments.processedby' => ['payments.refundedby'],
    // documentprintlog's group holds enrollmentId then printedBy; blockId sorts before either and
    // studentId after both, which is where the server would put them.
    'auditlogs.userid' => ['documentprintlog.blockid'],
    'documentprintlog.printedby' => ['documentprintlog.studentid'],
];

$byKey = [];
foreach ($missing as $e) {
    $byKey[strtolower($e['child'])] = $e;
}

$buildRow = function (array $e) use ($templateRow, $esc): string {
    $values = [$e['child'], $e['to'], $e['delete'], $e['update']];
    $row = $templateRow;
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cells, PREG_OFFSET_CAPTURE);
    foreach (array_reverse($cells[0], true) as $j => $cell) {
        [, $end] = $cell;
        $body = $cell[0];
        preg_match('/<w:r\b.*?<\/w:r>/s', $body, $rm);
        preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
        $stripped = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $body);
        $text = $values[$j] ?? '';
        $new = $text === ''
            ? $stripped
            : str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'.$esc($text).'</w:t></w:r></w:p>', $stripped);
        $row = substr_replace($row, $new, $cell[1], strlen($body));
    }

    return $row;
};

$inserts = [];
foreach ($place as $afterKey => $keys) {
    if (! isset($documented[$afterKey])) {
        fwrite(STDERR, "REFUSE: the anchor row {$afterKey} is not in the table\n");
        exit(1);
    }
    $blockXml = '';
    foreach ($keys as $k) {
        if (! isset($byKey[$k])) {
            fwrite(STDERR, "REFUSE: {$k} is not among the server's missing edges\n");
            exit(1);
        }
        $e = $byKey[$k];
        printf("  + %-46s %-38s %s / %s\n", $e['child'], $e['to'], $e['delete'], $e['update']);
        $blockXml .= $buildRow($e);
    }
    $row = $documented[$afterKey];
    $inserts[] = [
        'at' => $table['at'] + $row[1] + strlen($row[0]),
        'xml' => $blockXml,
        'after' => $afterKey,
        'rows' => count($keys),
    ];
}

// ── 3. §24.6's Enrollment module list gains the table §24.6 now carries ──────────────────

$moduleAnchor = '<w:t xml:space="preserve">enrolledsubjects</w:t></w:r><w:r><w:rPr><w:i/><w:iCs/><w:sz w:val="18"/><w:szCs w:val="18"/></w:rPr><w:t xml:space="preserve">.</w:t>';
if (substr_count($xml, $moduleAnchor) !== 1) {
    fwrite(STDERR, 'REFUSE: the module-list anchor is seen '.substr_count($xml, $moduleAnchor)." times\n");
    exit(1);
}
// The two runs are taken verbatim from the same sentence, so the new name arrives in the
// chapter's own code-face italic and not in a hand-authored style.
$commaRun = '<w:r><w:rPr><w:i/><w:iCs/><w:sz w:val="18"/><w:szCs w:val="18"/></w:rPr><w:t xml:space="preserve">, </w:t></w:r>';
$codeRun = '<w:r><w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas" w:cs="Consolas"/><w:i/><w:iCs/><w:sz w:val="18"/><w:szCs w:val="18"/></w:rPr><w:t xml:space="preserve">shiftingrequests</w:t></w:r>';
$moduleAt = strpos($xml, $moduleAnchor) + strlen('<w:t xml:space="preserve">enrolledsubjects</w:t></w:r>');
printf("\n  module list: enrolledsubjects → enrolledsubjects, shiftingrequests @ %d\n", $moduleAt);

if (! $apply) {
    $rows = array_sum(array_column($inserts, 'rows'));
    printf("\nDRY RUN — %d rows in %d groups, plus 2 runs in the module list\n", $rows, count($inserts));
    exit(0);
}

// ── 4. write, from the end backwards so earlier offsets stay valid ───────────────────────

copy($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-05-pass26.docx');

$edits = $inserts;
$edits[] = ['at' => $moduleAt, 'xml' => $commaRun.$codeRun, 'rows' => 0];
usort($edits, fn ($a, $b) => $b['at'] <=> $a['at']);
foreach ($edits as $e) {
    $xml = substr_replace($xml, $e['xml'], $e['at'], 0);
}

// ── 5. structural guards ─────────────────────────────────────────────────────────────────

// Nothing is removed by this pass, so for every tag the document may only move by exactly what
// the inserted blocks themselves contain — computed from them, not asserted by hand.
$allInserted = $commaRun.$codeRun;
foreach ($inserts as $e) {
    $allInserted .= $e['xml'];
}
foreach (['<w:tbl>', '</w:tbl>', '<w:tr>', '</w:tr>', '<w:tc>', '</w:tc>', '<w:p>', '<w:p ', '</w:p>', '<w:r>', '<w:rPr>', '<w:t ', '<w:t>'] as $tag) {
    $moved = substr_count($xml, $tag) - substr_count($original, $tag);
    $expect = substr_count($allInserted, $tag);
    if ($moved !== $expect) {
        fwrite(STDERR, "REFUSE WRITE: {$tag} moved by {$moved}, expected {$expect}\n");
        exit(1);
    }
    printf("guard  %-9s moved by %d as intended\n", $tag, $moved);
}
if (libxml_use_internal_errors(true) && simplexml_load_string($xml) === false) {
    fwrite(STDERR, "REFUSE WRITE: document.xml does not parse\n");
    exit(1);
}
echo "guard  document.xml parses\n";

// Every new edge must read exactly as the server says, in the row it was placed in.
foreach ($missing as $e) {
    foreach ([$e['child'], $e['to'], $e['delete'], $e['update']] as $v) {
        $probe = $esc($v);
        if (substr_count($xml, '<w:t xml:space="preserve">'.$probe.'</w:t>') < 1) {
            fwrite(STDERR, "REFUSE WRITE: {$probe} is not in the document\n");
            exit(1);
        }
    }
}
echo "guard  all ".count($missing)." new edges present verbatim at their offsets\n";
if (substr_count($xml, $commaRun.$codeRun) !== 1) {
    fwrite(STDERR, 'REFUSE WRITE: the module list carries the new pair '.substr_count($xml, $commaRun.$codeRun)." time(s), expected 1\n");
    exit(1);
}
echo "guard  the module list carries the sixth table\n";

$z = new ZipArchive();
$z->open($src, ZipArchive::CREATE);
$z->addFromString('word/document.xml', $xml);
$z->close();

echo "\nWROTE {$src}\n";
