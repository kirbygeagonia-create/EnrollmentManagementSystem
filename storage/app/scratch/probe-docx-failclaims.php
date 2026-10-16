<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));
foreach (['set to rejected','sets admissionStatus','is set to rejected','becomes rejected','admissionStatus = rejected','set to approved','sets it to rejected','automatically reject'] as $n) {
    $c = substr_count($plain, $n);
    if ($c === 0) continue;
    $scan = 0;
    while (($p = strpos($plain, $n, $scan)) !== false && $scan < 10000000) {
        printf("%-24s @%-8d %s\n", $n, $p, mb_substr(preg_replace('/\s+/',' ',substr($plain, max(0,$p-110), 230)),0,220));
        $scan = $p + 1;
        echo "\n";
    }
}
