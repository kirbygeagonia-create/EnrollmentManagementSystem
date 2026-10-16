<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
foreach ([
 'B2 cond' => 'General entrance result = fail',
 'B2 effect tail' => 'stage 4 is not administered',
 'B2 effect prefix' => 'Admission cannot be approved; the pending admission',
 '10.6 tail' => 'so no live applicant could ever have been blocked by it',
] as $l => $n) { printf("%-18s raw=%d\n", $l, substr_count($xml, $n)); }
