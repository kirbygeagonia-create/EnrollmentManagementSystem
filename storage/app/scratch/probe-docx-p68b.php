<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$p = strpos($xml, 'recorded against that enrollment');
$s = max(strrpos(substr($xml,0,$p), '<w:p>'), strrpos(substr($xml,0,$p), '<w:p '));
$e = strpos($xml, '</w:p>', $p) + 6;
preg_match_all('/<w:t(?: [^>]*)?>([^<]*)<\/w:t>/s', substr($xml,$s,$e-$s), $tm);
echo "para @{$s}..{$e} runs=".count($tm[1])." bytes=".($e-$s)."\n";
echo html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)."\n\nRUNS:\n";
foreach ($tm[1] as $i => $t) { echo "  $i: ".var_export(mb_substr(html_entity_decode($t, ENT_QUOTES|ENT_XML1),0,72), true)."\n"; }
