<?php

// Dump the XML context around specific <w:t> runs so run-level replacements can be
// written against what the file actually contains, not against a reconstruction.

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$targets = $argv[1] ?? '';
foreach (array_filter(explode('|', $targets)) as $target) {
    echo "\n===== target: ".$target."\n";
    $offset = 0;
    $hits = 0;
    while (($pos = strpos($xml, $target, $offset)) !== false) {
        $hits++;
        echo "-- hit {$hits} at {$pos}\n";
        echo str_replace('>', '>\n', substr($xml, max(0, $pos - 700), 1400))."\n";
        $offset = $pos + strlen($target);
    }
    echo "total hits: {$hits}\n";
}
