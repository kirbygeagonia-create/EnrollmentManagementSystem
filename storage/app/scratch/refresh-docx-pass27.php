<?php

/**
 * Pass 27 — the figures DERIVED from §23.3's inventory, re-read from information_schema now that
 * the inventory itself carries all 100 edges.
 *
 * §23.8's census (Keys out, Keys in, the inbound count printed beside a module's most-depended-on
 * table) and §23.3's closing rule split were both computed at 86 edges. Every replacement here is
 * the server's number, and every cell is written only after its previous value is checked, so a
 * mis-indexed column cannot write silently into the neighbouring one.
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

$cellText = function (string $x): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $x, $m);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1)));
};

/** Rewrites one cell's text, keeping the cell's own first-run style. */
$setCell = function (string $cell, string $text) use ($esc): string {
    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $stripped = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);

    return str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'.$esc($text).'</w:t></w:r></w:p>', $stripped);
};

// ── 1. the server's numbers ──────────────────────────────────────────────────────────────

$edges = [];
foreach (DB::select("
    select k.table_name t, k.referenced_table_name rt
    from information_schema.key_column_usage k
    where k.table_schema = 'ems' and k.referenced_table_name is not null") as $r) {
    $edges[] = ['child' => strtolower($r->t), 'parent' => strtolower($r->rt)];
}
$inbound = [];
foreach ($edges as $e) {
    $inbound[$e['parent']] = ($inbound[$e['parent']] ?? 0) + 1;
}

$modules = [
    'Student Information' => ['students', 'guardians', 'addresses', 'religions', 'educationalinstitutions', 'studenteducationalbackgrounds'],
    'Academic Structure' => ['academicunits', 'courses', 'majors', 'curriculums', 'subjects', 'curriculumsubjects', 'gradescale', 'examresults'],
    'Admissions and Requirements' => ['admissions', 'admissionrequirements', 'studentrequirementsubmissions', 'documents'],
    'Enrollment' => ['academicyears', 'academicterms', 'enrollments', 'enrollmentstatushistory', 'enrolledsubjects', 'shiftingrequests'],
    'Transfer and Prior Academic Records' => ['transferacademicrecords', 'creditedsubjects'],
    'Scholarships and Fees' => ['scholarshiptypes', 'studentscholarships', 'feetypes', 'studentassessments', 'charges', 'payments'],
    'Clearance (Registrar sub-function)' => ['clearanceperiods', 'clearancerequirements', 'studentclearances', 'clearanceapprovals'],
    'Scheduling and Blocking' => ['blocks', 'schedules', 'schedulemeetings', 'rooms'],
    'Clinic' => ['clinicrecords'],
    'ID Management' => ['idrequests'],
    'Users, Offices, Roles and Security' => ['offices', 'staffusers', 'roles', 'permissions', 'staff_roles', 'role_permissions', 'model_has_permissions', 'auditlogs', 'settings'],
    'Reports and Document Tracking' => ['notifications', 'documentprintlog'],
    'Workflow Tracking' => ['enrollmentworkflow', 'workflowsteps'],
];

$out = $in = $shown = [];
foreach ($modules as $name => $tables) {
    $shown[$name] = $tables[0];
    foreach ($edges as $e) {
        if (in_array($e['child'], $tables, true)) {
            $out[$name] = ($out[$name] ?? 0) + 1;
        }
        if (in_array($e['parent'], $tables, true) && ! in_array($e['child'], $tables, true)) {
            $in[$name] = ($in[$name] ?? 0) + 1;
        }
    }
}

// The chapter names one representative table per module; its inbound count is the figure in
// parentheses. Each is taken from the row itself rather than assumed, so the pairing cannot drift.
$totals = [
    'tables' => array_sum(array_map('count', $modules)),
    'out' => array_sum($out),
    'in' => array_sum($in),
];
printf("server: %d edges, %d tables in the 13 EMS modules, Keys out %d, Keys in %d\n",
    count($edges), $totals['tables'], $totals['out'], $totals['in']);

// ── 2. the census table, found by its header row ─────────────────────────────────────────

preg_match_all('/<w:tbl>.*?<\/w:tbl>/s', $xml, $tables, PREG_OFFSET_CAPTURE);
$census = null;
foreach ($tables[0] as $t) {
    preg_match('/<w:tr>.*?<\/w:tr>/s', $t[0], $first);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $first[0], $cs);
    if (array_map($cellText, $cs[0]) === ['Module', 'Tables', 'Keys out', 'Keys in', 'Most-depended-on table in the module', 'Notes']) {
        $census = ['xml' => $t[0], 'at' => $t[1]];

        break;
    }
}
if ($census === null) {
    fwrite(STDERR, "REFUSE: §23.8's census table was not found by its header row\n");
    exit(1);
}

