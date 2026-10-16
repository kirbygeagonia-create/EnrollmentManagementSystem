<?php
// What header does each docx table actually carry? (lane 5's first pass found no "status" column,
// which is either a clean document or a wrong detector — this tells us which.)
$z = new ZipArchive;
$z->open('Documentation/EMS Complete Documentation.docx');
$xml = $z->getFromName('word/document.xml');
$z->close();

$tables = preg_split('/<w:tbl>/', $xml);
foreach (array_slice($tables, 1) as $ti => $chunk) {
    $body = substr($chunk, 0, (int) strpos($chunk, '</w:tbl>'));
    $rows = preg_split('/<w:tr[\s>]/', $body);
    if (count($rows) < 2) {
        continue;
    }
    $cells = [];
    foreach (preg_split('/<w:tc>/', $rows[1] ?? '') as $c) {
        if (! preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $c, $m)) {
            continue;
        }
        $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(implode(' ', $m[1]), ENT_QUOTES | ENT_XML1)));
        if ($t !== '') {
            $cells[] = $t;
        }
    }
    $rowTotal = count($rows) - 1;
    printf("T%-3d rows=%-4d %s\n", $ti + 1, $rowTotal, substr(implode(' / ', $cells), 0, 150));
}
