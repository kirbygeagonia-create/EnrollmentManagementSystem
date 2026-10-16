<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));
$p = strpos($plain, '1.1 Revision Record');
echo "REVISION RECORD:\n".mb_substr($plain, $p, 2600)."\n\n";
foreach (['C-4','C-5','C-8'] as $m) {
  echo "=== §28.5 mentions of {$m} ===\n";
  $scan = 0; $k = 0;
  while (($q = strpos($plain, $m, $scan)) !== false && $k < 6) {
    $ctx = mb_substr($plain, max(0,$q-90), 200);
    if (stripos($ctx, 'decision') !== false || $q > strlen($plain) - 40000) { echo " @{$q}: ".$ctx."\n"; $k++; }
    $scan = $q + 1;
  }
  echo "\n";
}
