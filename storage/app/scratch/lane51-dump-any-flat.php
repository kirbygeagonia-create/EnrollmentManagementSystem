<?php

// The same flattener as dump-docx-flat.php, pointed at any .docx — used to read the document as it stood in
// HEAD, so a citation move can be checked against the numbers that were correct before this batch moved code.
//
//   php storage/app/scratch/lane51-dump-any-flat.php <docx> <out.txt>

$in = $argv[1] ?? null;
$outPath = $argv[2] ?? null;

if (! is_string($in) || ! is_file($in) || ! is_string($outPath)) {
    fwrite(STDERR, "REFUSE: usage is lane51-dump-any-flat.php <docx> <out.txt>\n");

    exit(1);
}

$z = new ZipArchive();

if ($z->open($in) !== true) {
    fwrite(STDERR, "REFUSE: cannot open $in\n");

    exit(1);
}

$xml = $z->getFromName('word/document.xml');
$z->close();

if (! is_string($xml) || $xml === '') {
    fwrite(STDERR, "REFUSE: no word/document.xml in $in\n");

    exit(1);
}

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

file_put_contents($outPath, implode("\n", $out)."\n");
echo 'elements: '.count($out)." -> $outPath\n";
