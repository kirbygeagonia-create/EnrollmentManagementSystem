<?php

/**
 * Pass 188b — record the recount.
 *
 * §28.8's intro still describes only what its table is counted from, not when, and the table it introduces
 * had been asserting 46 owed slots while the column named a decider in 8 rows, and now in 2. §1.1's living
 * revision row also still stops at the 2026-10-24 rulings and says nothing about passes 187/188.
 *
 *   php storage/app/scratch/refresh-docx-pass188b.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass188b.php --apply  # write
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
        'so the split between institutional answers and engineering work is visible at a glance.',
        'so the split between institutional answers and engineering work is visible at a glance. '
        .'Recounted 2026-10-24 by storage/app/scratch/lane61-who-owes.php, which reads the column itself rather '
        .'than this table: two rows name someone who still owes a decision — C-5, left with the departments by '
        .'the ruling of 2026-10-23, and G-13, which is an install-time act for a server that does not exist yet. '
        .'Every other row in §28.2 and §28.3 answers that column with its own closure (“Closed in code”, '
        .'“Answered 2026-10-24 — no decision outstanding”), so a count read from the column must fall as items '
        .'close; this table had been standing at 46 slots since pass 173.',
    ],
    [
        'Four rows remain, items 1, 2, 4 and 5, and all four ask the Registrar’s window rather than the build.',
        'Four rows remain, items 1, 2, 4 and 5, and all four ask the Registrar’s window rather than the build. '
        .'Pass 187 then cleared six §28.2 rows whose “Who must decide” column still named an actor for a question '
        .'that had just been answered, and pass 188 rebuilt §28.8’s table from 46 owed slots to the two the '
        .'column actually names.',
    ],
];

$staged = $xml;
foreach ($edits as $i => [$from, $to]) {
    $n = substr_count($xml, $from);
    $say($n === 1, 'edit '.($i + 1).' found exactly once (count '.$n.')');
    foreach (['<', '>', '&'] as $ch) {
        $say(! str_contains($to, $ch), 'edit '.($i + 1).' carries no '.$ch);
    }
    $staged = str_replace($from, $to, $staged);
}

$say(substr_count($staged, 'Recounted 2026-10-24') === 1, 'the recount carries its date and its instrument');
$say(substr_count($staged, 'pass 188 rebuilt') === 1, '§1.1 records both passes');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');
printf("\n  staged byte delta: %+d\n", strlen($staged) - strlen($xml));
printf("%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: §28.8's intro and §1.1's living row would record the recount.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass188b.docx');
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
