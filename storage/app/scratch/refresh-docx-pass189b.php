<?php

/**
 * Pass 189b — record the citation repairs in §1.1's living row.
 *
 * Deliberately written without a new section pointer: the row sits in §1.1 and naming the print-log register
 * again would move the count the §-reference sweep prints, which is the drift this repository keeps paying
 * for. The sentences name the tables by what they are.
 *
 *   php storage/app/scratch/refresh-docx-pass189b.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass189b.php --apply  # write
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$from = 'and pass 188 rebuilt §28.8’s table from 46 owed slots to the two the column actually names.';
$to = $from.' Pass 189 repaired five bare continuation pointers that the line sweep could not see, because '
    .'a checker requiring a filename before the colon reads only the first of the two pointers a sentence '
    .'writes that way, and rewrote one passage that still described an older build: the Evaluation queue has '
    .'shown a Proposed Standing column for a while, and the combined transferee-and-shifter figure is labelled '
    .'“Transferees / Shifters”, so what remains of that quirk is the combined count rather than a missing '
    .'field. The sweep now judges a bare number against the file named before it on the same line, prints the '
    .'ones it cannot bind, and is a gate rather than only an instrument.';

$n = substr_count($xml, $from);
$say($n === 1, 'the living revision row was found exactly once (count '.$n.')');
foreach (['<', '>', '&'] as $ch) {
    $say(! str_contains($to, $ch), 'the replacement carries no raw '.$ch);
}
$say(substr_count($xml, 'Pass 189') === 0, 'nothing in the document yet claims pass 189 happened');
// A sentence ABOUT citations still has to obey the citation rules: `File.php:380` written as an example is a
// pointer the sweep will try to resolve, and `File.php` is not a file in this repository.
$say(preg_match('/[A-Za-z][A-Za-z0-9_.\-]*\.(?:php|jsx|js|md|json):\d/', $to) === 0,
    'the record writes no citation-shaped example that no file could answer');
$staged = str_replace($from, $to, $xml);
$say(substr_count($staged, 'Pass 189') === 1, 'the record is stated once');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');
printf("\n  staged byte delta: %+d\n", strlen($staged) - strlen($xml));
printf("%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: §1.1’s living row would record pass 189.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass189b.docx');
$z = new ZipArchive;
if ($z->open($src) !== true) {
    echo "REFUSED: the docx cannot be reopened for the write — nothing written.\n";
    exit(1);
}
if ($z->getFromName('word/document.xml') !== $xml) {
    echo "REFUSED: document.xml changed under this pass since it was read — nothing written.\n";
    exit(1);
}
$z->addFromString('word/document.xml', $staged);
$z->close();
echo "WROTE {$src}\n";
