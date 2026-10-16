<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$n = 'until a pass is recorded against that enrollment’s own term';
printf("needle raw=%d\n", substr_count($xml, $n));
