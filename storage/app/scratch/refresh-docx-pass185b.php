<?php

/**
 * Pass 185b — lane 59 refused: §28.3's lane 49 sentence still counts 340 non-figure lines, and the
 * instrument answers 339 today.
 *
 * The number moved because formatYearLevel stopped being a ternary that paints an em dash beside a value
 * and became an early return, which is one of the shapes lane 49 counts as "the operand is a name, code,
 * date or error string". A guard that reads a dash-producing expression changed its own count — the same
 * class as pass 184c, caught by the checker rather than by memory.
 *
 * The figure is read out of lane59-readings.json, never typed.
 *
 *   php storage/app/scratch/refresh-docx-pass185b.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass185b.php --apply  # write
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$readings = json_decode((string) file_get_contents($root.'/storage/app/scratch/lane59-readings.json'), true);
$l49 = $readings['lane49-zero-shown-as-absence'] ?? null;
if (! is_array($l49) || ! isset($l49['nonFigures'])) {
    exit("REFUSED: lane59-readings.json holds no lane 49 nonFigures reading — run lane59-collect-readings.php first.\n");
}
$nonFigures = (int) $l49['nonFigures'];

$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

// Accept whichever figure the previous pass wrote, so a re-run is not defeated by its own earlier output.
$from = null;
foreach ([340, 339, 341] as $n) {
    $c = 'lines where the tested operand is a name, code, date or error message';
    $needle = $n.' '.$c;
    if (substr_count($xml, $needle) === 1) {
        $from = $needle;
    }
}
if ($from === null) {
    exit("REFUSED: the lane 49 line-count sentence matches none of the forms this pass has written — read it before writing it.\n");
}
$to = $nonFigures.' lines where the tested operand is a name, code, date or error message';
echo "lane 49 answers nonFigures={$nonFigures}; the sentence on disk reads ".substr($from, 0, 42)."…\n";

$say(substr_count($xml, $from) === 1, 'needle occurs once');
$say(! preg_match('/[<>]/', $to), 'the replacement carries no angle bracket');
$say((bool) preg_match('/^(\d+) lines where the tested operand/u', $to), 'the phrase lane 59 binds its label to survives');

$staged = str_replace($from, $to, $xml);
$say(substr_count($staged, $to) === 1, 'the replacement landed once');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');

printf("\n%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: §28.3's lane 49 sentence would read {$nonFigures} lines.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass185b.docx');
$z = new ZipArchive;
if ($z->open($src) !== true) {
    exit("REFUSED: the docx cannot be reopened for the write.\n");
}
if ($z->getFromName('word/document.xml') !== $xml) {
    exit("REFUSED: document.xml changed under this pass since it was read — nothing written.\n");
}
$z->addFromString('word/document.xml', $staged);
$z->close();
echo "WROTE {$src}\n";
