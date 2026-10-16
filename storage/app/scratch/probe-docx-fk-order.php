<?php
/**
 * Print §23.3's inventory rows in document order, so a new edge can be placed in the group the
 * chapter already uses ("grouped by the table that holds the key").
 */

$z = new ZipArchive();
$z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$xml = $z->getFromName('word/document.xml');
$zipClose = true;
$z->close();

preg_match_all('/<w:tbl>.*?<\/w:tbl>/s', $xml, $tables, PREG_OFFSET_CAPTURE);

$cellText = function (string $cell): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $m);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1)));
};

foreach ($tables[0] as $t) {
    preg_match('/<w:tr>.*?<\/w:tr>/s', $t[0], $first);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $first[0], $cs);
    $head = array_map($cellText, $cs[0]);
    if ($head !== ['Child column', 'References', 'On delete', 'On update']) {
        continue;
    }
    printf("table @ %d, %d chars\n", $t[1], strlen($t[0]));
    preg_match_all('/<w:tr>.*?<\/w:tr>/s', $t[0], $trs, PREG_OFFSET_CAPTURE);
    $i = 0;
    foreach ($trs[0] as $row) {
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row[0], $c2);
        $v = array_map($cellText, $c2[0]);
        if (count($v) !== 4 || $v[0] === 'Child column') {
            continue;
        }
        printf("%2d  %-52s %-40s %-10s %-8s  rowEnd@%d\n",
            ++$i, $v[0], $v[1], $v[2], $v[3], $t[1] + $row[1] + strlen($row[0]));
    }
    echo "rows: $i\n";

    break;
}
