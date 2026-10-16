<?php

/**
 * Pass 80 probe — where the error-surface paragraph ends, and whether the chosen tail
 * needle is unique. Also reports the file:// citations that name the two upload routes,
 * so the pass can cite line numbers that still exist.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$esc = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

foreach ([
    'rather than a reproduced bug.',
    'which is what the first attempt at it did.',
    'Nine tests pin the two renderers',
] as $needle) {
    printf("%-45s %d\n", $needle, substr_count($xml, $esc($needle)));
}

$at = strpos($xml, $esc('rather than a reproduced bug.'));
$window = substr($xml, max(0, $at - 3000), 3400);
$flat = preg_replace('/<[^>]+>/', '', $window);
echo "\n--- text around the tail (tag-stripped) ---\n".substr($flat, -900)."\n";
