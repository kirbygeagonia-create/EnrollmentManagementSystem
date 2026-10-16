<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));
foreach (['enrollment’s own term', 'recorded against that enrollment', 'signBlockers() refuses the Department Evaluation signature'] as $n) {
    printf("%-56s raw=%d\n", mb_substr($n,0,50), substr_count($xml, $n));
}
$p = strpos($plain, 'signBlockers() refuses the Department Evaluation');
echo "\nCONTEXT:\n".mb_substr($plain, $p-260, 700)."\n";
