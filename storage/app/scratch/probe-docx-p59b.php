<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
// Show the run boundaries inside the §11.6 paragraph so a contiguous needle can be chosen.
$p = strpos($xml, 'ExamPolicy.php:56-64', 610000);
$s = max(strrpos(substr($xml, 0, $p), '<w:p>'), strrpos(substr($xml, 0, $p), '<w:p '));
$e = strpos($xml, '</w:p>', $p) + 6;
preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', substr($xml, $s, $e - $s), $tm, PREG_OFFSET_CAPTURE);
echo "paragraph @{$s}..{$e}, runs=".count($tm[1]).":\n";
foreach ($tm[1] as $k => $r) { echo "  run $k: ".var_export(html_entity_decode($r[0], ENT_QUOTES | ENT_XML1), true)."\n"; }
