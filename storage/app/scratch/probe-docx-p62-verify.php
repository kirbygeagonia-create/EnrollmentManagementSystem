<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
echo '(this issue) copies: '.substr_count($xml, '(this issue)')."\n";
$h = strpos($xml, '1.1 Revision Record');
$ts = strpos($xml, '<w:tbl>', $h); $te = strpos($xml, '</w:tbl>', $ts) + 8;
preg_match_all('/<w:tr\b.*?<\/w:tr>/s', substr($xml, $ts, $te-$ts), $rm);
echo "revision rows: ".count($rm[0])."\n";
foreach ($rm[0] as $k => $row) {
  preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
  preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cm[0][0], $tm);
  preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cm[0][1], $tm2);
  echo "  [$k] ".html_entity_decode(implode('',$tm[1]), ENT_QUOTES|ENT_XML1)."  ||  ".mb_substr(preg_replace('/\s+/',' ',html_entity_decode(implode('',$tm2[1]), ENT_QUOTES|ENT_XML1)),0,110)."\n";
}
