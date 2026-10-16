<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));
foreach (['21. Reference Data', 'which table controls which decision', 'Reference Data → Courses',
          'from the screen', 'dialog', 'Edit'] as $n) {
    printf("%-42s plain=%d raw=%d\n", $n, substr_count($plain,$n), substr_count($xml,$n));
}
$p = strpos($plain, '21. Reference Data');
echo "\n§21 OPENING:\n".mb_substr($plain, $p, 700)."\n";
// §21.x subsection headings
preg_match_all('/21\.\d+ ([A-Z][^.]{3,60})/', $plain, $hm);
echo "\n§21 SUBSECTIONS:\n  ".implode("\n  ", array_slice($hm[0], 0, 12))."\n";
