<?php
$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive(); $zip->open($src); $xml = $zip->getFromName('word/document.xml'); $zip->close();
foreach (array_filter(explode('|', $argv[1] ?? ''), fn($t) => trim($t) !== '') as $target) {
    $pos = strpos($xml, $target);
    echo "\n##### ".json_encode($target)." at {$pos}\n";
    if ($pos === false) { echo "NOT FOUND\n"; continue; }
    $start = strrpos(substr($xml, 0, $pos), '<w:tr');
    $end = strpos($xml, '</w:tr>', $pos) + 7;
    $row = substr($xml, $start, $end - $start);
    preg_match_all('/<w:t(?: [^>]*)?>.*?<\/w:t>/s', $row, $m);
    foreach ($m[0] as $i => $run) {
        echo str_pad((string) $i, 3, ' ', STR_PAD_LEFT).' '.json_encode($run, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
    }
}
