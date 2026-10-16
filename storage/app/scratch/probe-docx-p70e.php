<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$p = strpos($xml, 'no rows from the seeder');
$s = max(strrpos(substr($xml,0,$p), '<w:p>'), strrpos(substr($xml,0,$p), '<w:p '));
$e = strpos($xml, '</w:p>', $p) + 6;
$para = substr($xml,$s,$e-$s);
preg_match_all('/<w:t(?: [^>]*)?>([^<]*)<\/w:t>/s', $para, $tm);
echo "paragraph @{$s}..{$e} bytes=".strlen($para)." runs=".count($tm[1])."\n";
foreach ($tm[1] as $i => $t) { echo "  $i: ".var_export(html_entity_decode($t, ENT_QUOTES|ENT_XML1), true)."\n"; }
// is the anchor inside a table cell?
$tc = strrpos(substr($xml,0,$p), '<w:tc>'); $tr = strrpos(substr($xml,0,$p), '<w:tr');
echo "in-cell=".var_export($tc !== false && $tr !== false && $tc > $tr, true)."\n";
