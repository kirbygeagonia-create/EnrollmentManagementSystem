<?php
/**
 * Read-only: print the §23.6.1 row that carries `uq_enrollments_active_seat` cell by cell, with each cell's run
 * count and byte span inside word/document.xml.
 *
 * This exists to answer one question before pass 384 chooses its technique: can that row be REWRITTEN in place into
 * the row for `uq_enrollments_request_key` (keeping the register's size and its 12/10 count sentence honest), or
 * must it be deleted and a sibling cloned? The answer is the cell count and whether every cell's text lives in one
 * <w:t> run — a value split across three runs cannot travel through a text needle.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    fwrite(STDERR, "REFUSED: could not open the docx as an archive (an error code is truthy).\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    fwrite(STDERR, "REFUSED: word/document.xml read back empty.\n");
    exit(1);
}

$opens = [];
preg_match_all('/<w:tr\b[^>]*>/', $xml, $rm, PREG_OFFSET_CAPTURE);
foreach ($rm[0] as $hit) {
    $opens[] = [$hit[1], $hit[1] + strlen($hit[0])];
}
$found = [];
foreach ($opens as [$open, $body]) {
    $end = strpos($xml, '</w:tr>', $body);
    if ($end !== false && str_contains(substr($xml, $open, $end - $open), 'uq_enrollments_active_seat')) {
        $found[] = [$open, $end + strlen('</w:tr>')];
    }
}
if (count($found) !== 1) {
    fwrite(STDERR, 'REFUSED: the seat-key row matched '.count($found)." time(s), expected exactly 1.\n");
    exit(1);
}
[$rs, $re] = $found[0];
$row = substr($xml, $rs, $re - $rs);
printf("row bytes %d..%d (%d), </w:tr> in document = %d\n\n", $rs, $re, $re - $rs, substr_count($xml, '</w:tr>'));

$cells = [];
preg_match_all('#<w:tc>.*?</w:tc>#s', $row, $cm, PREG_OFFSET_CAPTURE);
foreach ($cm[0] as $hit) {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $hit[0], $tm);
    $runs = $tm[1] ?? [];
    $text = html_entity_decode(implode('', $runs), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    printf("cell %d: bytes %d..%d, %d run(s): %s\n",
        count($cells) + 1,
        $rs + $hit[1],
        $rs + $hit[1] + strlen($hit[0]),
        count($runs),
        strlen($text) > 120 ? substr($text, 0, 120).' …' : $text);
    foreach ($runs as $i => $r) {
        printf("    run %d (%d bytes): %s\n", $i + 1, strlen($r), strlen($r) > 90 ? substr($r, 0, 90).' …' : $r);
    }
    $cells[] = [$rs + $hit[1], $text];
}
printf("\ncells in this row: %d\n", count($cells));

// The sibling rows in the same table, for the column vocabulary the replacement must match.
$tableStart = strrpos(substr($xml, 0, $rs), '<w:tbl>');
$tableEnd = strpos($xml, '</w:tbl>', $rs);
echo "\nsibling rows in the same table (first cell, cell count):\n";
preg_match_all('#<w:tr\b.*?</w:tr>#s', substr($xml, $tableStart, $tableEnd - $tableStart), $rows);
foreach ($rows[0] as $i => $r) {
    preg_match_all('#<w:tc>.*?</w:tc>#s', $r, $c2);
    $first = '';
    if ($c2[0] !== []) {
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $c2[0][0], $t2);
        $first = html_entity_decode(implode('', $t2[1] ?? []), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    printf("  row %2d  cells=%d  %s\n", $i + 1, count($c2[0]),
        strlen($first) > 70 ? substr($first, 0, 70).' …' : $first);
}
