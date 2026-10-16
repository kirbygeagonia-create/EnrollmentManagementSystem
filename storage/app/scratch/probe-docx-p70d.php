<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
foreach (['rooms has one', 'scholarshiptypes', 'gradescale, settings', 'no rows from the seeder', 'has one'] as $n) {
    printf("%-24s raw=%d\n", $n, substr_count($xml, $n));
}
$p = strpos($xml, 'rooms has one');
if ($p === false) { exit("no anchor\n"); }
$s = max(strrpos(substr($xml,0,$p), '<w:p>'), strrpos(substr($xml,0,$p), '<w:p '));
$e = strpos($xml, '</w:p>', $p) + 6;
preg_match_all('/<w:t(?: [^>]*)?>([^<]*)<\/w:t>/s', substr($xml,$s,$e-$s), $tm);
echo "paragraph @{$s}..{$e} runs=".count($tm[1])."\n";
foreach ($tm[1] as $i => $t) { echo "  $i: ".var_export(mb_substr(html_entity_decode($t, ENT_QUOTES|ENT_XML1),0,110), true)."\n"; }
