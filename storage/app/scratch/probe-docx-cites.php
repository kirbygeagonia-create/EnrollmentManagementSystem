<?php
// Which docx sentences cite a file, and do they carry line numbers that an edit would shift?
$z = new ZipArchive();
$path = 'Documentation/EMS Complete Documentation.docx';
if (! $z->open($path)) {
    exit("cannot open $path\n");
}
$xml = $z->getFromName('word/document.xml');
$z->close();
$text = preg_replace('/<[^>]+>/', '', $xml);
$text = html_entity_decode($text, ENT_QUOTES | ENT_XML1);

foreach (['MegaAppLauncher', 'HandleInertiaRequests', 'DeskSubNav'] as $needle) {
    $hits = [];
    if (preg_match_all('/[^\n]{0,110}'.$needle.'[^\n]{0,150}/', $text, $m)) {
        $hits = $m[0];
    }
    echo "== $needle: ".count($hits)." sentence fragment(s) ==\n";
    foreach (array_slice($hits, 0, 8) as $h) {
        echo '  '.trim(preg_replace('/\s+/', ' ', $h))."\n";
    }
    echo "\n";
}
