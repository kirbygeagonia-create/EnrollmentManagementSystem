<?php

/**
 * Pass 16 — the revision log gets this batch, and the previous entry stops claiming to be
 * the current issue.
 *
 * Cloning the last row is deliberate: the copy inherits the table's own borders, widths and
 * fonts, so the new row cannot look like a different table. The guard is the same one the
 * cell engine uses, with the intended row delta made explicit.
 */

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open docx\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();
$original = $xml;

$anchor = '2026-09-28 (this issue)';
if (substr_count($xml, $anchor) !== 1) {
    fwrite(STDERR, "revision anchor count is ".substr_count($xml, $anchor)."\n");
    exit(1);
}

$pos = strpos($xml, $anchor);
$start = strrpos(substr($xml, 0, $pos), '<w:tr');
$end = strpos($xml, '</w:tr>', $pos) + 7;
if ($start === false || strpos(substr($xml, $start, $pos - $start), '</w:tr>') !== false) {
    fwrite(STDERR, "anchor is not inside one row\n");
    exit(1);
}

$row = substr($xml, $start, $end - $start);
$cells = preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, PREG_OFFSET_CAPTURE);
if ($cells !== 2) {
    fwrite(STDERR, "revision row holds {$cells} cells, expected 2\n");
    exit(1);
}

$new = [
    '2026-10-04 (this issue)',
    'The owner’s 2026-10-02 rulings become desk actions and the document is re-read against the build they produced. Money: a receipt below the balance records partial and holds the account up (Payments::held() is the one definition of money still in the drawer), a refund keeps its number and its date and reopens paid|enrolled to assessed, a scholarship grant is revoked or expired by a named decision with a reason and coverage is recomputed from the grants still standing, and a zero-balance account is settled by Accounting without inventing a receipt — with the fact printed on the fee sheet and the hand and hour named. Authority: the five signature acts leave OfficeHead for the desks that own each box, the lost-slip replacement reaches Accounting and the Registrar and prices itself from Reference Data rather than a config default, and the legacy Clearance office (id 8) is retired in data so nine offices remain. Process: the pass slip is confirmed at Department Evaluation, Evaluation issues the returning student’s enrollment, one student holds one active enrollment per term at every creation point, the shift request becomes a paper with three signatures, an enrollment can be dropped by the Registrar with a reason and the seat released, a clearance window closes only when nothing is pending and extends by its own action, and the year level is derived from the student’s own record (G-2) instead of asserted as 1. Data: the demo dataset moves to the term the pinned curriculum covers, its loads and timetable are built from that curriculum, its curriculum version is pinned at issue, and the calendar reaches AY 2026-2027. §28 records the rows this closes — G-1, G-2, G-3, G-4, G-7, G-8, G-9, D-1, X-4, C-11, P-12, E-1 and E-4 — and §24 states which column listings are not yet re-generated.',
];

// Rewrite both cells of the clone, keeping each cell's own run properties. Cells are
// handled from the last one backwards so an earlier rewrite cannot move a later offset.
$originalRow = substr($xml, $start, $end - $start);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $originalRow, $cm, PREG_OFFSET_CAPTURE);
$row = $originalRow;
foreach (array_reverse($cm[0], true) as $j => $cell) {
    $body = $cell[0];
    preg_match('/<w:r\b.*?<\/w:r>/s', $body, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $text = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $new[$j]);
    $stripped = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $body);
    $stripped = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'.$text.'</w:t></w:r></w:p>', $stripped);
    $row = substr_replace($row, $stripped, $cell[1], strlen($body));
}

// The previous entry stops calling itself the current issue.
$rowOld = str_replace($anchor, '2026-09-28', $originalRow);
if ($rowOld === $originalRow) {
    fwrite(STDERR, "the (this issue) marker was not found in the row\n");
    exit(1);
}

$xml = substr($xml, 0, $start).$rowOld.$row.substr($xml, $end);

$checks = [
    '<w:tr>' => [preg_match_all('/<w:tr>/', $original), preg_match_all('/<w:tr>/', $xml), 1],
    '</w:tr>' => [preg_match_all('/<\/w:tr>/', $original), preg_match_all('/<\/w:tr>/', $xml), 1],
    '<w:tbl>' => [preg_match_all('/<w:tbl>/', $original), preg_match_all('/<w:tbl>/', $xml), 0],
    '<w:tc>' => [preg_match_all('/<w:tc>/', $original), preg_match_all('/<w:tc>/', $xml), 2],
    '<w:p>' => [preg_match_all('/<w:p>/', $original), preg_match_all('/<w:p>/', $xml), 2],
];
foreach ($checks as $tag => [$b, $a, $delta]) {
    if ($a - $b !== $delta) {
        fwrite(STDERR, "REFUSED WRITE: {$tag} moved by ".($a - $b).', expected '.$delta."\n");
        exit(1);
    }
    echo "guard  {$tag} delta ".($a - $b)." as intended\n";
}
if (substr_count($xml, $new[0]) !== 1 || substr_count($xml, '2026-09-28 (this issue)') !== 0) {
    fwrite(STDERR, "REFUSED WRITE: the revision rows are not as intended\n");
    exit(1);
}
echo "guard  new row present, old marker removed, document.xml parses: ".(simplexml_load_string($xml) === false ? 'FAIL' : 'ok')."\n";
if (simplexml_load_string($xml) === false) {
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-04-pass16.docx');
$tmp = sys_get_temp_dir().'/ems-docx16-'.uniqid().'.docx';
copy($src, $tmp);
$z2 = new ZipArchive();
$z2->open($tmp);
$z2->addFromString('word/document.xml', $xml);
$z2->close();
rename($tmp, $src);
echo "WROTE {$src}\n";
