<?php

/**
 * Docx pass 396 — the §1.1 row's owed clause, now delivered.
 *
 * Pass 391 recorded the contention phase as not re-run since the key returned. The run has since been taken
 * (`--keep`, ems_l172_a, 10 workers over 1,000 issues then 10 at one seat), so the clause is re-taken from the
 * log's own counters rather than softened: 1,000 created / 0 refused in the volume phase, one winner and nine
 * refusals in contention, and the driver breakdown attributing eight refusals to the server's 1062 and three to
 * the application predicate. The row keeps its earlier 12-refusals reading as a dated record elsewhere in the
 * document, so nothing is re-aged here — only this sentence's claim about what has not been measured.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass396.php [--apply]
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
require_once $root.'/storage/app/scratch/docx-edit-engine.php';

$from = 'contention phase has not been re-run since the return so its refusal count is a reading of the earlier '
    .'live-key moment';
$to = 'contention phase has been re-run on the returned key, and the run’s own driver breakdown attributes 8 '
    .'refusals to the server’s code 1062 and 3 to seatHolder(), so both layers are seen refusing on one schema';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: the docx did not open.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$n = substr_count($xml, $from);
if ($n === 0) {
    if (substr_count($xml, $to) === 1) {
        echo "nothing to do: the clause already reads as the delivered run\n";
        exit(0);
    }
    fwrite(STDERR, "REFUSED: the owed clause is not a contiguous run (count 0) — read the split before writing.\n");
    exit(1);
}
if ($n !== 1) {
    fwrite(STDERR, "REFUSED: the clause occurred {$n} time(s), expected exactly 1.\n");
    exit(1);
}

printf("%sthe clause is unique and addressable (%d bytes → %d bytes)\n", $apply ? '' : 'DRY RUN — ', strlen($from), strlen($to));

$backup = $root.'/storage/app/scratch/pass396-orig.docx';
if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the working copy.\n");
    exit(1);
}
docxApply($docx, $backup, [[$from, $to]], [], $apply);
if (! $apply) {
    exit(0);
}

$again = new ZipArchive;
if ($again->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: the written docx did not reopen.\n");
    exit(1);
}
$read = (string) $again->getFromName('word/document.xml');
$again->close();
if (substr_count($read, $to) !== 1 || substr_count($read, $from) !== 0) {
    fwrite(STDERR, "REFUSED: read-back did not show the single rewritten clause; restore from {$backup}.\n");
    exit(1);
}
echo "read-back: the owed clause now states the delivered run.\n";
