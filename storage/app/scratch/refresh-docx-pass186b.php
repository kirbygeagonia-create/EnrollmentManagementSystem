<?php

/**
 * Pass 186b — two count sentences that the 2026-10-24 rulings left in the present tense.
 *
 * §28.5's intro still says "The sixteen that DO wait on an office … were put on one sheet", and §1.1's
 * living revision row still ends by describing item 16 as the open question. Twelve of the sheet's
 * sixteen rows are answered now and four remain, so both sentences state the register wrongly —
 * lane 61's fourth shape: not a stale figure, not a stale status word, but a count that stopped
 * growing when rows were closed rather than opened.
 *
 *   php storage/app/scratch/refresh-docx-pass186b.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass186b.php --apply  # write
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
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    [
        'The sixteen that do wait on an office or on whoever provisions the server were put on one sheet for a single meeting on 2026-10-23: Documentation/School-Decision-Sheet-2026-10-23.md.',
        'The sixteen that waited on an office or on whoever provisions the server on the day this table was reconciled were put on one sheet for a single meeting: Documentation/School-Decision-Sheet-2026-10-23.md. Twelve of those sixteen are answered as of 2026-10-24 — five came back the day the sheet issued, item 9 was ruled provisional the same day, and the owner answered items 3, 6, 7, 8, 10 and 11 on 2026-10-24 — and four remain: items 1, 2, 4 and 5, each of which asks what the Registrar’s window accepts rather than what the software does. None of the twelve answers needed a release.',
    ],
    [
        'and item 16 asks which of the two papers the system is to produce.',
        'and item 16 asks which of the two papers the system is to produce. Twelve of the sheet’s sixteen rows are now answered: five came back on 2026-10-23, item 9 was ruled provisional the same day, and on 2026-10-24 the owner answered items 3, 6, 7, 8, 10 and 11 — the certificate keeps the wording it prints, no program waives the General examination for a transferee, retention is indefinite as measured, a program change always makes the student Irregular, a shifter sits no departmental examination, and a returning or shifting student is re-determined every term — closing also the queue half of item 15, which reading the listeners narrowed to the in-app bell alone. Four rows remain, items 1, 2, 4 and 5, and all four ask the Registrar’s window rather than the build.',
    ],
];

$staged = $xml;
foreach ($edits as $i => [$from, $to]) {
    $n = substr_count($xml, $from);
    $say($n === 1, 'edit '.($i + 1).' found exactly once (count '.$n.')');
    foreach (['<', '>', '&'] as $ch) {
        $say(! str_contains($to, $ch), 'edit '.($i + 1).' replacement carries no '.$ch);
    }
    $staged = str_replace($from, $to, $staged);
}

$say(substr_count($staged, 'The sixteen that do wait') === 0, 'no present-tense count of sixteen waiting rows survives');
$say(substr_count($staged, 'Twelve of those sixteen are answered') === 1, '§28.5 states today’s reading');
$say(substr_count($staged, 'Twelve of the sheet’s sixteen rows are now answered') === 1, '§1.1’s living row records the batch');
$say(substr_count($staged, 'Nothing in this table asks for code') === 1, 'the sentence that was true still stands');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');
printf("\n  staged byte delta: %+d\n", strlen($staged) - strlen($xml));

printf("%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: §28.5's intro and §1.1's living row would carry the 2026-10-24 tally.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass186b.docx');
$z = new ZipArchive;
if ($z->open($src) !== true) {
    exit("REFUSED: the docx cannot be reopened for the write — nothing written.\n");
}
if ($z->getFromName('word/document.xml') !== $xml) {
    exit("REFUSED: document.xml changed under this pass since it was read — nothing written.\n");
}
$z->addFromString('word/document.xml', $staged);
$z->close();
echo "WROTE {$src}\n";
