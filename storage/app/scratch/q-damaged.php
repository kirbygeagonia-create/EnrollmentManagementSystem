<?php
$z = new ZipArchive(); $z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$x = $z->getFromName('word/document.xml'); $z->close();
foreach (['though the row carries a NULL enrollment', 'attributed to the first seated student'] as $n) {
    $p = strpos($x, $n);
    $s = strrpos(substr($x, 0, $p), '<w:t'); $e = strpos($x, '</w:t>', $p);
    echo "\n--- ".substr($n, 0, 30)."\n".trim(preg_replace('/\s+/', ' ', substr($x, $s, $e - $s)))."\n";
}
