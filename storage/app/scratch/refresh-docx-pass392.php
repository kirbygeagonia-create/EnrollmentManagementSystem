<?php

/**
 * Docx pass 392 — the count sentence's own case.
 *
 * Pass 390 rebuilt §23.6.1's count sentence from a lowercase word map and wrote `thirteen keys in ten tables.`
 * where the paragraph opens with a capital, which is the defect AGENTS records for this very sentence: the engine
 * applies a case-only change happily, and `lane44-unique-keys.php` matches with `/i`, so both the write and the
 * checker agreed while the document read wrong. The repair is one text edit with the original casing restored, and
 * it asserts the needle it replaces is the register's own sentence rather than a quotation of the figure elsewhere
 * in the document — the §1.1 row now quotes the same words, and a document-wide replace would re-case that sentence
 * mid-clause into a proper noun.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass392.php [--apply]
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

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: the docx did not open.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

// The register's sentence, identified by its own terminal period: the §1.1 row quotes the same words without one,
// so the punctuation is what separates the claim from the quotation. The tail cannot be part of the needle — it
// lives in a different `<w:t>` run, and a phrase that reads continuously on screen is not contiguous in the file.
$from = 'thirteen keys in ten tables.';
$to = 'Thirteen keys in ten tables.';
$n = substr_count($xml, $from);
if ($n === 0) {
    if (substr_count($xml, $to) === 1) {
        echo "nothing to do: the sentence already opens capitalised\n";
        exit(0);
    }
    fwrite(STDERR, "REFUSED: the register's count sentence was not found as a contiguous run; read the run split before writing.\n");
    exit(1);
}
if ($n !== 1) {
    fwrite(STDERR, "REFUSED: the needle occurred {$n} time(s), expected exactly 1.\n");
    exit(1);
}

printf("%sthe sentence is addressable and occurs once: [%s]\n", $apply ? '' : 'DRY RUN — ', $from);

$backup = $root.'/storage/app/scratch/pass392-orig.docx';
if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the working copy the engine reads.\n");
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
    fwrite(STDERR, "REFUSED: read-back did not show the sentence capitalised once and the old spelling gone; restore from {$backup}.\n");
    exit(1);
}
echo "read-back: the register's sentence opens capitalised.\n";
