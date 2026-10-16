<?php
/**
 * Every table row and paragraph that cites ExamController.php, printed in full.
 *
 * The line ranges moved twice today and two of these cells also describe BEHAVIOUR that the G-8
 * closure changed (a recorded fail no longer rejects an application — the examination module
 * writes examresults rows and nothing else). A probe that shows the whole cell is what lets the
 * next pass tell a stale number from a stale claim.
 *
 * Nothing is written.
 */

$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive();
$z->open($docx);
$xml = $z->getFromName('word/document.xml');
$z->close();

$flat = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));
preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $xml, $rows, PREG_OFFSET_CAPTURE);

echo "=== ROWS ===\n";
foreach ($rows[0] as $r) {
    if (! preg_match('/ExamController\.php/', $r[0])) {
        continue;
    }
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $r[0], $cm);
    $cells = [];
    foreach ($cm[0] as $c) {
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $c, $tm);
        $cells[] = preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1));
    }
    echo '@'.$r[1].' cells='.count($cells)."\n";
    foreach ($cells as $i => $c) {
        if (trim($c) === '') {
            continue;
        }
        echo '   ['.$i.'] '.$c."\n";
    }
    echo "\n";
}

echo "=== PARAGRAPHS (outside rows) ===\n";
preg_match_all('/<w:p\b.*?<\/w:p>/s', $xml, $ps, PREG_OFFSET_CAPTURE);
$inRow = [];
foreach ($rows[0] as $r) {
    for ($x = $r[1]; $x < $r[1] + strlen($r[0]); $x++) {
        $inRow[$x] = true;
    }
}
foreach ($ps[0] as $p) {
    if (! preg_match('/ExamController\.php/', $p[0])) {
        continue;
    }
    if (isset($inRow[$p[1]])) {
        continue;
    }
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $p[0], $tm);
    $t = preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1));
    if (trim($t) === '') {
        continue;
    }
    echo '@'.$p[1].' runs='.count($tm[1])."\n   ".$t."\n\n";
}
