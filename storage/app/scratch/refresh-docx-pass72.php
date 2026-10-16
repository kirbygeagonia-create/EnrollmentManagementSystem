<?php

/**
 * Pass 72 — the document stops asserting two things the code stopped doing, and gains the row
 * describing the audit that found them.
 *
 * Four content corrections, each located by its own run text (never by a remembered offset,
 * because the revision-row label edit in front of them moves every later byte):
 *
 *  1. ExamPolicy.php:32-55 — the range retired with ExamPolicy::verifyGeneralExam() (#47). The
 *     file now ends at line 51, so the cite pointed past it; the surviving rule is record(), 35-51.
 *  2. DashboardController.php:41 — the line Pint's import ordering moved two lines down when the
 *     queue map took its place. Re-measured, not assumed: the cited statement is now line 43.
 *  3. §5.2's Evaluation-queue row said Academic Standing and Year Level are not columns. They are
 *     since the G-2 derivation — measured at Evaluation/Index.jsx:84 and :90 before rewriting.
 *  4. §9's B-3 row still said "the year level is not recalculated (gap G-2)". G-2 closed on
 *     2026-10-05, and YearLevelDerivationTest pins it.
 *
 * Then the revision record gains the 2026-10-10 row (cloned from the last, because the engine's
 * container guard insists a <w:tr> does not move) and the previous row stops claiming to be current.
 */

$src = 'Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open docx\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();
$original = $xml;

$esc = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
// Targets that quote the body verbatim already carry &quot; for a quotation mark; escaping their
// ampersand again looks for &amp;quot; and finds nothing. These are matched as written.
$escRaw = fn (string $s) => str_replace(['<', '>'], ['&lt;', '&gt;'], $s);

$revisionText =
    'The five-lane audit the owner asked for after the catalogue was closed — permissions against '
    .'their readers, routes against their guards, schema against its write paths, the Inertia contract '
    .'between every controller and the page that renders it, and the document\'s own registers read back '
    .'for still-open rows. It found one dead right that every role holds and no code reads '
    .'(dashboard.view, granted to 17 of 17 roles, awaiting the owner\'s ruling like #47), eighteen route '
    .'actions no test names, and no enum drift in either direction across 43 cast columns on live ems. '
    .'The front end carried the real defect: the launcher\'s Campus Clearance card was still gated on '
    .'officeId 8, the office ruling 12 folded into Registrar, so with zero accounts in office 8 the desk '
    .'twelve accounts may open had no card for anyone but the admin — the same retired number also hid '
    .'the Clearance queue on the dashboard. Both now answer to one rule read through the right each '
    .'desk\'s own route asks, the queue payload is scoped to those desks, and two dropdowns stopped '
    .'hard-coding what their enum already decides. Ten tests cover the fixes and the six screens the '
    .'walkthrough opens that had never been asked of the router; every checker in this audit was proved '
    .'against a mutant before its clean verdict was believed, and two of its first answers — "no dead '
    .'rights beyond one" and "0 open register rows" — were detector bugs caught that way, not findings.';

$revisions = [
    ['2026-10-08 (this issue)', '2026-10-08'],
];

foreach ($revisions as [$old, $new]) {
    if (substr_count($xml, $esc($old)) !== 1) {
        fwrite(STDERR, "REFUSE: revision label '{$old}' seen ".substr_count($xml, $esc($old))." times\n");
        exit(1);
    }
    $xml = str_replace($esc($old), $esc($new), $xml);
}

// ---- content corrections --------------------------------------------------------
$edits = [
    ['ExamPolicy.php:32-55', 'ExamPolicy.php:35-51'],
    ['DashboardController.php:41', 'DashboardController.php:43'],
    ['Evaluation/Index.jsx:85-88', 'Evaluation/Index.jsx:84-91'],
    ['Student Type as a badge column (', 'Student Type as a badge column, with Year Level and Proposed Standing beside it ('],
    ['Academic Standing and Year Level are not columns. The queue summary also counts transferee and shifter rows as one &quot;transferees&quot; figure (',
     'Year Level and Academic Standing were missing from this queue and are columns of it now (:84, :90); what still merges two categories is the summary, which counts transferee and shifter rows as one &quot;transferees&quot; figure ('],
    ['Partially implemented — crediting exists (', 'Implemented — crediting exists ('],
    [' recalculated (gap G-2).', ' derived from the load it was credited against (gap G-2, closed 2026-10-05).'],
];

