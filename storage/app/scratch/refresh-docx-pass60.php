<?php
/**
 * Pass 60 — §24's `courses` column listing gains the row for entranceExamExemptsTransferee.
 *
 * The shared engine cannot do this: its container guard insists <w:tr> moves by zero, and a
 * new dictionary row moves it by one. So this file builds the row by CLONING the row above it
 * — the one for requiresCourseSpecificExam, which is the same shape (a tinyint(1) flag with a
 * 0 default) — and rewriting only the six text cells. Cloning is what keeps the shading, the
 * borders, the cell widths and the paragraph styles identical to its neighbours; a hand-written
 * <w:tr> would be the one row in a 185-table document that renders differently.
 *
 * Guards, all before any write: the heading is found once, the table that follows it is the one
 * that owns the template row, every cell of that row holds exactly one paragraph, the cloned
 * text appears at the offset it is written to, the containers move by exactly the template's
 * own counts, and the document still parses.
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

$heading = 'courses — 7 columns';
$template = 'requiresCourseSpecificExam';
$values = [
    'entranceExamExemptsTransferee',
    'tinyint(1)',
    'no',
    '',
    '0',
    '',
];

if (substr_count($xml, $heading) !== 1) {
    fwrite(STDERR, "REFUSE: the heading is not unique (".substr_count($xml, $heading)." copies)\n");
    exit(1);
}
$hPos = strpos($xml, $heading);
$tblStart = strpos($xml, '<w:tbl>', $hPos);
$tblEnd = strpos($xml, '</w:tbl>', $tblStart) + 8;
if ($tblStart === false || $tblStart > $tblEnd) {
    fwrite(STDERR, "REFUSE: no table follows the heading\n");
    exit(1);
}
$tbl = substr($xml, $tblStart, $tblEnd - $tblStart);
echo 'heading @'.$hPos.'  table @'.$tblStart.'..'.$tblEnd.' ('.strlen($tbl)." bytes)\n";

preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $tbl, $rows, PREG_OFFSET_CAPTURE);
echo 'rows in the table: '.count($rows[0])."\n";

// Prove this table is the dictionary listing and not its neighbour: the first row must be the
// six-column header, and the heading must sit OUTSIDE the table it names.
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $rows[0][0][0], $hcm);
preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $hcm[0][0], $htm);
$firstHeaderCell = preg_replace('/\s+/', ' ', html_entity_decode(implode('', $htm[1]), ENT_QUOTES | ENT_XML1));
if (count($hcm[0]) !== 6 || trim($firstHeaderCell) !== 'Column') {
    fwrite(STDERR, 'REFUSE: the table after the heading has '.count($hcm[0]).' cells in its first row '
        .'starting ['.trim($firstHeaderCell).'], not the 6-cell "Column" header\n');
    exit(1);
}
if (substr_count(substr($xml, 0, $hPos), '<w:tbl>') !== substr_count(substr($xml, 0, $hPos), '</w:tbl>')) {
    fwrite(STDERR, "REFUSE: the heading sits inside an open table, so the next table is a neighbour\n");
    exit(1);
}
echo "guard  that table is the {$template} listing (6-cell Column header, heading precedes it)\n";

// The template row must be the LAST row of the table — the listing ends with the newest flag,
// and a row inserted anywhere else would sort the dictionary by nothing at all.
$tAt = null;
foreach ($rows[0] as $k => $r) {
    if (str_contains($r[0], $template)) {
        $tAt = $k;
    }
}
if ($tAt === null) {
    fwrite(STDERR, "REFUSE: no row of that table holds {$template}\n");
    exit(1);
}
if ($tAt !== count($rows[0]) - 1) {
    fwrite(STDERR, "REFUSE: the template row is row {$tAt} of ".count($rows[0]).", not the last\n");
    exit(1);
}
$tpl = $rows[0][$tAt][0];
$at = $tblStart + $rows[0][$tAt][1] + strlen($tpl);

preg_match_all('/<w:tc>.*?<\/w:tc>/s', $tpl, $cells);
echo 'template cells: '.count($cells[0])."\n";
if (count($cells[0]) !== count($values)) {
    fwrite(STDERR, 'REFUSE: template has '.count($cells[0]).' cells, this pass writes '.count($values)."\n");
    exit(1);
}
foreach ($cells[0] as $k => $cell) {
    if (substr_count($cell, '<w:p>') !== 1) {
        fwrite(STDERR, "REFUSE: template cell {$k} holds ".substr_count($cell, '<w:p>')." paragraphs\n");
        exit(1);
    }
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $tm);
    echo '  cell '.$k.' currently: '.var_export(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)), true)."\n";
}

// Build the clone cell by cell: strip the runs, keep everything else (tcPr, the paragraph
// properties), and put one run back carrying the new value in the template's own run style.
$clone = $tpl;
$escaped = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;', ], $s);
foreach ($cells[0] as $k => $cell) {
    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .$escaped($values[$k]).'</w:t></w:r></w:p>', $body);
    $clone = str_replace($cell, $new, $clone);
}
if (substr_count($clone, '<w:tc>') !== count($values)) {
    fwrite(STDERR, "REFUSE: the clone lost or gained a cell\n");
    exit(1);
}
echo 'clone: '.strlen($clone).' bytes, cells='.substr_count($clone, '<w:tc>')."\n";
echo 'insert @'.$at."\n";

$newXml = $xml;
$newXml = substr_replace($newXml, $clone, $at, 0);
// The heading count then follows the row it describes.
$old = $escaped($heading);
$new = $escaped('courses — 8 columns');
if (substr_count($newXml, $old) !== 1) {
    fwrite(STDERR, "REFUSE: heading vanished or duplicated during the insert\n");
    exit(1);
}
$newXml = str_replace($old, $new, $newXml);

foreach ([
    '<w:tr>' => substr_count($newXml, '<w:tr>') - substr_count($original, '<w:tr>'),
    '</w:tr>' => substr_count($newXml, '</w:tr>') - substr_count($original, '</w:tr>'),
    '<w:tc>' => substr_count($newXml, '<w:tc>') - substr_count($original, '<w:tc>'),
    '</w:tc>' => substr_count($newXml, '</w:tc>') - substr_count($original, '</w:tc>'),
    '<w:p>' => substr_count($newXml, '<w:p>') - substr_count($original, '<w:p>'),
    '</w:p>' => substr_count($newXml, '</w:p>') - substr_count($original, '</w:p>'),
] as $tag => $d) {
    $want = str_contains($tag, 'tc') ? count($values) : (str_contains($tag, 'tr') ? 1 : substr_count($tpl, '<w:p>'));
    printf("guard %-8s moved by %d, expected %d  %s\n", $tag, $d, $want, $d === $want ? 'ok' : 'REFUSED');
    if ($d !== $want) {
        exit(1);
    }
}

if (substr($newXml, $at, strlen($clone)) !== $clone) {
    fwrite(STDERR, "REFUSE: the cloned row is not byte-identical at its offset\n");
    exit(1);
}
echo "guard  the clone sits exactly at @{$at}\n";
if (simplexml_load_string($newXml) === false) {
    fwrite(STDERR, "REFUSE: document.xml no longer parses\n");
    exit(1);
}
echo "guard  document.xml parses\n";

if (! $apply) {
    echo "\nDRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-07-pass60.docx');
$z2 = new ZipArchive();
$z2->open($src);
$z2->addFromString('word/document.xml', $newXml);
$z2->close();

// Verify from disk, not from memory: the zip write is the step that has surprised this project.
$z3 = new ZipArchive();
$z3->open($src);
$back = $z3->getFromName('word/document.xml');
$z3->close();
if ($back !== $newXml) {
    fwrite(STDERR, "REFUSE: the file on disk does not hold the edited document.xml\n");
    exit(1);
}
echo "Wrote {$src}\n";
