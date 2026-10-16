<?php

// Print every <w:t> element of the paragraphs overlapping a target, verbatim and
// quoted, so replacement needles can be copied exactly instead of reconstructed.

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

foreach (array_filter(explode('|', $argv[1] ?? '')) as $target) {
    $pos = strpos($xml, $target);
    echo "\n##### ".json_encode($target)." at {$pos}\n";
    if ($pos === false) {
        echo "NOT FOUND\n";
        continue;
    }
    $start = strrpos(substr($xml, 0, $pos), '<w:p>');
    $end = strpos($xml, '</w:p>', $pos) + 6;
    $para = substr($xml, $start, $end - $start);
    preg_match_all('/<w:t(?: [^>]*)?>.*?<\/w:t>/s', $para, $m);
    foreach ($m[0] as $i => $run) {
        echo str_pad((string) $i, 3, ' ', STR_PAD_LEFT).' '.json_encode($run, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
    }
}
