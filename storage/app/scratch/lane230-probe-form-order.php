<?php
/**
 * Print the form's blocks in DOCUMENT ORDER between two byte offsets, so the labels ("Your Analysis / Solution",
 * "If N/A or different from our workflow…") are seen beside the empty tables they belong to instead of being
 * reconstructed from a count.
 *
 * Usage: php storage/app/scratch/lane230-probe-form-order.php --file=<docx> [--from=N] [--to=N] [--width=N]
 */
$root = dirname(__DIR__, 3);
chdir($root);

$file = $root.'/Documentation/ADS_Final_Project_Guide_EMS_ANSWERED.docx';
$from = 0;
$to = PHP_INT_MAX;
$width = 130;
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if (preg_match('#^--file=(.*)$#s', $arg, $m)) {
        $file = $m[1];
    } elseif (preg_match('#^--from=(\d+)$#', $arg, $m)) {
        $from = (int) $m[1];
    } elseif (preg_match('#^--to=(\d+)$#', $arg, $m)) {
        $to = (int) $m[1];
    } elseif (preg_match('#^--width=(\d+)$#', $arg, $m)) {
        $width = (int) $m[1];
    } else {
        fwrite(STDERR, "REFUSED: this tool does not read the flag {$arg}\n");
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

$flat = static function (string $chunk): string {
    preg_match_all('#<w:t(?: [^>]*)?>(.*?)</w:t>#s', $chunk, $m);

    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1)) ?? '');
};

preg_match_all('#<w:tbl>.*?</w:tbl>#s', $xml, $tm, PREG_OFFSET_CAPTURE);
$tables = [];
foreach ($tm[0] as $t) {
    $tables[] = ['start' => $t[1], 'end' => $t[1] + strlen($t[0]), 'raw' => $t[0]];
}
$inTable = static function (int $at) use ($tables): bool {
    foreach ($tables as $t) {
        if ($at >= $t['start'] && $at < $t['end']) {
            return true;
        }
    }

    return false;
};

$blocks = [];
foreach ($tables as $t) {
    if ($t['start'] >= $from && $t['start'] <= $to) {
        $rows = preg_match_all('#<w:tr[ >]#', $t['raw'], $rr);
        $cells = preg_match_all('#<w:tc>#', $t['raw'], $cc);
        $emptyCells = 0;
        preg_match_all('#<w:tc>.*?</w:tc>#s', $t['raw'], $cm);
        foreach ($cm[0] as $c) {
            if ($flat((string) $c) === '') {
                $emptyCells++;
            }
        }
        $blocks[] = ['at' => $t['start'], 'tag' => "TBL rows={$rows} cells={$cells} empty={$emptyCells}", 'text' => $flat($t['raw'])];
    }
}
preg_match_all('#<w:p[ >].*?</w:p>#s', $xml, $pm, PREG_OFFSET_CAPTURE);
foreach ($pm[0] as $o) {
    $at = $o[1];
    if ($at < $from || $at > $to || $inTable($at)) {
        continue;
    }
    $text = $flat($o[0]);
    if ($text !== '') {
        $blocks[] = ['at' => $at, 'tag' => 'P', 'text' => $text];
    }
}
usort($blocks, static fn (array $a, array $b): int => $a['at'] <=> $b['at']);

echo basename($file)." — ".count($blocks)." top-level block(s) in [{$from}, {$to}]\n";
foreach ($blocks as $b) {
    echo str_pad((string) $b['at'], 8).' '.str_pad($b['tag'], 28).' '.mb_strimwidth($b['text'], 0, $width, '…')."\n";
}
