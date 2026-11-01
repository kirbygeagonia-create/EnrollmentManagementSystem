<?php

/**
 * Pass 377 — the day's §1.1 record gains the §14 states work, inside the span pass 372 gave that row.
 *
 * Row 37 leads `2026-10-29 – 11-01`, so a sentence about 2026-11-01 belongs in it now; that is what the widening was
 * for. Two things are recorded and neither is a re-aging: prompt §14's loading half turned out to be 28 pages of 28,
 * because the flag that made 24 of them look handled belonged to a SAVE button; and the eighteenth sortable table's
 * fixture wrote grade points 75 and 79.99 into `decimal(3,2)` columns, which SQLite accepted and the school's MySQL
 * refused with error 1264, so the run that was green on this box was red on the runner.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass377.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = __DIR__.'/pass377-backup.docx';

// Figures are read from the instrument in this run, not carried from an earlier sentence.
$out = [];
$code = 0;
exec('C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe storage/app/scratch/lane142-measure-console-states.php 2>&1', $out, $code);
if ($code !== 0) {
    fwrite(STDERR, "REFUSED: lane142 exited {$code}; its figures cannot be written into the document.\n");

    exit(1);
}
$body = implode("\n", $out);
preg_match('/(\d+) repaint the rows from a/', $body, $repaint);
preg_match('/table sites rendered: (\d+)/', $body, $sites);
preg_match_all('/^LOADING\s+(\d+)$/m', $body, $now);
if (($repaint[1] ?? null) === null || ($sites[1] ?? null) === null || ($now[1][0] ?? null) === null) {
    fwrite(STDERR, "REFUSED: could not read the repaint, sites and current-loading figures out of lane142's print.\n");

    exit(1);
}
printf("ok   lane142: %s of %s sites repaint; current LOADING = %s\n", $repaint[1], $sites[1], $now[1][0]);

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$from = 'so the equality is the finding here.';
$to = $from.' The same day the register’s §14 states row moved from unverified to contained on three of its four '
    .'halves: `lane142-measure-console-states.php` weighs the console, empty and loading claims at the source over '
    .'table sites, and the loading half first answered '.$repaint[1].' pages waiting in silence out of the '.$repaint[1]
    .' that repaint from a router call of their own, because 24 of them carried a SAVE button’s pending flag in the same file and had been read as though '
    .'the list refresh were announced too. The wait is now stated once, in words, inside the shared table, armed at '
    .'boot, and the two screens that build their rows by hand read the same flag rather than inventing one; the '
    .'responsive half is still not judged by any scan. And the eighteenth sortable table’s own fixture was corrected '
    .'after the runner refused it: it wrote grade points 75 and 79.99 into `gradescale` columns the migration declares '
    .'`decimal(3,2)`, SQLite accepted them and MySQL answered error 1264, which is the spent-counter lesson reached '
    .'from the type side — a fixture outside the column’s domain is green on this box and red on CI.';

$got = substr_count($xml, $from);
if ($got !== 1) {
    fwrite(STDERR, "REFUSED: the anchor occurs {$got} time(s), wanted 1.\n");

    exit(1);
}
echo "ok   anchor occurs once\n";

docxApply($docx, $backup, [[$from, $to, null, 1]], [], $apply);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen the docx.\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

// An append embeds its own needle, so the old sentence must still be there — exactly once. Demanding zero would
// refuse a correct write (pass 335 and 341 both met this).
foreach ([
    [$from, 1],
    ['The same day the register’s §14 states row moved from unverified to contained on three of its four halves', 1],
    ['MySQL answered error 1264', 1],
] as [$n, $want]) {
    $g = substr_count($after, $n);
    if ($g !== $want) {
        echo sprintf("BAD  [%s] occurs %d, wanted %d — restoring.\n", substr($n, 0, 52), $g, $want);
        copy($backup, $docx);

        exit(1);
    }
}

echo "written and read back: the day's row carries the §14 states work and the fixture-domain correction.\n";
