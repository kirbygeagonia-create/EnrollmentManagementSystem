<?php

/**
 * Pass 185e — §1.1's living revision row still tells the reader P-2 waits on a blank form.
 *
 * Found by reading the row, not by a checker. Its last clause says "so closing P-2 is a blade's layout once
 * the blank form arrives" — true on 2026-10-22, the day that row was written. The owner ruled on 2026-10-23
 * that **no blank will be supplied** and P-2 was closed the same day from the designs in the gitignored
 * `Images/` folder, so the register's P-2 row leads `Closed` while §1.1 — the page a reader trusts for the
 * state of the work — still names a blocker that has been ruled away.
 *
 * This is lane 61's mirror defect in the document's own summary row: an instrument that catches a sentence
 * presenting an *answered* id as undecided cannot catch a row that keeps an *answered* id's stale blocker,
 * because the row carries no verdict word to contradict.
 *
 * Pass 179's rule is honoured: the 2026-10-22–23 record keeps its own words, and the correction arrives as a
 * clause carrying its own date. One run, verified unique before any write.
 *
 *   php storage/app/scratch/refresh-docx-pass185e.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass185e.php --apply  # write
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

$from = 'so closing P-2 is a blade’s layout once the blank form arrives.';
$to = 'so closing P-2 is a blade’s layout once the blank form arrives. That blocker was ruled away on 2026-10-23: no blank will be supplied, and the block was built from the designs in the gitignored Images folder, which closed P-2 the same day. On 2026-10-24 the owner ruled the year level’s proper format, so every printed surface and desk screen now takes the level from one helper on each side rather than spelling it.';

$say(substr_count($xml, $from) === 1, 'the clause is found exactly once (count '.substr_count($xml, $from).')');
foreach (['<', '>', '&'] as $ch) {
    $say(! str_contains($to, $ch), 'the replacement carries no '.$ch);
}
$say(str_contains($to, 'once the blank form arrives'), 'the 2026-10-22 reading is kept inside the row, not overwritten');
$say(str_contains($to, '2026-10-23'), 'the ruling that removed the blocker carries its own date');
$say(str_contains($to, '2026-10-24'), 'the vocabulary ruling carries its own date');

$staged = str_replace($from, $to, $xml);
$say(substr_count($staged, $to) === 1, 'the replacement landed once');
$say(substr_count($staged, 'once the blank form arrives.') === 1, 'the sentence is not duplicated');
$say(preg_match('/spelling it\. storage\/app\/scratch\/mutate-p2-payload-tests/u', $staged) === 1, 'the next sentence about the mutate harness still follows the new clause, unbroken');
$say(substr_count($staged, 'P-2 waits') === 0, 'no present-tense waiting claim was introduced');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');

printf("\n%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: §1.1's living row would gain the two dated rulings and stop naming a ruled-away blocker.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass185e.docx');
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
