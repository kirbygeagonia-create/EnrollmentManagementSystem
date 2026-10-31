<?php

/**
 * Pass 359 — the register's lane 49 figure is re-taken from the instrument, not carried.
 *
 * `lane49-zero-shown-as-absence.php` prints its own blind spots, and the G-18 row in the authority document quotes
 * two of them: "21 guards whose dash sits on a line this file cannot join" and the count of lines where the tested
 * operand is a name, code, date or error string. The first still matches. The second says 355 while the tool answers
 * 359 today, because this batch added lines of exactly that shape to two desk pages — the same effect pass 176
 * recorded when a JSX edit moved the figure from 339 to 342. A figure bound to an instrument is re-taken from the
 * instrument (lane 59 refuses the run otherwise); it is never adjusted by arithmetic.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass359.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = __DIR__.'/pass359-backup.docx';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

// The replacement figure comes from the checker's own footer, read here rather than typed from memory — and the
// CURRENT figure is located by its shape, not by a literal this pass froze. A pass anchored on the number it last
// wrote is a pass that refuses its own correct output the next time the tree moves (lane 79's verifier-expectations
// rule, met inside my own instrument).
$cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/storage/app/scratch/lane49-zero-shown-as-absence.php').' 2>&1';
$toolOut = (string) shell_exec($cmd);
if (! preg_match('/Not a money\/percent figure the guard itself prints:\s*(\d+)/', $toolOut, $m)) {
    fwrite(STDERR, "REFUSED: lane 49 printed no operand-line figure, so nothing can be re-taken.\n");

    exit(1);
}
$today = (int) $m[1];

if (! preg_match('/(\d+) lines where the tested operand/', $xml, $mm)) {
    fwrite(STDERR, "REFUSED: the document holds no `N lines where the tested operand` claim to re-take.\n");

    exit(1);
}
$inDoc = (int) $mm[1];
$needle = $inDoc.' lines where the tested operand';

if ($inDoc === $today) {
    echo "ALREADY CURRENT — lane 49 answers {$today} operand lines, so the sentence holds and nothing is written.\n";

    exit(0);
}

$edits = [
    [$needle, $today.' lines where the tested operand'],
];

foreach ($edits as $i => $edit) {
    $got = substr_count($xml, $edit[0]);
    if ($got !== 1) {
        fwrite(STDERR, sprintf("REFUSED: needle %d occurs %d time(s), wanted 1 [%s]\n", $i, $got, substr($edit[0], 0, 60)));

        exit(1);
    }
    echo sprintf("ok   needle %d occurs once; lane 49 answers %d operand lines today\n", $i, $today);
}

docxApply($docx, $backup, $edits, [], $apply);

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

foreach ([
    [$needle, 0],
    [$today.' lines where the tested operand', 1],
    ['21 guards whose dash', 1],
] as [$check, $want]) {
    $got = substr_count($after, $check);
    if ($got !== $want) {
        echo sprintf("BAD  [%s] occurs %d, wanted %d — restoring.\n", substr($check, 0, 44), $got, $want);
        copy($backup, $docx);

        exit(1);
    }
}

echo "written and read back: G-18's lane 49 figure equals what the checker prints today.\n";
