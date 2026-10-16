<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));
foreach (['GuidanceStaff', 'SysAdmin', 'Admin '] as $n) {
    $scan = 0; $k = 0;
    while (($p = strpos($plain, $n, $scan)) !== false && $k < 3) {
        echo "@{$p} [{$n}]  ".mb_substr(preg_replace('/\s+/',' ',substr($plain,$p,190)),0,180)."\n";
        $scan = $p + 1; $k++;
    }
    echo "\n";
}
// any phrase like "seventy permissions" / counts per role in 22.3
foreach (['role_permissions rows', 'grants reconcile', 'the two-role offset'] as $n) {
    $p = strpos($plain, $n);
    if ($p !== false) { echo "[{$n}] @{$p}\n  ".mb_substr(preg_replace('/\s+/',' ',substr($plain,$p-200,420)),0,400)."\n\n"; }
}
