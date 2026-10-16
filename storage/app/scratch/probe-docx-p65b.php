<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
printf("raw :263-274 = %d\n", substr_count($xml, ':263-274'));
$scan = 0;
while (($p = strpos($xml, 'AdmissionPolicy.php:69-91', $scan)) !== false) {
    $s = max(strrpos(substr($xml,0,$p), '<w:p>'), strrpos(substr($xml,0,$p), '<w:p '));
    $e = strpos($xml, '</w:p>', $p) + 6;
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', substr($xml,$s,$e-$s), $tm);
    echo "\n @{$p} para @{$s} runs=".count($tm[1]).":\n  ".preg_replace('/\s+/', ' ', html_entity_decode(implode('',$tm[1]), ENT_QUOTES|ENT_XML1))."\n";
    $scan = $p + 1;
}
$q = strpos($xml, ':263-274');
if ($q !== false) {
    $s = max(strrpos(substr($xml,0,$q), '<w:p>'), strrpos(substr($xml,0,$q), '<w:p '));
    $e = strpos($xml, '</w:p>', $q) + 6;
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', substr($xml,$s,$e-$s), $tm2);
    echo "\n:263-274 para @{$s} runs=".count($tm2[1]).":\n  ".preg_replace('/\s+/', ' ', html_entity_decode(implode('',$tm2[1]), ENT_QUOTES|ENT_XML1))."\n";
    foreach ($tm2[1] as $i => $r) { echo "   run $i: ".var_export(html_entity_decode($r[0], ENT_QUOTES|ENT_XML1), true)."\n"; }
}
