<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$scan = 0; $k = 0;
while (($p = strpos($xml, 'ExamPolicy.php:56-64', $scan)) !== false) {
    $k++;
    $s = max(strrpos(substr($xml, 0, $p), '<w:p>'), strrpos(substr($xml, 0, $p), '<w:p '));
    $e = strpos($xml, '</w:p>', $p) + 6;
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', substr($xml, $s, $e - $s), $tm);
    echo "--- occurrence $k @{$p} ---\n".preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1))."\n\n";
    $scan = $p + 1;
}
