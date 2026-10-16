<?php
// Step-7 feasibility probe: for each candidate needle, report how many times it occurs
// in word/document.xml and whether each occurrence lies inside a single <w:t> run
// (text-only edit possible) or spans runs (whole-cell/paragraph rewrite needed).

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open docx\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$needles = array_slice($argv, 1);

// Pre-compute the span of every <w:t>..</w:t> text node.
$spans = [];
preg_match_all('/<w:t(?: [^>]*)?>/s', $xml, $opens, PREG_OFFSET_CAPTURE);
preg_match_all('/<\/w:t>/', $xml, $closes, PREG_OFFSET_CAPTURE);
foreach ($opens[0] as $i => $open) {
    if (! isset($closes[0][$i])) {
        break;
    }
    $spans[] = [$open[1] + strlen($open[0]), $closes[0][$i][1]];
}

foreach ($needles as $needle) {
    $hits = [];
    $offset = 0;
    while (($p = strpos($xml, $needle, $offset)) !== false) {
        $inside = false;
        foreach ($spans as [$s, $e]) {
            if ($p >= $s && $p + strlen($needle) <= $e) {
                $inside = true;
                break;
            }
        }
        $hits[] = [$p, $inside];
        $offset = $p + 1;
    }
    printf(
        "%-72s occurrences=%d  in-one-run=%s\n",
        substr($needle, 0, 70),
        count($hits),
        $hits === [] ? 'n/a' : implode(',', array_map(fn ($h) => $h[1] ? 'yes@'.$h[0] : 'NO@'.$h[0], $hits))
    );
}
