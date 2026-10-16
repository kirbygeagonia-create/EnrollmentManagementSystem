<?php
$z = new ZipArchive(); $z->open(dirname(__DIR__, 2).'/../Documentation/EMS Complete Documentation.docx');
$x = $z->getFromName('word/document.xml'); $z->close();
$flat = preg_replace('/<[^>]+>/', ' ', $x);
$pos = strpos($flat, 'To make §25 true of the software');
echo "context before the table:\n  ".trim(preg_replace('/\s+/', ' ', substr($flat, max(0, $pos - 500), 560)))."\n\n";
preg_match_all('/2[5-7]\.[0-9] [A-Z][^0-9]{5,60}/', $flat, $m);
echo "section headings 25-27:\n";
foreach (array_slice(array_unique($m[0]), 0, 14) as $h) { echo '  '.trim($h)."\n"; }