preg_match_all('/<w:tr>.*?<\/w:tr>/s', $census['xml'], $trs, PREG_OFFSET_CAPTURE);
$cellWrites = [];
foreach ($trs[0] as $row) {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row[0], $cs, PREG_OFFSET_CAPTURE);
    $v = array_map($cellText, array_column($cs[0], 0));
    if (count($v) !== 6) {
        continue;
    }
    $module = $v[0];
    $isTotal = $module === 'Total';
    if (! $isTotal && ! isset($modules[$module])) {
        continue;
    }

    // The representative table's name is the cell's own first word, so the number beside it is
    // read from the server rather than guessed at.
    $rep = null;
    if (preg_match('/^([a-z_]+)\s*\((\d+)\)$/', $v[4], $m)) {
        $rep = $m[1];
    }

    $wanted = $isTotal
        ? [
            1 => (string) ($totals['tables'] + 8),
            2 => (string) $totals['out'],
            4 => $rep !== null ? $rep.' ('.($inbound[$rep] ?? 0).')' : $v[4],
        ]
        : [
            1 => (string) count($modules[$module]),
            2 => (string) ($out[$module] ?? 0),
            3 => (string) ($in[$module] ?? 0),
            4 => $rep !== null ? $rep.' ('.($inbound[$rep] ?? 0).')' : $v[4],
        ];

    foreach ($wanted as $idx => $newValue) {
        if ($newValue === $v[$idx]) {
            continue;
        }
        $cellWrites[] = [
            // Both offsets are relative: the cell sits inside the row, the row inside the table.
            'at' => $census['at'] + $row[1] + $cs[0][$idx][1],
            'idx' => $idx,
            'len' => strlen($cs[0][$idx][0]),
            'to' => $setCell($cs[0][$idx][0], $newValue),
            'was' => $v[$idx],
            'now' => $newValue,
            'row' => $module,
        ];
        printf("  census %-34s col %d: %-18s → %s\n", $module, $idx, $v[$idx], $newValue);
    }
}

if ($cellWrites === []) {
    fwrite(STDERR, "REFUSE: the census already matches the server — nothing to write\n");
    exit(1);
}

// ── 3. §23.3's closing rule split ────────────────────────────────────────────────────────

