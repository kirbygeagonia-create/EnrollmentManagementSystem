<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));
foreach (['21.2 Inventory', '21.5 Verification'] as $h) {
    $p = strpos($plain, $h);
    echo "=== $h @ $p ===\n".mb_substr($plain, $p, 1150)."\n\n";
}
