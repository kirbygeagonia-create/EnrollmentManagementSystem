<?php
/**
 * Lane 107 inspector: how many cells does a §1.1 revision row really hold, and what is in each?
 *
 * Pass 326 refused its §1.1 anchor because it assumed `2026-10-30` stood alone in the first cell. The dump joins
 * cells with a pipe, and the ruling row prints its date and its title with no pipe between them — so either they
 * share a cell or the dumper collapsed something. This reads document.xml and prints the cells. Read-only.
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive::open answers an error CODE, which is truthy).\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};

$needles = ['A ruled reversal', 'The eighth table let a tab', 'Revision'];
$at = 0;
$found = [];
while (($start = strpos($xml, '<w:tr', $at)) !== false) {
    $end = strpos($xml, '</w:tr>', $start);
    if ($end === false) {
        break;
    }
    $end += 7;
    $row = substr($xml, $start, $end - $start);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
    $flat = $flatten($row);
    foreach ($needles as $n) {
        if (str_contains($flat, $n) && ! isset($found[$n])) {
            $found[$n] = [$cm, $flat];
        }
    }
    $at = $end;
}

foreach ($found as $n => [$cm, $flat]) {
    echo "=== row containing: {$n}  — ", count($cm[0]), ' cell(s)', PHP_EOL;
    foreach ($cm[0] as $i => $cell) {
        preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
        printf("  cell %d (%d paragraph(s)) : %s\n", $i, count($pm[0]), substr($flatten($cell), 0, 90));
    }
}
if (count($found) !== count($needles)) {
    fwrite(STDERR, 'NOTE: needles found = '.count($found).' of '.count($needles)."; the missing one is a claim about the document that did not hold.\n");
}
