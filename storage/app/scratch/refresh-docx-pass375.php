<?php

/**
 * Pass 375 — lane 49's operand-line figure moved, and the AGENTS copy of it was never bound at all.
 *
 * Two surfaces state what `lane49-zero-shown-as-absence.php` prints. The docx's §28.3 row said 362 lines; the tool
 * now answers 363, because this batch's grade-scale JSX added a line of the shape it counts — the same cascade
 * recorded when a JSX pass moved the blind-spot count 339 → 342. `lane59-instrument-counts.php` caught that one and
 * refused the run.
 *
 * AGENTS.md carries the SAME pair (20 guards, 345 lines) and lane59 said nothing about it — not because those
 * figures are right (the tool answers 21 and 363) but because lane59 binds a figure only where its own sentence
 * names the instrument, and this sentence says "prints its own blind spots" while the tool is named three lines
 * earlier in the bullet. That is the documented one-sentence window, so the repair belongs in the document: name the
 * tool in the sentence that carries the numbers. A stale figure the checker cannot see is not a clean figure.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass375.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
chdir($root);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = __DIR__.'/pass375-backup.docx';

// Both figures come from the tool, in this run. Nothing here is typed from memory or from arithmetic.
$out = [];
$code = 0;
exec('C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe storage/app/scratch/lane49-zero-shown-as-absence.php 2>&1', $out, $code);
if ($code !== 0) {
    fwrite(STDERR, "REFUSED: lane49 exited {$code}; its figures cannot be re-taken from a run that did not complete.\n");

    exit(1);
}
$body = implode("\n", $out);
$guards = preg_match('/^Not read \(a dash on the line, no recoverable operand\):\s*(\d+)$/m', $body, $g) === 1
    ? (int) $g[1] : null;
$lines = preg_match('/^Not a money\/percent figure the guard itself prints:\s*(\d+)$/m', $body, $l) === 1
    ? (int) $l[1] : null;
if ($guards === null || $lines === null) {
    fwrite(STDERR, "REFUSED: could not read both figures out of lane49's own print (guards="
        .var_export($guards, true).", lines=".var_export($lines, true)
        .") — the labels moved, and a figure typed from memory is what this pass exists to refuse.\n");

    exit(1);
}
printf("ok   lane49 answers %d unread guards and %d operand lines\n", $guards, $lines);

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    ['21 guards whose dash sits on a line this file cannot join, 362 lines where the tested operand',
        sprintf('%d guards whose dash sits on a line this file cannot join, %d lines where the tested operand', $guards, $lines),
        null, 1],
];

foreach ($edits as $i => $edit) {
    $got = substr_count($xml, $edit[0]);
    if ($got !== 1) {
        fwrite(STDERR, sprintf("REFUSED: needle %d occurs %d time(s), wanted 1 [%s]\n", $i, $got, substr($edit[0], 0, 70)));

        exit(1);
    }
    echo sprintf("ok   needle %d occurs once\n", $i);
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

$expect = sprintf('%d guards whose dash sits on a line this file cannot join, %d lines where the tested operand', $guards, $lines);
foreach ([
    ['362 lines where the tested operand', 0],
    [$expect, 1],
] as [$n, $want]) {
    $got = substr_count($after, $n);
    if ($got !== $want) {
        echo sprintf("BAD  [%s] occurs %d, wanted %d — restoring.\n", substr($n, 0, 56), $got, $want);
        copy($backup, $docx);

        exit(1);
    }
}

echo "written and read back: the docx carries lane49's own two figures from this run.\n";
printf("(AGENTS.md carries the same pair as %d and %d and its sentence must name the tool — done by hand in this batch,%s",
    $guards, $lines, " so lane59 can bind it.)\n");
