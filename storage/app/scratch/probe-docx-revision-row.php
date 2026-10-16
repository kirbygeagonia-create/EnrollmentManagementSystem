<?php

/**
 * Probe the §1.1 revision table's row XML so pass 128 can CLONE a row rather than invent one.
 *
 * A new row has to carry the same cell properties (`<w:tcPr>`, widths, borders) and the same run properties as
 * the rows beside it, or the table renders one column out of place — and nothing in the verification set can
 * see a row inserted inside a cell, because a `<w:tr>` opened and closed within `<w:tc>` leaves every container
 * count balanced. So this prints exactly what a clone must preserve: the row's own attributes, how many cells
 * it has, how many paragraphs and runs per cell, and where the row ends.
 *
 * Read-only.
 */

$root = dirname(__DIR__, 3);
$zip = new ZipArchive();
$zip->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$anchor = 'The five-lane audit the owner asked for after the catalogue was closed';
$at = strpos($xml, $anchor);
if ($at === false) {
    fwrite(STDERR, "anchor not found — the 2026-10-10 revision row moved\n");
    exit(1);
}

$start = strrpos(substr($xml, 0, $at), '<w:tr');
$startOpen = strpos($xml, '>', $start) + 1;
$end = strpos($xml, '</w:tr>', $at) + 7;

printf("row starts @%d ends @%d  (%d bytes)\n", $start, $end, $end - $start);
printf("row tag: %s\n", substr($xml, $start, $startOpen - $start));

$row = substr($xml, $start, $end - $start);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
printf("cells: %d\n", count($cm[0]));

foreach ($cm[0] as $i => $cell) {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $tm);
    $text = html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1);
    printf("  cell %d: %d <w:p>, %d runs, %d bytes  text[%.60s]\n",
        $i, substr_count($cell, '<w:p>') + substr_count($cell, '<w:p '), count($tm[1]), strlen($cell), $text);
    if ($i === 0) {
        printf("    cell 0 props: %s\n", preg_replace('/\s+/', ' ', substr($cell, 0, (int) strpos($cell, '<w:p'))));
    }
}

printf("\nrow is preceded by: %s\n", substr($xml, max(0, $start - 60), 60));
printf("row is followed by: %s\n", substr($xml, $end, 60));
