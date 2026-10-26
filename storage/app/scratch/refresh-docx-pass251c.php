<?php

/**
 * Docx pass 251c — take the citation shapes out of the row 251b just wrote.
 *
 * The §1.1 row records that two pointers were false by meaning, and it did so by QUOTING them:
 * `EvaluationController.php:58,190` and `:959-1000`. A citation-shaped token is a citation even
 * inside prose about citations — this file has recorded that lesson twice — so the row invented
 * two pointers the document does not owe, and the sweep would judge them against the code. It
 * also made pass 251's own two repairs ambiguous: the needle appeared once too often, in the
 * sentence that describes the defect.
 *
 * The row now says what those pointers named, in words, and the repair passes address only the
 * real citations.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass251c.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass251c-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$textEdits = [
    ['Two citations in this document were found false by meaning while its ranges were being re-measured: EvaluationController.php:58,190 named a blank line and a ternary arm, and the bare :959-1000 offered confirmClearance() while those lines sit inside store() and approvedPassSlip(); both were re-taken against the live file.',
        'Two pointers were found false by meaning while the ranges were being re-measured. The BR32 row quoted lines 58 and 190 of the Evaluation controller, which are the class brace and one arm of a ternary rather than the refusal the row describes; and a bare continuation in the pass-slip paragraph was offered for confirmClearance() while those lines sit inside two other methods. Both were re-taken against the live file, and the numbers they now carry were read off it.',
        null, 1],
];

echo "pass 251c — text edits (".count($textEdits).")\n";
docxApply($docx, $backup, $textEdits, [], $apply);
echo $apply ? "written.\n" : "DRY RUN — nothing written.\n";