$rules = [];
foreach (DB::select("
    select r.delete_rule d, count(*) n
    from information_schema.key_column_usage k
    join information_schema.referential_constraints r
      on r.constraint_name = k.constraint_name and r.constraint_schema = k.table_schema
    where k.table_schema = 'ems' and k.referenced_table_name is not null
    group by r.delete_rule") as $r) {
    $rules[$r->d] = (int) $r->n;
}
printf("\ndelete rules: %s\n", json_encode($rules));

$textWrites = [
    ['86 constraints: 66 refuse deletion of the parent (',
        '100 constraints: '.$rules['NO ACTION'].' refuse deletion of the parent ('],
    ['), 16 null the child key (', '), '.$rules['SET NULL'].' null the child key ('],
    ['), 4 delete the child rows with the parent (', '), '.$rules['CASCADE'].' delete the child rows with the parent ('],
];

// 69 + 26 + 4 is 99, so the sentence needs the fourth rule named or its own arithmetic fails.
// The clause is one run in the sentence's own italic style, with the column in the chapter's
// monospace, appended after the sentence's closing period.
$restrict = $rules['RESTRICT'] ?? 0;
$paraStart = strrpos(substr($xml, 0, strpos($xml, '86 constraints:')), '<w:p');
$paraEnd = strpos($xml, '</w:p>', strpos($xml, '86 constraints:'));
if ($restrict > 0 && $paraStart !== false && $paraEnd !== false) {
    $tailRun = '<w:r><w:rPr><w:i/><w:iCs/><w:sz w:val="18"/><w:szCs w:val="18"/></w:rPr><w:t xml:space="preserve"> The remaining one is declared </w:t></w:r>'
        .'<w:r><w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas" w:cs="Consolas"/><w:i/><w:iCs/><w:sz w:val="18"/><w:szCs w:val="18"/></w:rPr><w:t xml:space="preserve">RESTRICT</w:t></w:r>'
        .'<w:r><w:rPr><w:i/><w:iCs/><w:sz w:val="18"/><w:szCs w:val="18"/></w:rPr><w:t xml:space="preserve"> (</w:t></w:r>'
        .'<w:r><w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas" w:cs="Consolas"/><w:i/><w:iCs/><w:sz w:val="18"/><w:szCs w:val="18"/></w:rPr><w:t xml:space="preserve">shiftingrequests.requestedBy</w:t></w:r>'
        .'<w:r><w:rPr><w:i/><w:iCs/><w:sz w:val="18"/><w:szCs w:val="18"/></w:rPr><w:t xml:space="preserve">), which MySQL enforces exactly as NO ACTION.</w:t></w:r>';
    $textWrites[] = ['__insert_at_end_of_paragraph__', [$paraEnd, $tailRun]];
}
unset($paraStart);

printf("  rule split: 86→100, NO ACTION 66→%d, SET NULL 16→%d, CASCADE 4→%d, RESTRICT +%d clause\n",
    $rules['NO ACTION'] ?? 0, $rules['SET NULL'] ?? 0, $rules['CASCADE'] ?? 0, $restrict);

if (! $apply) {
    printf("\nDRY RUN — %d census cells and %d prose edits would be written\n", count($cellWrites), count($textWrites));
    exit(0);
}

// ── 4. write, from the end backwards ─────────────────────────────────────────────────────

copy($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-05-pass27.docx');

$edits = [];
foreach ($cellWrites as $w) {
    $edits[] = ['at' => $w['at'], 'len' => $w['len'], 'to' => $w['to'], 'label' => $w['row'].' '.$w['was'].'→'.$w['now']];
}
foreach ($textWrites as $w) {
    if ($w[0] === '__insert_at_end_of_paragraph__') {
        $edits[] = ['at' => $w[1][0], 'len' => 0, 'to' => $w[1][1], 'label' => 'RESTRICT clause'];
        continue;
    }
    [$from, $to] = $w;
    if (substr_count($xml, $from) !== 1) {
        fwrite(STDERR, "REFUSE WRITE: prose needle seen ".substr_count($xml, $from)." times [$from]\n");
        exit(1);
    }
    $edits[] = ['at' => strpos($xml, $from), 'len' => strlen($from), 'to' => $to, 'label' => substr($from, 0, 30)];
}

usort($edits, fn ($a, $b) => $b['at'] <=> $a['at']);
foreach ($edits as $e) {
    $xml = substr_replace($xml, $e['to'], $e['at'], $e['len']);
}

// ── 5. guards ────────────────────────────────────────────────────────────────────────────

foreach (['<w:tbl>' => 0, '</w:tbl>' => 0, '<w:tr>' => 0, '</w:tr>' => 0, '<w:tc>' => 0, '</w:tc>' => 0] as $tag => $delta) {
    $moved = substr_count($xml, $tag) - substr_count($original, $tag);
    if ($moved !== $delta) {
        fwrite(STDERR, "REFUSE WRITE: {$tag} moved by {$moved}, expected {$delta}\n");
        exit(1);
    }
}
$pDelta = substr_count($xml, '<w:p>') - substr_count($original, '<w:p>');
if ($pDelta !== 0) {
    fwrite(STDERR, "REFUSE WRITE: <w:p> moved by {$pDelta}\n");
    exit(1);
}
echo "guard  containers unmoved; only cell text and one prose clause changed\n";
if (libxml_use_internal_errors(true) && simplexml_load_string($xml) === false) {
    fwrite(STDERR, "REFUSE WRITE: document.xml does not parse\n");
    exit(1);
}
echo "guard  document.xml parses\n";

// Every census cell must now read the server's figure, in the row that names the module.
foreach ($cellWrites as $w) {
    if (substr_count($xml, $w['to']) < 1) {
        fwrite(STDERR, "REFUSE WRITE: the rewritten cell for {$w['row']} is absent\n");
        exit(1);
    }
}
echo "guard  ".count($cellWrites)." rewritten cells present\n";

// Presence is not position. Re-read the census from the written XML and require every rewritten
// cell to carry its new figure in the row that names its module.
preg_match_all('/<w:tbl>.*?<\/w:tbl>/s', $xml, $newTables, PREG_OFFSET_CAPTURE);
foreach ($newTables[0] as $t) {
    preg_match('/<w:tr>.*?<\/w:tr>/s', $t[0], $f2);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $f2[0], $hc);
    if (array_map($cellText, $hc[0]) !== ['Module', 'Tables', 'Keys out', 'Keys in', 'Most-depended-on table in the module', 'Notes']) {
        continue;
    }
    preg_match_all('/<w:tr>.*?<\/w:tr>/s', $t[0], $nr);
    $byRow = [];
    foreach ($nr[0] as $r) {
        // No PREG_OFFSET_CAPTURE here, so $r IS the row string — $r[0] would be its first letter.
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $r, $rc);
        $vals = array_map($cellText, $rc[0]);
        if (count($vals) === 6) {
            $byRow[$vals[0]] = $vals;
        }
    }
    foreach ($cellWrites as $w) {
        $got = $byRow[$w['row']][$w['idx']] ?? null;
        if ($got === null || preg_replace('/\s+/', ' ', $got) !== preg_replace('/\s+/', ' ', $w['now'])) {
            fwrite(STDERR, "REFUSE WRITE: {$w['row']} col {$w['idx']} reads \"{$got}\", expected \"{$w['now']}\"\n");
            exit(1);
        }
    }
    echo "guard  all ".count($cellWrites)." rewritten cells read their new figure in their own row\n";

    break;
}

$z = new ZipArchive();
$z->open($src, ZipArchive::CREATE);
$z->addFromString('word/document.xml', $xml);
$z->close();

echo "\nWROTE {$src}\n";
