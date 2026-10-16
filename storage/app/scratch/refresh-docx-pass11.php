<?php

/**
 * Pass 11 — add G-9 to the §28 gap register.
 *
 * The row is cloned from G-8 so it inherits the table's borders, widths and fonts, then
 * every cell is rewritten; the claim is the measurement taken against ems on 2026-10-02
 * and printed by the demo pipeline from this batch on.
 */

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$tmp = sys_get_temp_dir().'/ems-docx11-'.uniqid().'.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$anchor = 'An approved application with no enrollment row is invisible to the next desk';
$pos = strpos($xml, $anchor);
if ($pos === false) {
    fwrite(STDERR, "G-8 row not found\n");
    exit(1);
}
$start = strrpos(substr($xml, 0, $pos), '<w:tr');
$end = strpos($xml, '</w:tr>', $pos) + 7;
if ($start === false || strpos(substr($xml, $start, $pos - $start), '</w:tr>') !== false) {
    fwrite(STDERR, "G-8 anchor is not inside a table row\n");
    exit(1);
}

$cells = [
    ['G-8', 'G-9'],
    ['An approved application with no enrollment row is invisible to the next desk',
        'The demo term offers no curriculum subject at the level and semester most active enrollments sit'],
    ['The Evaluation queue selects on enrollmentStatus',
        'Measured against ems on 2026-10-02: 17 of the 18 active enrollments sit the Summer term of AY 2025-2026 (termId 18), and the pinned curriculum holds no row for that semester at their year level — BSIT Y1 (9), BSCrim Y1 (3), BSCrim Y2 (3), BSBA Y1 (1), BSBA Y2 (1). The Evaluation desk for those students lists zero available subjects, so the loads on file (ENG101, GEN101, CRIM101 — none of them curriculum rows at that level) could not have been proposed there, and the mandatory-subject, elective-band and prerequisite checks each pass by absence rather than by satisfying anything. The demo pipeline now prints the count and one line per program and level on every run.'],
    ['Developer',
        'Registrar (which subjects a special term offers) or the demo owner (seat the demo in a term the curriculum covers)'],
    ['§11.5', '§13.4, §16 (gate 7), §21.3'],
];

$row = substr($xml, $start, $end - $start);
foreach ($cells as $i => [$prefix, $to]) {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, PREG_OFFSET_CAPTURE);
    $hits = [];
    foreach ($cm[0] as $j => $cell) {
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell[0], $tm);
        if (strncmp(implode('', $tm[1]), $prefix, min(strlen($prefix), 50)) === 0) {
            $hits[] = $j;
        }
    }
    if (count($hits) !== 1) {
        echo "FAIL cell {$i}: matched ".count($hits)." for [".substr($prefix, 0, 40)."]\n";
        exit(1);
    }
    $cell = $cm[0][$hits[0]][0];
    if (substr_count($cell, '<w:p>') !== 1) {
        echo "FAIL cell {$i}: holds ".substr_count($cell, '<w:p>')." paragraphs\n";
        exit(1);
    }
    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $newCell = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $newCell = str_replace('</w:p>', '<w:r>'.$pr[0].'<w:t xml:space="preserve">'
        .str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $to).'</w:t></w:r></w:p>', $newCell);
    $row = substr_replace($row, $newCell, $cm[0][$hits[0]][1], strlen($cell));
    echo "ok   cell {$i} rewritten\n";
}

$xml = substr($xml, 0, $end).$row.substr($xml, $end);
echo "\nrow cloned with all 5 cells rewritten\n";

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
