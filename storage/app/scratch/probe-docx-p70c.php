<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
foreach ([
 '21.5 tail' => 'religions has one; rooms has one.',
 '21.5 mid' => 'Confirmed: scholarshiptypes, gradescale, settings and blocks have no rows',
 'rev label 10-07' => '2026-10-07 (this issue)',
 'rev label 10-06' => '2026-10-04–06',
 'rev desc 10-07' => 'The three institutional questions the owner answered on 2026-10-07',
] as $l => $n) { printf("%-16s raw=%d\n", $l, substr_count($xml, $n)); }
// run boundaries of the §21.5 paragraph
$p = strpos($xml, 'religions has one; rooms has one.');
$s = max(strrpos(substr($xml,0,$p), '<w:p>'), strrpos(substr($xml,0,$p), '<w:p '));
$e = strpos($xml, '</w:p>', $p) + 6;
preg_match_all('/<w:t(?: [^>]*)?>([^<]*)<\/w:t>/s', substr($xml,$s,$e-$s), $tm);
echo "\nparagraph @{$s} runs=".count($tm[1]).":\n";
foreach ($tm[1] as $i => $t) { echo "  $i: ".var_export(mb_substr(html_entity_decode($t, ENT_QUOTES|ENT_XML1),0,96), true)."\n"; }
