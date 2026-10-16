<?php
$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive(); $zip->open($src); $xml = $zip->getFromName('word/document.xml'); $zip->close();
preg_match_all('/<w:t(?: [^>]*)?>.*?<\/w:t>/s', $xml, $m, PREG_OFFSET_CAPTURE);
foreach (array_filter(explode('|', $argv[1] ?? '')) as $re) {
    echo "\n##### /{$re}/\n";
    $n = 0;
    foreach ($m[0] as $i => $run) {
        if (preg_match("/{$re}/", $run[0])) { echo json_encode($run[0], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)." @".$run[1]."\n"; if (++$n > 12) { echo "...\n"; break; } }
    }
    echo "matches: $n\n";
}
