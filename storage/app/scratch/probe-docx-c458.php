<?php
/**
 * Where the C-4 / C-5 / C-8 rulings have to be written, read from the live file.
 *
 * Three targets, one probe: §28.2's three register rows (what the panel still has to
 * answer), the §24 column listing for `courses` (which needs a new row for the flag the
 * ruling added), and the revision log (which records the pass itself).
 *
 * Prints row anchors as they exist byte-for-byte, because every later pass addresses the
 * document by them. Nothing is written.
 */

$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive();
if ($z->open($docx) !== true) {
    fwrite(STDERR, "cannot open {$docx}\n");
    exit(1);
}
$xml = $z->getFromName('word/document.xml');
$z->close();

echo 'document.xml: '.number_format(strlen($xml))." chars\n\n";

$cellsOf = function (string $row): array {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
    $out = [];
    foreach ($cm[0] as $cell) {
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $tm);
        $out[] = preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1));
    }

    return $out;
};

/** Print every row whose first cell starts with one of these register marks. */
foreach (['C-4', 'C-5', 'C-8'] as $mark) {
    echo str_repeat('=', 100)."\nREGISTER ROW {$mark}\n".str_repeat('=', 100)."\n";
    preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $xml, $rows, PREG_OFFSET_CAPTURE);
    foreach ($rows[0] as $r) {
        $cells = $cellsOf($r[0]);
        if (! isset($cells[0])) {
            continue;
        }
        if (ltrim($cells[0]) !== $mark) {
            continue;
        }
        echo '@'.$r[1].'  cells='.count($cells)."\n";
        foreach ($cells as $i => $c) {
            echo '  ['.$i.'] '.mb_substr($c, 0, 300)."\n";
        }
        echo "\n";
    }
}

echo str_repeat('=', 100)."\n§24 COURSES COLUMN LISTING\n".str_repeat('=', 100)."\n";
if (preg_match_all('/courses — (\d+) columns/', preg_replace('/\s+/', ' ', strip_tags($xml)), $hm)) {
    echo 'heading reads: '.implode(' | ', $hm[0])."\n";
}
// The table that owns that heading: the last heading before each <w:tbl>.
preg_match_all('/<w:tbl>.*?<\/w:tbl>/s', $xml, $tables, PREG_OFFSET_CAPTURE);
foreach ($tables[0] as $t) {
    $before = substr($xml, max(0, $t[1] - 8000), min($t[1], 8000));
    $plain = preg_replace('/\s+/', ' ', strip_tags($before));
    if (! preg_match('/([a-z_]+) — (\d+) columns/', $plain, $h)) {
        continue;
    }
    if ($h[1] !== 'courses') {
        continue;
    }
    $rows = [];
    preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $t[0], $rm);
    foreach ($rm[0] as $k => $row) {
        $cells = $cellsOf($row);
        echo '  row '.$k.': '.implode(' § ', array_map(fn ($c) => mb_substr($c, 0, 46), $cells))."\n";
    }
    echo '  table offset @'.$t[1].'  rows='.count($rm[0])."\n";
    break;
}

echo "\n".str_repeat('=', 100)."\nREVISION LOG — last rows\n".str_repeat('=', 100)."\n";
$plainAll = preg_replace('/\s+/', ' ', strip_tags($xml));
foreach (['Revision log', 'REVISION LOG', 'Revision Log', 'Change log', 'Document control'] as $needle) {
    $at = strpos($plainAll, $needle);
    echo $needle.': '.($at === false ? 'absent' : 'at plain offset '.$at)."\n";
}
