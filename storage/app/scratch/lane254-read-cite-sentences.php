<?php

/**
 * Lane 254 — print the sentence around a citation token, from the flat dump.
 *
 * `lane70-read-span.php` resolves basenames inside app/, resources/js, database/seeders and resources/views only,
 * so it cannot open a HEAD export; and a token's meaning lives in the document's own prose, not in the file. This
 * prints each occurrence with its surrounding characters so a map or a skip is written from the sentence.
 *
 * Usage: php storage/app/scratch/lane254-read-cite-sentences.php 693-844 621 1194-1235
 */
$tokens = array_slice($GLOBALS['argv'], 1);
if ($tokens === []) {
    fwrite(STDERR, "REFUSED: at least one token is required.\n");
    exit(1);
}

$root = dirname(__DIR__, 3);
$dump = $root.'/storage/app/scratch/docx-flat.txt';
$text = file_get_contents($dump);
if (! is_string($text) || $text === '') {
    fwrite(STDERR, "REFUSED: {$dump} is missing or empty — run dump-docx-flat.php first.\n");
    exit(1);
}

foreach ($tokens as $token) {
    $at = 0;
    $hits = 0;
    echo "=== [{$token}] ===\n";
    while (($i = strpos($text, $token, $at)) !== false) {
        $hits++;
        $from = max(0, $i - 400);
        $window = substr($text, $from, strlen($token) + 620);
        echo "  hit {$hits}: ".preg_replace('/\s+/', ' ', $window)."\n\n";
        $at = $i + strlen($token);
    }
    if ($hits === 0) {
        echo "  (no occurrence in the flat dump — the runs may split it; probe document.xml)\n\n";
    }
}
