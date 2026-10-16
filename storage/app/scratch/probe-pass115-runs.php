<?php
$z = new ZipArchive;
$z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$x = $z->getFromName('word/document.xml'); $z->close();
$at = strpos($x, 'What this does not do is rewrite history');
echo str_replace(['<w:', '</w:'], ['<w:', '</w:'], substr($x, $at - 60, 2600)), "\n";
