<?php

/**
 * Pass 355 — one instrument figure the Blocking wiring moved, re-taken from the tool rather than retyped by hand.
 *
 * `lane49-zero-shown-as-absence.php` counts the lines where the operand a guard tests is a name, code, date or
 * error message — a shape count over `resources/js`. The roster page grew four lines while its columns gained
 * `sortKey`, so the figure moved 354 to 355 and §28.3's G-25 row went stale on the same edit that wired a header.
 * `lane59-instrument-counts.php` named it; this pass writes what the tool prints, in the row's own words, and the
 * read-back refuses unless the retired number is gone and the new one arrived exactly once.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass355.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

// The figure is read out of the tool, never recalled: the same command line §28.3's row cites.
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
chdir($root);
$out = (string) shell_exec(escapeshellarg($php).' storage/app/scratch/lane49-zero-shown-as-absence.php 2>&1');
if (preg_match('/Not read \(a dash on the line, no recoverable operand\): (\d+)/', $out, $un) !== 1
    || preg_match('/Not a money\/percent figure the guard itself prints: (\d+)/', $out, $op) !== 1) {
    fwrite(STDERR, "REFUSED: lane 49 printed neither figure, so nothing here can be re-taken.\n");

    exit(1);
}
$guards = (int) $un[1];
$operands = (int) $op[1];
echo "lane49 prints: {$guards} unjoinable guards, {$operands} non-figure operands\n";

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$from = 'guards whose dash sits on a line this file cannot join, 354 lines where the tested operand';
$to = 'guards whose dash sits on a line this file cannot join, '.$operands.' lines where the tested operand';
foreach ([[$from, 1, 'the sentence as the row states it today']] as [$needle, $want, $why]) {
    $got = substr_count($xml, $needle);
    if ($got !== $want) {
        fwrite(STDERR, sprintf("REFUSED: needle occurs %d time(s), wanted %d — %s\n", $got, $want, $why));

        exit(1);
    }
}

docxApply($docx, $root.'/storage/app/scratch/pass355-backup.docx', [[$from, $to]], [], $apply);

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

$checks = [
    ['354 lines where the tested operand', 0],
    [$operands.' lines where the tested operand', 1],
];
foreach ($checks as [$needle, $want]) {
    $got = substr_count($after, $needle);
    if ($got !== $want) {
        echo sprintf("BAD  [%s] occurs %d, wanted %d — restoring.\n", substr($needle, 0, 34), $got, $want);
        copy($root.'/storage/app/scratch/pass355-backup.docx', $docx);

        exit(1);
    }
}
echo "written and read back: §28.3 now states the figure lane 49 prints today.\n";
