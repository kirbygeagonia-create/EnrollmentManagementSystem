<?php
$z = new ZipArchive(); $z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$xml = $z->getFromName('word/document.xml'); $z->close();
$at = (int) $argv[1]; $before = (int) ($argv[2] ?? 600); $after = (int) ($argv[3] ?? 900);
echo preg_replace('/></', ">\n<", substr($xml, max(0, $at - $before), $before + $after))."\n";
