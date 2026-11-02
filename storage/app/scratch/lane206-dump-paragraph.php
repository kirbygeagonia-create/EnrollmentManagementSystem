<?php

/**
 * Read-only: print one guide paragraph's raw XML so a writer can see the run split rather than guess it.
 *
 * The fill-in lines look like `Student 1: ______` once their runs are flattened, but the label and the
 * underscores may sit in the same `<w:t>` or in two of them, and a writer that assumes one shape silently
 * fails the other (lane203's §VII pass did exactly that and refused).
 *
 * Usage: php storage/app/scratch/lane206-dump-paragraph.php --contains=<text>
 */
$root = dirname(__DIR__, 3);
chdir($root);
$src = 'C:/Users/ADMIN/Downloads/ADS_Final_Project_Guide_EMS_updated.docx';
$needle = null;
foreach (array_slice($GLOBALS['argv'], 1) as $a) {
    if (preg_match('/^--contains=(.*)$/s', $a, $m)) {
        $needle = $m[1];
    } else {
        fwrite(STDERR, "REFUSED: this tool does not read the flag {$a}\n");
        exit(1);
    }
}
if ($needle === null || $needle === '') {
    fwrite(STDERR, "REFUSED: --contains= is empty; the paragraph is chosen by its own text, never by an index.\n");
    exit(1);
}
$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    fwrite(STDERR, "REFUSED: {$src} did not open.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
preg_match_all('/<w:p[ >].*?<\/w:p>/s', $xml, $pm, PREG_OFFSET_CAPTURE);
$hits = 0;
foreach ($pm[0] as $o) {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $o[0], $tm);
    $flat = html_entity_decode(implode('|', $tm[1]), ENT_QUOTES | ENT_XML1);
    if (! str_contains($flat, $needle)) {
        continue;
    }
    $hits++;
    echo "--- paragraph @{$o[1]} runs=".count($tm[1])." flat: {$flat}\n";
    foreach ($tm[1] as $i => $t) {
        printf("    run %d: %s\n", $i, var_export(html_entity_decode((string) $t, ENT_QUOTES | ENT_XML1), true));
    }
}
if ($hits === 0) {
    fwrite(STDERR, "REFUSED: no paragraph's flattened runs contain '{$needle}'.\n");
    exit(1);
}
printf("%d paragraph(s) contain it\n", $hits);
