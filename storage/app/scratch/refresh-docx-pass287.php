<?php

/**
 * Pass 287: §28.3's G-43 row understates its own family, and the new sweep says so.
 *
 * The row names five columns — the five the app's desk paths were writing at the time — and lane 87's
 * migration-bound sweep enumerates seven names across 48 write sites, `enrollments.formSignedDate` alone
 * accounting for fourteen of them (thirteen inside the demo generator). A register row that reports the
 * subset it happened to measure is the "claim about a set" defect in a new place, so the row is re-taken.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass287.php [--apply]
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

$from = 'are all `date` on the server and all fed a Carbon.';
$to = 'are all `date` on the server and all fed a Carbon — five sites on the desk paths. Bound instead to the '
    .'migrations rather than to a live database, `lane87-date-write-columns.php` enumerates the family at seven '
    .'names across 48 write sites, `enrollments.formSignedDate` alone accounting for fourteen of them (thirteen '
    .'inside the demo generator, which the static gates do not analyse), so the five named here are what the '
    .'desks write today and not the size of the class. The instrument carries its own positive control: it '
    .'refuses the run unless all five named columns come back as candidates, because a sweep that cannot say '
    .'"found it" can only ever say "clean". Its binding is by column NAME, and it prints the tables declaring '
    .'each name so the reader confirms the model — `assessmentDate`, for one, is `date` on two tables.';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

if (substr_count($xml, $from) !== 1) {
    fwrite(STDERR, 'REFUSED: the needle occurs '.substr_count($xml, $from)." time(s); it must be one run of one cell.\n");

    exit(1);
}

$backup = $root.'/storage/app/scratch/pass287-backup-'.date('Ymd-His').'.docx';
if ($apply && ! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: no backup.\n");

    exit(1);
}

printf("%s: G-43's row takes the enumerated family\n", $apply ? 'APPLY' : 'DRY RUN');
docxApply($docx, $apply ? $backup : $root.'/storage/app/scratch/pass287-dry.docx', [[$from, $to]], [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();
if (substr_count($read, $from) !== 0 || substr_count($read, $to) !== 1) {
    fwrite(STDERR, "REFUSED READ-BACK: old ".substr_count($read, $from).', new '.substr_count($read, $to).".\n");

    exit(1);
}

echo "\npass 287 applied: the register states the class the sweep enumerated.\n";
