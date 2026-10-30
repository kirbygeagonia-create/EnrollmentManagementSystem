<?php

/**
 * Read-only: how many <w:tc> cells does a table row REALLY hold, in document.xml?
 *
 * `lane69-open-inventory.php` reports §24's `resultStatus` row as UNRESOLVED — "five cells against a six-cell
 * header" — but it decides that by splitting the FLAT DUMP, which `dump-docx-flat.php` joins with ' | ' while the
 * inventory splits on a bare '|'. A cell that writes an enum list, `enum(provisional|official)`, therefore splits
 * itself. So the question is not "is the document row short" but "what does the archive hold", and only reading
 * the row's own <w:tc> elements answers it.
 *
 * Usage: php storage/app/scratch/lane89-probe-row-cells.php [needle]
 */

$root = dirname(__DIR__, 3);
$needle = $argv[1] ?? 'resultStatus';

$zip = new ZipArchive;
if (! $zip->open($root.'/Documentation/EMS Complete Documentation.docx')) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};

$rows = [];
$at = 0;
while (($start = strpos($xml, '<w:tr', $at)) !== false) {
    $end = strpos($xml, '</w:tr>', $start);
    if ($end === false) {
        break;
    }
    $end += 7;
    $row = substr($xml, $start, $end - $start);
    preg_match_all('/<w:tc[ >].*?<\/w:tc>/s', $row, $cm);
    $cells = array_map($flatten, $cm[0]);
    $rows[] = ['n' => count($cm[0]), 'cells' => $cells];
    $at = $end;
}

$hits = [];
foreach ($rows as $i => $r) {
    if (isset($r['cells'][0]) && str_starts_with($r['cells'][0], $needle)) {
        $hits[] = $i;
    }
}

if ($hits === []) {
    fwrite(STDERR, 'REFUSED: no row\'s first cell starts with ['.$needle.'] — the probe read '.count($rows)." rows and found nothing to say.\n");

    exit(1);
}

foreach ($hits as $i) {
    // The table's header is the first row of the enclosing run of rows; walk back to it by finding the nearest
    // row above whose own cell count matches the rows around it and whose text names the columns.
    printf("row #%d holds %d <w:tc> cell(s):\n", $i, $rows[$i]['n']);
    foreach ($rows[$i]['cells'] as $k => $c) {
        printf("   cell %d: %s\n", $k, mb_substr($c, 0, 70));
    }
    for ($j = $i - 1; $j >= max(0, $i - 6); $j--) {
        printf("   above #%d (%d cells): %s\n", $j, $rows[$j]['n'], mb_substr(implode(' | ', $rows[$j]['cells']), 0, 110));
    }
}

printf("\n%d row(s) matched [%s] out of %d rows read.\n", count($hits), $needle, count($rows));
