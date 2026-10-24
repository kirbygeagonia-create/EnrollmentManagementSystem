<?php

/**
 * Pass 183c — §28.7's row 2 still sized the decision sheet at fifteen items the day it grew to sixteen.
 *
 * Pass 183 moved §28.5's own intro sentence (fifteen → sixteen) and §1.1's new living row says the sheet
 * carries sixteen items, which left one sentence in the document stating fifteen beside it. §28.7 is the
 * order the build took, so the honest repair is to keep the record of what was tabled and name the day the
 * sixteenth arrived — the same rule lane 63 wrote for a dated figure: do not re-age a record, date it.
 *
 *   php storage/app/scratch/refresh-docx-pass183c.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass183c.php --apply  # write
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

$from = 'Tabled 2026-10-23 as Documentation/School-Decision-Sheet-2026-10-23.md — fifteen items, each naming '
    .'the register row it closes and carrying a line to initial.';
$to = 'Tabled 2026-10-23 as Documentation/School-Decision-Sheet-2026-10-23.md — fifteen items when it was '
    .'issued and a sixteenth the same day, when reading the school’s own paper opened §25.4 P-19; each names '
    .'the register row it closes and carries a line to initial.';

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$new = $xml;

foreach (['<', '>'] as $c) {
    $say(! str_contains($to, $c), 'the replacement carries no '.$c);
}
$say(! str_contains($to, '&amp;'), 'the replacement carries no entities');
$c = substr_count($new, $from);
$say($c === 1, 'needle occurs '.$c.' time(s), expected 1');
$say(substr_count($new, 'fifteen items') === 1, 'no other sentence in the document sizes the sheet at fifteen');
$say(substr_count($new, 'sixteen that do wait on an office') === 1,
    '§28.5’s own intro already counts sixteen — the two must not disagree');
if ($fails > 0) {
    printf("\n%d guard(s) failed — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
$new = str_replace($from, $to, $new);
$say(substr_count($new, $to) === 1, 'the replacement landed once');
$say(substr_count($new, 'fifteen items') === 1, 'the remaining “fifteen items” is the record this sentence keeps');
$say(@(new DOMDocument)->loadXML($new) !== false, 'the staged document.xml parses');
if ($fails > 0) {
    printf("\n%d guard(s) failed after staging — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: §28.7 row 2 would record fifteen items at issue and the sixteenth the same day.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass183c.docx');
$z = new ZipArchive();
if ($z->open($src) !== true) {
    exit("REFUSED: the docx cannot be reopened for the write.\n");
}
if ($z->getFromName('word/document.xml') !== $xml) {
    exit("REFUSED: document.xml changed under this pass since it was read — nothing written.\n");
}
$z->addFromString('word/document.xml', $new);
$z->close();
echo "WROTE {$src}\n";