foreach ($edits as [$from, $to]) {
    $f = $escRaw($from);
    $n = substr_count($xml, $f);
    if ($n !== 1) {
        fwrite(STDERR, "REFUSE: seen {$n} times, expected 1: '".mb_substr($from, 0, 60)."'\n");
        exit(1);
    }
    $xml = str_replace($f, $escRaw($to), $xml);
}

// The 'not' of "the year level is not recalculated" is its own run — emphasis formatting splits it.
// Located structurally from the unique run before it rather than by content, since 'not' alone is not
// unique in a 5.7M-character body. It becomes 'now', and the tail run above already became
// 'derived from the load…', so the cell ends up asserting the closed gap, not the old one.
$anchor = $escRaw('); the year level is ');
$from = strpos($xml, $anchor) + strlen($anchor);
$window = substr($xml, $from, 600);
if (preg_match('/<w:t(?: [^>]*)?>not<\/w:t>/', $window, $rm, PREG_OFFSET_CAPTURE) !== 1) {
    fwrite(STDERR, "REFUSE: the 'not' run does not follow the anchor\n");
    exit(1);
}
$notAt = $from + $rm[0][1] + strpos($rm[0][0], '>not<') + 1;
if (substr($xml, $notAt, 3) !== 'not') {
    fwrite(STDERR, "REFUSE: 'not' is not at the computed offset\n");
    exit(1);
}
$xml = substr_replace($xml, 'now', $notAt, 3);
if (substr($xml, $notAt, 3) !== 'now') {
    fwrite(STDERR, "REFUSE: the 'not' run did not become 'now'\n");
    exit(1);
}

