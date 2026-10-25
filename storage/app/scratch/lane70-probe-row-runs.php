<?php

/**
 * Lane 70, probe: list the `<w:t>` runs of the table row whose flattened text contains a phrase.
 *
 * §13's Department Evaluation step row cites `:273` for "evaluatedBy written by profile capture", and both the whole
 * phrase and `>273<` are absent from document.xml as contiguous bytes — the citation is split across several runs.
 * Only the engine's `cell` kind can reach it, and a cell rewrite retypes the whole cell, so the row's runs must be
 * read exactly as the file holds them (never from docx-flat.txt, which joins runs with a space).
 *
 * Usage: php lane70-probe-row-runs.php '<phrase found in the flattened row>'
 */
$root = dirname(__DIR__, 3);
$needle = $argv[1] ?? '';
if ($needle === '' || str_starts_with($needle, '-')) {
    echo "usage: php ".basename(__FILE__)." '<phrase in the flattened row>'\n";
    exit(1);
}
$zip = new ZipArchive();
if ($zip->open($root.'/Documentation/EMS Complete Documentation.docx') !== true) {
    echo "REFUSED: cannot open the document\n";
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$flat = static function (string $block): string {
    preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $block, $m);

    return html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1);
};

$rows = preg_split('/(?=<w:tr[ >])/', $xml);
$found = 0;
foreach ($rows as $row) {
    if (! str_starts_with($row, '<w:tr')) {
        continue;
    }
    $text = $flat($row);
    if (! str_contains($text, $needle)) {
        continue;
    }
    $found++;
    echo "ROW $found flattened=[{$text}]\n";
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cells);
    foreach ($cells[0] as $ci => $cellXml) {
        preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $cellXml, $tm);
        printf("  CELL %d  runs=%d  flat=[%s]\n", $ci, count($tm[1]), $flat($cellXml));
        foreach ($tm[1] as $ri => $t) {
            printf("    run %d: [%s]\n", $ri, html_entity_decode($t, ENT_QUOTES | ENT_XML1));
        }
    }
}
echo 'rows matched: '.$found."\n";
