<?php
$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive(); $zip->open($src); $xml = $zip->getFromName('word/document.xml'); $zip->close();
$target = $argv[1];
$pos = strpos($xml, $target);
$start = strrpos(substr($xml, 0, $pos), '<w:tbl>');
$end = strpos($xml, '</w:tbl>', $pos) + 8;
$table = substr($xml, $start, $end - $start);
$rows = preg_split('/(?=<w:tr>)/', $table);
foreach ($rows as $r => $row) {
    preg_match_all('/<w:t(?: [^>]*)?>.*?<\/w:t>/s', $row, $m);
    if ($m[0] === []) continue;
    $texts = array_map(fn($t) => preg_replace('/^<w:t(?: [^>]*)?>|<\/w:t>$/', '', $t), $m[0]);
    $cells = preg_split('/(?=<w:tc>)/', $row);
    $out = [];
    foreach ($cells as $cell) {
        preg_match_all('/<w:t(?: [^>]*)?>.*?<\/w:t>/s', $cell, $cm);
        if ($cm[0] === []) continue;
        $out[] = trim(preg_replace('/\s+/', ' ', implode('', array_map(fn($t) => preg_replace('/^<w:t(?: [^>]*)?>|<\/w:t>$/', '', $t), $cm[0]))));
    }
    echo "row {$r}: ".json_encode($out, JSON_UNESCAPED_UNICODE)."\n";
}
echo "\ntable span: {$start}..{$end}\n";
