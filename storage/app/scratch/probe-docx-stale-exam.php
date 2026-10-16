<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));
$dead = ['138-157','158-167','189-191','195-201','209-213','221-245','247-250','252-260','263-274',
         '176-278','236-245','243-245','56-64','96-130','69-91','83-90'];
foreach ($dead as $d) {
    $n = substr_count($plain, $d);
    if ($n === 0) continue;
    $p = strpos($plain, $d);
    printf("%-9s x%d  %s\n", $d, $n, mb_substr(preg_replace('/\s+/',' ', substr($plain, max(0,$p-95), 210)), 0, 200));
}
