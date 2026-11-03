<?php
/**
 * Print the row/cell grid of the form around one heading, so "did the answer land in the box the guide means" is
 * answered by the layout instead of by a ballot count.
 *
 * Usage: php storage/app/scratch/lane229-read-form-grid.php --file=<docx> --match="<text of the heading paragraph>"
 *        [--rows=N]
 * A `--match` that finds no paragraph refuses by name, and one that finds more than one prints every hit's offset so
 * the reader picks a narrower phrase rather than guessing which block was meant.
 */
$root = dirname(__DIR__, 3);
chdir($root);

$file = null;
$match = null;
$rowLimit = 6;
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if (preg_match('#^--file=(.*)$#s', $arg, $m)) {
        $file = $m[1];
    } elseif (preg_match('#^--match=(.*)$#s', $arg, $m)) {
        $match = $m[1];
    } elseif (preg_match('#^--rows=(\d+)$#', $arg, $m)) {
        $rowLimit = max(1, (int) $m[1]);
    } else {
        fwrite(STDERR, "REFUSED: this tool does not read the flag {$arg}\n");
        exit(1);
    }
}
foreach ([['--file=', $file], ['--match=', $match]] as [$flag, $value]) {
    if ($value === null) {
        fwrite(STDERR, "REFUSED: {$flag} is required.\n");
        exit(1);
    }
}
if (! is_file($file)) {
    fwrite(STDERR, "REFUSED: {$file} is not on disk.\n");
    exit(1);
}

$z = new ZipArchive;
if ($z->open($file) !== true) {
    fwrite(STDERR, "REFUSED: {$file} did not open as an archive.\n");
    exit(1);
}
$xml = (string) $z->getFromName('word/document.xml');
$z->close();
if ($xml === '') {
    fwrite(STDERR, "REFUSED: word/document.xml read empty.\n");
    exit(1);
}

$flatten = static function (string $chunk): string {
    preg_match_all('#<w:t(?: [^>]*)?>(.*?)</w:t>#s', $chunk, $m);

    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1)) ?? '');
};

// Table spans, as [start, end].
preg_match_all('#<w:tbl>.*?</w:tbl>#s', $xml, $tm, PREG_OFFSET_CAPTURE);
$tables = array_map(static fn (array $t): array => ['start' => $t[1], 'end' => $t[1] + strlen($t[0]), 'raw' => $t[0]], $tm[0]);

$hits = [];
preg_match_all('#<w:p[ >].*?</w:p>#s', $xml, $pm, PREG_OFFSET_CAPTURE);
foreach ($pm[0] as $o) {
    $text = $flatten($o[0]);
    if ($text !== '' && mb_stripos($text, $match) !== false) {
        $start = $o[1];
        $kind = 'inside a table';
        foreach ($tables as $t) {
            if ($start >= $t['start'] && $start < $t['end']) {
                $kind = 'inside a table at '.$t['start'];
                break;
            }
        }
        $hits[] = ['start' => $start, 'end' => $start + strlen($o[0]), 'text' => $text, 'kind' => $kind];
    }
}
if ($hits === []) {
    fwrite(STDERR, "REFUSED: no paragraph in ".basename($file)." carries the text `{$match}`.\n");
    exit(1);
}
echo basename($file)." — ".count($hits)." paragraph(s) match `{$match}`\n";

foreach ($hits as $i => $h) {
    echo "\n=== hit ".($i + 1)." at offset {$h['start']} ({$h['kind']}) ===\n";
    echo '  heading: '.mb_strimwidth($h['text'], 0, 160, '…')."\n";

    // The table that contains the heading, plus the tables that follow it, are the boxes a reader would call the
    // problem's own answer area. Print each as rows of cells.
    $around = [];
    foreach ($tables as $t) {
        if ($t['start'] >= $h['end'] || ($t['start'] <= $h['start'] && $t['end'] >= $h['end'])) {
            $around[] = $t;
        }
        if (count($around) >= 3) {
            break;
        }
    }
    if ($around === []) {
        echo "  NO TABLE at or after this heading — the form's box is elsewhere than expected\n";
        continue;
    }
    foreach ($around as $k => $t) {
        $rel = $t['start'] < $h['start'] ? 'CONTAINS the heading' : 'follows (+'.'0)';
        echo '  table '.($k + 1)." at {$t['start']} — {$rel}\n";
        preg_match_all('#<w:tr[ >].*?</w:tr>#s', $t['raw'], $rm, PREG_OFFSET_CAPTURE);
        foreach (array_slice($rm[0], 0, $rowLimit) as $ri => $r) {
            preg_match_all('#<w:tc>.*?</w:tc>#s', $r[0], $cm);
            $cells = array_map(static fn ($c): string => $flatten((string) $c), $cm[0]);
            echo '    row '.($ri + 1).' ['.count($cells)." cell(s)]  "
                .implode('  ||  ', array_map(static fn ($c): string => '"'.mb_strimwidth($c, 0, 96, '…').'"', $cells))."\n";
        }
        if (count($rm[0]) > $rowLimit) {
            echo '    … '.(count($rm[0]) - $rowLimit)." more row(s) not printed\n";
        }
    }
}