// ---- revision record row --------------------------------------------------------
$heading = '1.1 Revision Record';
$template = 'The rulings of 2026-10-07 were walked through the running system the next day';
foreach ([$heading, $template] as $n) {
    if (substr_count($xml, $n) !== 1) {
        fwrite(STDERR, "REFUSE: '".mb_substr($n, 0, 40)."' seen ".substr_count($xml, $n)." times\n");
        exit(1);
    }
}
$hPos = strpos($xml, $heading);
if (substr_count(substr($xml, 0, $hPos), '<w:tbl>') !== substr_count(substr($xml, 0, $hPos), '</w:tbl>')) {
    fwrite(STDERR, "REFUSE: the heading sits inside an open table\n");
    exit(1);
}
$tblStart = strpos($xml, '<w:tbl>', $hPos);
$tblEnd = strpos($xml, '</w:tbl>', $tblStart) + 8;
preg_match_all('/<w:tr\b.*?<\/w:tr>/s', substr($xml, $tblStart, $tblEnd - $tblStart), $rows, PREG_OFFSET_CAPTURE);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $rows[0][0][0], $hcm);
preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $hcm[0][0], $htm);
$hdr = trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $htm[1]), ENT_QUOTES | ENT_XML1)));
if (count($hcm[0]) !== 2 || $hdr !== 'Revision') {
    fwrite(STDERR, "REFUSE: not the revision table (header cell [{$hdr}], ".count($hcm[0])." cells)\n");
    exit(1);
}
$tAt = null;
foreach ($rows[0] as $k => $r) {
    if (str_contains($r[0], $template)) {
        $tAt = $k;
    }
}
if ($tAt === null || $tAt !== count($rows[0]) - 1) {
    fwrite(STDERR, "REFUSE: template row not the last (found ".var_export($tAt, true).', rows='.count($rows[0]).")\n");
    exit(1);
}
$tpl = $rows[0][$tAt][0];
$values = ['2026-10-10 (this issue)', $revisionText];
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $tpl, $cells);
if (count($cells[0]) !== count($values)) {
    fwrite(STDERR, 'REFUSE: template has '.count($cells[0])." cells, pass writes ".count($values)."\n");
    exit(1);
}
foreach ($cells[0] as $k => $cell) {
    if (substr_count($cell, '<w:p>') !== 1) {
        fwrite(STDERR, "REFUSE: template cell {$k} holds ".substr_count($cell, '<w:p>')." paragraphs\n");
        exit(1);
    }
}
$clone = $tpl;
foreach ($cells[0] as $k => $cell) {
    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .$escaped = $esc($values[$k]).'</w:t></w:r></w:p>', $body);
    $clone = str_replace($cell, $new, $clone);
}
$at = $tblStart + $rows[0][$tAt][1] + strlen($tpl);
$newXml = substr_replace($xml, $clone, $at, 0);
if (substr_count($newXml, $esc('(this issue)')) !== 1) {
    fwrite(STDERR, "REFUSE: more than one row now says it is the current issue\n");
    exit(1);
}
foreach (['<w:tr>' => 1, '</w:tr>' => 1, '<w:tc>' => 2, '</w:tc>' => 2,
          '<w:p>' => substr_count($tpl, '<w:p>'), '</w:p>' => substr_count($tpl, '<w:p>')] as $tag => $want) {
    $d = substr_count($newXml, $tag) - substr_count($xml, $tag);
    if ($d !== $want) {
        fwrite(STDERR, "REFUSE: {$tag} moved by {$d}, expected {$want}\n");
        exit(1);
    }
}
if (substr($newXml, $at, strlen($clone)) !== $clone || simplexml_load_string($newXml) === false) {
    fwrite(STDERR, "REFUSE: clone not byte-identical, or document.xml does not parse\n");
    exit(1);
}

printf("edits applied: %d text corrections + 1 revision row (table @%d..%d, cloning row %d)\n",
    count($edits), $tblStart, $tblEnd, $tAt);
$flat = html_entity_decode(preg_replace('/<[^>]+>/', '', $newXml), ENT_QUOTES | ENT_XML1);
foreach (['ExamPolicy.php:35-51', 'DashboardController.php:43', 'Evaluation/Index.jsx:84-91',
    'the year level is now derived from the load it was credited against (gap G-2, closed 2026-10-05)',
    'Implemented — crediting exists', '2026-10-10 (this issue)',
    'Year Level and Academic Standing were missing from this queue and are columns of it now'] as $must) {
    $want = str_replace('\u2014', '—', $must);
    if (substr_count($flat, $want) !== 1) {
        fwrite(STDERR, "REFUSE: expected result seen ".substr_count($flat, $want)." times, want 1: {$must}
");
        exit(1);
    }
}
foreach (['ExamPolicy.php:32-55', 'DashboardController.php:41', 'Index.jsx:85-88',
    'year level is now not', 'not recalculated', 'Partially implemented — crediting',
    'Academic Standing and Year Level are not columns'] as $mustNot) {
    $want = str_replace('\u2014', '—', $mustNot);
    if (substr_count($flat, $want) !== 0) {
        fwrite(STDERR, "REFUSE: stale text still present: {$mustNot}
");
        exit(1);
    }
}
echo "guards  every correction landed, no stale text survives, containers moved by the template's own counts, document parses\n";

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}
copy($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-10-pass72.docx');
$z2 = new ZipArchive();
$z2->open($src);
$z2->addFromString('word/document.xml', $newXml);
$z2->close();
$z3 = new ZipArchive();
$z3->open($src);
$back = $z3->getFromName('word/document.xml');
$z3->close();
if ($back !== $newXml) {
    fwrite(STDERR, "REFUSE: the file on disk does not hold the edited document.xml\n");
    exit(1);
}
echo "Wrote {$src}\n";
