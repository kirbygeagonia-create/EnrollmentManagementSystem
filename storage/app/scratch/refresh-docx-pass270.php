<?php

/**
 * §18's lane-49 blind-spot figure, re-read from the tool.
 *
 * The document states the checker's own two blind-spot tallies, and this batch's new desk components added lines
 * of the shape lane 49 counts: it prints 345 where the document still said 342. The figure is taken from the
 * tool's output rather than typed, so the pass cannot land on a number the instrument no longer answers.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass270.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

// The figure the checker prints today, read out of its own summary line.
$lane49 = shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/storage/app/scratch/lane49-zero-shown-as-absence.php').' 2>&1');
if (! preg_match('/Not a money\/percent figure the guard itself prints:\s*(\d+)/', (string) $lane49, $m)) {
    fwrite(STDERR, "REFUSED: lane 49 printed no 'Not a money/percent figure' tally, so the new figure is unmeasured.\n");

    exit(1);
}
$tally = (int) $m[1];
printf("lane 49 answers %d lines where the tested operand is not a figure it prints\n", $tally);

$zip = new ZipArchive;

if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

    exit(1);
}

$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$from = '342 lines where the tested operand';
$to = "{$tally} lines where the tested operand";

if (substr_count($xml, $to) === 1 && substr_count($xml, $from) === 0) {
    fwrite(STDERR, "REFUSED: this pass's own sentence is already in the document.\n");

    exit(1);
}

$n = substr_count($xml, $from);
if ($n !== 1) {
    fwrite(STDERR, "REFUSED: needle [{$from}] matched {$n} place(s), expected exactly 1.\n");

    exit(1);
}

$updated = str_replace($from, $to, $xml);

if (substr_count($updated, $from) !== 0 || substr_count($updated, $to) !== 1) {
    fwrite(STDERR, "REFUSED: the after-count is wrong (old ".substr_count($updated, $from).', new '.substr_count($updated, $to).").\n");

    exit(1);
}

foreach ([['<w:p', '</w:p>'], ['<w:t', '</w:t>'], ['<w:tc', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl', '</w:tbl>']] as [$open, $close]) {
    $a = preg_match_all('/'.preg_quote($open, '/').'(?=[\s>])/', $updated);
    $b = substr_count($updated, $close);
    if ($a !== $b) {
        fwrite(STDERR, "REFUSED: the edited document is unbalanced in {$open} ({$a} against {$b}).\n");

        exit(1);
    }
}

if (simplexml_load_string($updated) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}

printf("%s§18 would answer 342 -> %d\n", $apply ? '' : 'DRY RUN — ', $tally);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass270-backup-'.date('Ymd-His').'.docx';

if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup.\n");

    exit(1);
}

$zip = new ZipArchive;

if (! $zip->open($docx, ZipArchive::CREATE)) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing.\n");

    exit(1);
}

$zip->addFromString('word/document.xml', $updated);
$zip->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

if ($read !== $updated) {
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed. Restore from {$backup}.\n");

    exit(1);
}

echo "\npass 270 applied: the document states the blind-spot figure lane 49 prints today.\n";
