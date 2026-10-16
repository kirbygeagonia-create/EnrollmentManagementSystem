<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
foreach ([
 'bulk two writes' => 'Two such writes exist',
 'bulk ExamController cite' => 'ExamController.php:210-213',
 'step4 row anchor' => 'a entrance/general result exists and is pass',
 'window para anchor' => 'before the admission decision can be approved',
 'notification cite' => 'NotificationController.php:65-68',
] as $l => $n) { printf("%-24s %d\n", $l, substr_count($xml, $n)); }
// run boundaries of the bulk-write paragraph
$p = strpos($xml, 'Two such writes exist');
$s = max(strrpos(substr($xml,0,$p), '<w:p>'), strrpos(substr($xml,0,$p), '<w:p '));
$e = strpos($xml, '</w:p>', $p) + 6;
preg_match_all('/<w:t(?: [^>]*)?>([^<]*)<\/w:t>/s', substr($xml,$s,$e-$s), $tm);
foreach ($tm[1] as $i => $t) { echo "run $i: ".var_export(mb_substr(html_entity_decode($t, ENT_QUOTES|ENT_XML1),0,80), true)."\n"; }
