<?php
// Why can't pass 22 find a row to clone in documentprintlog? Dump the row shapes.

$z = new ZipArchive();
$z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$xml = $z->getFromName('word/document.xml');
$z->close();

preg_match_all('/<w:tbl>.*?<\/w:tbl>/s', $xml, $tables, PREG_OFFSET_CAPTURE);
foreach ($tables[0] as $t) {
    $plain = preg_replace('/\s+/', ' ', strip_tags(substr($xml, max(0, $t[1] - 6000), min($t[1], 6000))));
    if (! preg_match_all('/(documentprintlog|payments|enrollments) — (\d+) columns/', $plain, $hm, PREG_SET_ORDER)) {
        continue;
    }
    $head = $hm[count($hm) - 1];
    echo "\n=== block for {$head[1]} (heading says {$head[2]} columns) at {$t[1]}, bytes=".strlen($t[0])."\n";
    preg_match_all('/<w:tr>.*?<\/w:tr>/s', $t[0], $trs, PREG_OFFSET_CAPTURE);
    echo 'rows found: '.count($trs[0])."\n";
    foreach (array_slice($trs[0], 0, 12) as $j => $row) {
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row[0], $cells);
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $row[0], $texts);
        printf("  row %d cells=%d text=[%s]\n", $j, count($cells[0]), mb_substr(implode(' | ', $texts[1]), 0, 90));
    }
}
