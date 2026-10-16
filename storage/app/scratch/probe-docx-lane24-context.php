<?php

/**
 * What does the authority docx currently say about the private folder, the served /storage path,
 * and APP_KEY? Pass 105 has to follow the code, so this reads the docx's own sentences first —
 * including whether it repeats the claim DEPLOYMENT.md §2 just lost.
 */
$root = dirname(__DIR__, 3);
$zip = new ZipArchive();
$zip->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$needles = [
    'Preflight.php:82-96',
    'ems:preflight turns DEPLOYMENT.md into a command',
    'storage/app/private',
    'no web path',
    '/storage',
    'APP_KEY',
    'visibility',
    'serve',
    'signed URL',
    'signature',
    'documents disk',
];

foreach ($needles as $n) {
    $count = substr_count($xml, $n);
    printf("\n#### %s  → %d occurrence(s) in the raw XML\n", $n, $count);
    if ($count === 0) {
        continue;
    }
    $pos = 0;
    $shown = 0;
    while (($at = strpos($xml, $n, $pos)) !== false && $shown < 3) {
        $start = max(0, $at - 260);
        $chunk = substr($xml, $start, 520);
        $text = preg_replace('/\s+/', ' ', strip_tags($chunk));
        printf("   [%5d] …%s…\n", $at, mb_substr($text, 0, 300));
        $pos = $at + strlen($n);
        $shown++;
    }
}
