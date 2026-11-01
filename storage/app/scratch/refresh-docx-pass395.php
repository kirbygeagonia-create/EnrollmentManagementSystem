<?php

/**
 * Docx pass 395 — the §1.1 row must record the negative register too.
 *
 * Pass 391's revision row says what the key's return did to §23.6.1 and §24. It said nothing about §23.6.2 — the
 * section that lists the rules the server does NOT enforce — which is where the same return left two rows false, and
 * about §23.9 and §28's storyboard row, which restate the enforcement claim in prose. A register row that records half
 * of a cascade is the membership defect this file has refused in four lanes, so the clause is appended to the row that
 * already carries the day, rather than left in a scratch log.
 *
 * The row was written by pass 391 as ONE run, so any substring of it is contiguous in document.xml; the pass asserts
 * that before writing, and refuses if the needle is not unique. Usage:
 *   php storage/app/scratch/refresh-docx-pass395.php [--apply]
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

$from = 'What is owed, stated rather than smoothed:';
$clause = 'The return also left the document’s negative register behind: §23.6.2 still listed the seat and the fee '
    .'sheet as rules the server does not enforce, §23.9 and §28’s storyboard row still said no unique key exists, and '
    .'the section’s own lead sentence counted three empties where two remained. Pass 393 deleted the two rows and '
    .'proved the other 1910 byte-identical; pass 394 re-took the two prose rows through the engine’s cell kind, which '
    .'is the only addressable route into a cell that splits every identifier into its own run.';
$to = $clause.' '.$from;

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: the docx did not open.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$n = substr_count($xml, $from);
if ($n !== 1) {
    fwrite(STDERR, "REFUSED: the anchor phrase occurred {$n} time(s), expected exactly 1 — the row it belongs to must be read.\n");
    exit(1);
}
if (str_contains($xml, $clause)) {
    echo "nothing to do: the clause is already in the row\n";
    exit(0);
}
printf("%sappending %d bytes to the 2026-11-01 revision row\n", $apply ? '' : 'DRY RUN — ', strlen($clause));

$backup = $root.'/storage/app/scratch/pass395-orig.docx';
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
if (substr_count($read, $clause) !== 1) {
    fwrite(STDERR, "REFUSED: the clause is not present exactly once; restore from {$backup}.\n");
    exit(1);
}
echo "read-back: the clause sits in the row, ahead of the owed-items sentence.\n";
