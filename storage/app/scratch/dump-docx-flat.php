<?php
// Flat text of every paragraph and table row in the authority docx, in document
// order, so the step-7 survey can read what is stale without re-probing per string.

$z = new ZipArchive();
if ($z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx') !== true) {
    fwrite(STDERR, "cannot open docx\n");
    exit(1);
}
$xml = $z->getFromName('word/document.xml');
$z->close();

// Walk the body sequentially: a row is taken wholesale (its cells flattened), so the
// paragraphs inside its cells are not emitted twice.
$out = [];
$pos = 0;
$index = 0;
while ($pos < strlen($xml)) {
    $p = strpos($xml, '<w:p>', $pos);
    $pAttr = strpos($xml, '<w:p ', $pos);
    $t = strpos($xml, '<w:tr>', $pos);
    $starts = array_filter([$p, $pAttr, $t], fn ($v) => $v !== false);
    if ($starts === []) {
        break;
    }
    $next = min($starts);
    $isRow = ($t !== false && $next === $t);
    if ($isRow) {
        $end = strpos($xml, '</w:tr>', $next);
        $chunk = substr($xml, $next, $end - $next);
        $cells = [];
        foreach (explode('<w:tc>', $chunk) as $cellXml) {
            preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cellXml, $cm);
            $text = preg_replace('/\s+/', ' ', trim(implode(' ', $cm[1])));
            if ($text !== '') {
                $cells[] = $text;
            }
        }
        if ($cells !== []) {
            $out[] = 'ROW '.(++$index).': '.implode(' | ', $cells);
        }
        $pos = $end + 7;
        continue;
    }
    $end = strpos($xml, '</w:p>', $next);
    $chunk = substr($xml, $next, $end - $next);
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $chunk, $cm);
    $text = preg_replace('/\s+/', ' ', trim(implode('', $cm[1])));
    if ($text !== '') {
        $out[] = 'P '.(++$index).': '.$text;
    }
    $pos = $end + 6;
}

file_put_contents(dirname(__DIR__, 3).'/storage/app/scratch/docx-flat.txt', implode("\n", $out)."\n");
echo 'elements: '.count($out)."\n";
