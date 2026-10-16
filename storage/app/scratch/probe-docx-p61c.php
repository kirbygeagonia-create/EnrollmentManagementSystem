<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));
// the paragraph text just before that table
$p = 5479399;
$s = strrpos(substr($xml, 0, $p), '<w:tbl>');
$before = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(substr($xml, max(0,$s-3000), $s - max(0,$s-3000))), ENT_QUOTES | ENT_XML1));
echo "BEFORE THE TABLE:\n".mb_substr($before, -700)."\n\n";
preg_match_all('/<w:tr\b.*?<\/w:tr>/s', substr($xml, $s, 12000), $rm);
foreach (array_slice($rm[0], 0, 3) as $k => $row) {
  preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm); $o=[];
  foreach ($cm[0] as $c) { preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $c, $tm); $o[] = preg_replace('/\s+/',' ', html_entity_decode(implode('',$tm[1]), ENT_QUOTES|ENT_XML1)); }
  echo "row $k: ".mb_substr(implode(' | ', $o), 0, 300)."\n";
}
// G-6 row cells
$g = strpos($xml, 'An absent Course-Specific Examination result did not block admission approval');
$rs = strrpos(substr($xml, 0, $g), '<w:tr'); $re = strpos($xml, '</w:tr>', $g) + 7;
preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($xml, $rs, $re-$rs), $cm2);
echo "\nG-6 ROW @{$rs} cells=".count($cm2[0]).":\n";
foreach ($cm2[0] as $k => $c) { preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $c, $tm);
  echo " [$k] ".preg_replace('/\s+/',' ', html_entity_decode(implode('',$tm[1]), ENT_QUOTES|ENT_XML1))."\n"; }
