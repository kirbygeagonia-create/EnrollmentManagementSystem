<?php
// Prints the run-split shape of the §24 note paragraphs and headings, so a pass can tell whether any
// contiguous needle can address them at all. Runs are shown in document order with their lengths.
$root = dirname(__DIR__, 3);
$zip = new ZipArchive;
if ($zip->open($root.'/Documentation/EMS Complete Documentation.docx') !== true) {
    fwrite(STDERR, "REFUSED: the docx did not open.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$targets = ['students — 23 columns', 'created_at, updated_at.', 'created_at, curriculumId, returnReason, updated_at;', 'Change since the previous edition'];
foreach ($targets as $t) {
    echo '### '.substr($t, 0, 60).' — contiguous count '.substr_count($xml, $t)."\n";
}

// Show the runs of the first paragraph whose flattened text starts with the note phrase.
$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};
preg_match_all('/<w:p>.*?<\/w:p>/s', $xml, $pm, PREG_OFFSET_CAPTURE);
$shown = 0;
foreach ($pm[0] as $p) {
    $t = $flatten($p[0]);
    if (! str_starts_with($t, 'Change since the previous edition') && ! preg_match('/^[a-z0-9_]+ — \d+ columns$/u', $t)) {
        continue;
    }
    if ($shown++ >= 4) {
        break;
    }
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $p[0], $tm);
    $parts = array_map(static fn ($s) => '['.strlen($s).'] '.substr($s, 0, 60), $tm[1]);
    echo 'paragraph at '.$p[1].': '.count($tm[1])." run(s)\n  ".implode("\n  ", $parts)."\n";
}
