<?php

/**
 * Lane 70: print, for every unattributed pointer the sweep reports, the whole sentence that owns it and every
 * file named anywhere on the same dump line. This is the reading sheet: the owner of a bare `:NN` is usually
 * named on the same line beyond the 160-character window, and when it is not, the sentence itself says which
 * construct the number describes.
 *
 *   php storage/app/scratch/lane70-blind-sheet.php
 */
$root = dirname(__DIR__, 3);
$php = PHP_BINARY;
$sweep = $root.'/storage/app/scratch/sweep-docx-citations.php';
exec(escapeshellarg($php).' '.escapeshellarg($sweep).' 2>&1', $out, $rc);
$tokens = [];
foreach ($out as $l) {
    if (preg_match('/UNATTRIBUTED :(\d+(?:-\d+)?)/', $l, $m) === 1) {
        $tokens[$m[1]] = true;
    }
}
$tokens = array_keys($tokens);
sort($tokens, SORT_STRING);
echo count($tokens)." distinct unattributed number(s)\n";

$lines = explode("\n", (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt'));
$fileRe = '/[A-Za-z0-9_.\-\/]+\.(?:php|jsx|js|md|json|neon|xml)/';
$seen = [];
foreach ($tokens as $tok) {
    foreach ($lines as $i => $raw) {
        if (! str_contains($raw, ':'.$tok)) {
            continue;
        }
        $line = preg_replace('/\s+/', ' ', html_entity_decode(str_replace('&quot;', '"', $raw), ENT_QUOTES));
        // the sentence holding the number
        $at = strpos($line, ':'.$tok);
        $start = max(0, (int) strrpos(substr($line, 0, $at), '.'));
        $sent = trim(mb_substr($line, $start, 340));
        preg_match_all($fileRe, $line, $fm);
        $files = array_values(array_unique($fm[0]));
        $key = $i.'|'.$tok;
        if (isset($seen[$sent.'|'.$tok])) {
            continue;
        }
        $seen[$sent.'|'.$tok] = true;
        printf("\n:%-9s dump line %-5d  files on the line: %s\n            %s\n",
            $tok, $i + 1, $files === [] ? 'NONE' : implode(', ', array_slice($files, 0, 4)), $sent);
    }
}
