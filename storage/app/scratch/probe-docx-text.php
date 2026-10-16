<?php
// Flat text of the element (paragraph or row) holding a literal, so sentences can be
// read without stripping the whole document.
$z = new ZipArchive(); $z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$xml = $z->getFromName('word/document.xml'); $z->close();
foreach (array_filter(explode('|', $argv[1] ?? ''), fn($t) => trim($t) !== '') as $target) {
    $pos = strpos($xml, $target);
    echo "\n##### ".json_encode($target)." at {$pos}\n";
    if ($pos === false) { echo "NOT FOUND\n"; continue; }
    $start = strrpos(substr($xml, 0, $pos), '<w:p>');
    $end = strpos($xml, '</w:p>', $pos) + 6;
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', substr($xml, $start, $end - $start), $m);
    echo preg_replace('/\s+/', ' ', implode('|', $m[1]))."\n";
}
