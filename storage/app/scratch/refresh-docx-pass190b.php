<?php

/**
 * Pass 190b — record the named pointer in §1.1's living row.
 *
 * Written without a new section pointer, for the reason pass 189b gave: the row sits in §1.1 and naming another
 * register would move the count the §-reference sweep prints, which is the drift this file keeps paying for.
 *
 *   php storage/app/scratch/refresh-docx-pass190b.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass190b.php --apply  # write
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

$from = 'and is a gate rather than only an instrument.';
$to = $from.' Pass 190 then took one of the pointers that list reports and made it checkable: the settlement '
    .'row said the recomputed balance equals zero and quoted a number that named no file, so nothing had ever '
    .'read it. The file is now named and the number moved onto the arithmetic itself — the grant insert and the '
    .'balance update inside the same method — and the citation agrees with the scholarship row, which had been '
    .'citing that span correctly all along.';

$n = substr_count($xml, $from);
$say($n === 1, 'the living revision row was found exactly once (count '.$n.')');
foreach (['<', '>', '&'] as $ch) {
    $say(! str_contains($to, $ch), 'the replacement carries no raw '.$ch);
}
$say(preg_match('/[A-Za-z][A-Za-z0-9_.\-]*\.(?:php|jsx|js|md|json):\d/', $to) === 0,
    'the record writes no citation-shaped example that no file could answer');
$staged = str_replace($from, $to, $xml);
$say(substr_count($staged, 'Pass 190') === 1, 'the record is stated once');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');
printf("\n  staged byte delta: %+d\n", strlen($staged) - strlen($xml));
printf("%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: §1.1’s living row would record pass 190.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass190b.docx');
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
