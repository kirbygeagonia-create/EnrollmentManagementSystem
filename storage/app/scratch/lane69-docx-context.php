<?php
/**
 * Lane 69 probe: print the sentence around every occurrence of the two spellings in the flat docx dump.
 * Built in PHP with substr(), not grep with a bounded .\{0,700\} — that pattern backtracks for minutes on
 * a single 23,074-byte flat-dump line.
 */

$dump = dirname(__DIR__, 3).'/storage/app/scratch/docx-flat.txt';
$text = (string) file_get_contents($dump);

$needles = $argv[1] ?? 'Year 2|2nd Year|four vocabularies|statusLabel.js';
foreach (explode('|', $needles) as $needle) {
    echo "\n########## '".$needle."'\n";
    $at = 0;
    $found = 0;
    while (($pos = strpos($text, $needle, $at)) !== false) {
        $found++;
        $from = max(0, $pos - 320);
        $to = min(strlen($text), $pos + strlen($needle) + 200);
        echo "\n--- hit {$found} at byte {$pos}\n";
        echo str_replace("\n", ' ⏎ ', substr($text, $from, $to - $from))."\n";
        $at = $pos + strlen($needle);
    }
    echo $found === 0 ? "  (none)\n" : "  {$found} hit(s)\n";
}
