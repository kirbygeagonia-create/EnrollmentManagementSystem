<?php

/**
 * Lane 70: probe candidate needles against word/document.xml.
 *
 * A flat dump joins `<w:t>` runs with nothing or a space depending on how the paragraph was built, so a phrase
 * that reads contiguously on screen may be three runs in the file. This prints the byte count of each candidate so
 * a pass can pick one that is addressable instead of discovering it after a refused write.
 *
 *   php storage/app/scratch/lane70-probe-needles.php "needle" "other needle" …
 */
$root = dirname(__DIR__, 3);
$zip = new ZipArchive();
if ($zip->open($root.'/Documentation/EMS Complete Documentation.docx') !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$cands = array_slice($argv, 1);
if ($cands === []) {
    echo "usage: php ".basename(__FILE__)." 'needle' ['needle' …]\n";
    exit(1);
}
foreach ($cands as $c) {
    printf("  count %d — %s\n", substr_count($xml, $c), $c);
}
