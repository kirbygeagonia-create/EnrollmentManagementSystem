<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
foreach (['1.1 Revision Record', '2026-10-04'] as $n) { printf("count [%s] = %d\n", $n, substr_count($xml, $n)); }
$h = strpos($xml, '1.1 Revision Record');
$tblStart = strpos($xml, '<w:tbl>', $h); $tblEnd = strpos($xml, '</w:tbl>', $tblStart) + 8;
echo "heading @{$h} table @{$tblStart}..{$tblEnd}\n";
preg_match_all('/<w:tr\b.*?<\/w:tr>/s', substr($xml, $tblStart, $tblEnd-$tblStart), $rm, PREG_OFFSET_CAPTURE);
echo "rows=".count($rm[0])."\n";
foreach ($rm[0] as $k => $r) {
  preg_match_all('/<w:tc>.*?<\/w:tc>/s', $r[0], $cm); $o=[];
  foreach ($cm[0] as $c) { preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $c, $tm);
    $o[] = preg_replace('/\s+/',' ', html_entity_decode(implode('',$tm[1]), ENT_QUOTES|ENT_XML1)); }
  echo " row $k cells=".count($cm[0])." paras=".substr_count($r[0], '<w:p>').": ".mb_substr(implode(' § ', $o),0,140)."\n";
}
