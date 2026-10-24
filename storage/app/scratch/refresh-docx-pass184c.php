<?php

/**
 * Pass 184c — §28.3's lane 49 sentence still counts 19 blind guards; the instrument answers 20.
 *
 * P-19's build replaced `{termEnrollment?.course?.courseName || '—'}` with a named local, which removed one of
 * the `|| '—'` shapes lane 49 tallies — so its own counts moved with the batch, and the sentence stating them
 * went stale without any checker in the document touching it.
 *
 * The number is read out of lane59-readings.json rather than typed in, and the key matters: lane 49 answers
 * dashGuards / unread / nonFigures / blind, and **the phrase "guards whose dash sits on a line this file
 * cannot join" is the tool's `unread` count, not its `blind` count** — which is what lane59-instrument-counts.php:181-183
 * encodes in its label table. This pass's first run took `blind` (16) and lane 59 refused it as STALE, which is
 * the instrument catching a figure that was read from the right tool and the wrong column.
 *
 *   php storage/app/scratch/refresh-docx-pass184c.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass184c.php --apply  # write
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$readings = json_decode((string) file_get_contents($root.'/storage/app/scratch/lane59-readings.json'), true);
$l49 = $readings['lane49-zero-shown-as-absence'] ?? null;
if (! is_array($l49) || ! isset($l49['unread'], $l49['nonFigures'])) {
    exit("REFUSED: lane59-readings.json holds no lane 49 unread/nonFigures reading — run lane59-collect-readings.php first.\n");
}
[$unread, $nonFigures] = [(int) $l49['unread'], (int) $l49['nonFigures']];

$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

/** The sentence as it stands on disk, whichever count an earlier run of this pass wrote into it. */
$from = null;
foreach ([19, 16] as $n) {
    $c = 'blind spots: '.$n.' guards whose dash sits on a line this file cannot join, 340 lines where the tested '
        .'operand is a name, code, date or error message.';
    if (substr_count($xml, $c) === 1) {
        $from = $c;
    }
}
if ($from === null) {
    exit("REFUSED: the lane 49 sentence matches neither of the two forms this pass has written — read it before writing it.\n");
}
$to = 'blind spots: '.$unread.' guards whose dash sits on a line this file cannot join, '.$nonFigures
    .' lines where the tested operand is a name, code, date or error message.';

echo "lane 49 answers unread={$unread} nonFigures={$nonFigures} from the readings taken this batch\n";
echo "the sentence on disk currently reads: ".substr($from, 0, 46)."…\n\n";
foreach (['<', '>'] as $c) {
    $say(! str_contains($to, $c), 'the replacement carries no '.$c);
}
$say((bool) preg_match('/blind spots: (\d+) guards/', $to), 'the replacement keeps the phrase lane 59 binds its label to');
$c = substr_count($xml, $from);
$say($c === 1, 'needle occurs '.$c.' time(s), expected 1');
if ($fails > 0) {
    printf("\n%d guard(s) failed — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

$staged = str_replace($from, $to, $xml);
$say(substr_count($staged, $to) === 1, 'the replacement landed once');
$say(! preg_match('/blind spots: (?:19|16) guards/', $staged), 'neither of the two superseded counts survives');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');
if ($fails > 0) {
    printf("\n%d guard(s) failed on the staged bytes — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: §28.3's lane 49 sentence would read {$unread} guards and {$nonFigures} lines.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass184c.docx');
$z = new ZipArchive();
if ($z->open($src) !== true) {
    exit("REFUSED: the docx cannot be reopened for the write.\n");
}
if ($z->getFromName('word/document.xml') !== $xml) {
    exit("REFUSED: document.xml changed under this pass since it was read — nothing written.\n");
}
$z->addFromString('word/document.xml', $staged);
$z->close();
echo "WROTE {$src}\n";
