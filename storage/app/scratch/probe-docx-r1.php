<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$rs = 200455; $re = strpos($xml, '</w:tr>', $rs) + 7;
preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($xml, $rs, $re - $rs), $rm);
foreach ($rm[0] as $k => $cc) {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cc, $t2);
    echo "R1 CELL [$k]: ".preg_replace('/\s+/', ' ', html_entity_decode(implode('', $t2[1]), ENT_QUOTES | ENT_XML1))."\n\n";
}
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));
$p = strpos($plain, '11.6 Verification Against the Current Build');
echo "§11.6 BODY:\n".mb_substr($plain, $p, 1200)."\n";
