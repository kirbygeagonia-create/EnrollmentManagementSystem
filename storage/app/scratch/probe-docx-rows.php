<?php

// Print the enclosing <w:tr> for each hit of a target: both the run-by-run text and
// the raw XML, so a replacement can be written against what the file really holds.

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$mode = $argv[1] ?? 'runs';
$targets = array_filter(explode('|', $argv[2] ?? ''));

foreach ($targets as $target) {
    $offset = 0;
    $hit = 0;
    while (($pos = strpos($xml, $target, $offset)) !== false) {
        $hit++;
        $start = strrpos(substr($xml, 0, $pos), '<w:tr');
        $end = strpos($xml, '</w:tr>', $pos);
        echo "\n##### {$target} — hit {$hit}, tr at {$start}..{$end}\n";
        if ($start === false || $end === false) {
            echo "(not inside a table row)\n";
            $offset = $pos + strlen($target);
            continue;
        }
        $tr = substr($xml, $start, $end - $start + 7);
        if ($mode === 'raw') {
            echo str_replace('>', '>\n', $tr)."\n";
        } else {
            // One line per cell, runs joined with a middot.
            $cells = preg_split('/<\/w:tc>/', $tr);
            foreach ($cells as $i => $cell) {
                preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $cell, $m);
                if ($m[0] === []) {
                    continue;
                }
                echo '  cell '.$i.': ['.implode(' · ', $m[1])."]\n";
            }
        }
        $offset = $pos + strlen($target);
    }
    echo "\n{$target}: {$hit} hit(s)\n";
}
