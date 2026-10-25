<?php

/**
 * Lane 70, probe: print the table cell that holds the run `273`, exactly as the edit engine flattens it.
 *
 * Pass 235's needle `:273` and pass 236's anchor `evaluatedBy written by profile capture` are both count 0 in
 * document.xml, so §13's Department Evaluation step row can only be repaired through the engine's `cell` kind.
 * A cell rewrite retypes the whole cell, so its true flattened text has to be READ from document.xml (never from
 * docx-flat.txt, which joins runs with a space) before any replacement is written.
 */
$root = dirname(__DIR__, 3);
$zip = new ZipArchive();
if ($zip->open($root.'/Documentation/EMS Complete Documentation.docx') !== true) {
    echo "REFUSED: cannot open the document\n";
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$want = $argv[1] ?? '273';
$pos = 0;
$hits = 0;
while (($p = strpos($xml, '>'.$want.'<', $pos)) !== false) {
    $pos = $p + 1;
    $hits++;
    // The cell that owns this run.
    $tcOpen = strrpos(substr($xml, 0, $p), '<w:tc>');
    $tcClose = strpos($xml, '</w:tc>', $p);
    if ($tcOpen === false || $tcClose === false) {
        printf("HIT %d: not inside a cell\n", $hits);

        continue;
    }
    $cellXml = substr($xml, $tcOpen, $tcClose - $tcOpen);
    preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $cellXml, $m);
    $flat = html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1);
    printf("HIT %d  runs=%d  cell=[%s]\n", $hits, count($m[1]), $flat);
    if ($hits > 6) {
        break;
    }
}
echo 'occurrences of >'.$want.'< found: '.$hits."\n";
