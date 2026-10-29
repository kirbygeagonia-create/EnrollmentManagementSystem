<?php

/**
 * Pass 280b: §24's own column-aggregate row.
 *
 * Pass 280 moved the per-table heading and the census sentence; this row is the third place that figure
 * lives — the cross-check cell that adds every column the section documents. AGENTS.md names the shape:
 * a register row that gains a column moves the total that counts the register, and the pass that just
 * made the move is the one that has to re-type the total.
 *
 * The number is not asserted here from arithmetic; `lane60-ratio-claims.php --run` is run after the write
 * and this pass reports whether it goes silent, which is the only reading that says 436 is right.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass280b.php [--apply]
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

require __DIR__.'/docx-edit-engine.php';

$from = '435 columns documented for 56 domain tables';
$to = '436 columns documented for 56 domain tables';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error code, not true).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

if (substr_count($xml, $from) !== 1) {
    fwrite(STDERR, 'REFUSED: ['.$from.'] occurs '.substr_count($xml, $from)." time(s); a pass writes unique needles.\n");

    exit(1);
}
$toBefore = substr_count($xml, $to);

$edits = [[$from, $to]];
$backup = $root.'/storage/app/scratch/pass280b-backup-'.date('Ymd-His').'.docx';

if ($apply && ! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup to {$backup}.\n");

    exit(1);
}

printf("%s: §24's aggregate row takes the added column\n", $apply ? 'APPLY' : 'DRY RUN');
docxApply($docx, $apply ? $backup : $root.'/storage/app/scratch/pass280b-dry.docx', $edits, [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

$want = $toBefore + 1;
if (substr_count($read, $from) !== 0 || substr_count($read, $to) !== $want) {
    fwrite(STDERR, 'REFUSED READ-BACK: ['.$from.'] '.substr_count($read, $from).' left (want 0), ['.$to.'] '
        .substr_count($read, $to)." left (want {$want}); restore from {$backup}.\n");

    exit(1);
}

echo "\npass 280b applied: the aggregate cell now reads what the section adds up to.\n";
