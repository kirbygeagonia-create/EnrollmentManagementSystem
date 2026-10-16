<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$p = 551650;
$rs = strrpos(substr($xml,0,$p), '<w:tr'); $re = strpos($xml, '</w:tr>', $p) + 7;
preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($xml,$rs,$re-$rs), $cm);
foreach ($cm[0] as $k => $c) {
  preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $c, $tm);
  echo "[$k] ".preg_replace('/\s+/',' ', html_entity_decode(implode('',$tm[1]), ENT_QUOTES|ENT_XML1))."\n";
}
// and the paragraph around 1093546: what section owns it
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(substr($xml, 1080000, 14000)), ENT_QUOTES|ENT_XML1));
echo "\nNEARBY:\n".mb_substr($plain, 0, 600)."\n";
