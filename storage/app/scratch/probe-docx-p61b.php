<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));
$p = strpos($plain, '28.5');
echo "§28.5 heading area:\n".mb_substr($plain, $p, 400)."\n\n";
preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $xml, $rows, PREG_OFFSET_CAPTURE);
foreach ($rows[0] as $r) {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $r[0], $cm);
    $cells = [];
    foreach ($cm[0] as $c) { preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $c, $tm);
      $cells[] = preg_replace('/\s+/',' ', html_entity_decode(implode('',$tm[1]), ENT_QUOTES|ENT_XML1)); }
    $j = implode(' | ', $cells);
    if (preg_match('/(^|\s|;|，)(C-4|C-5|C-8)(\s|$|;|,)/', $j) && $r[1] > 5000000) {
        echo "ROW @{$r[1]} cells=".count($cells).":\n  ".mb_substr($j, 0, 700)."\n\n";
    }
}
