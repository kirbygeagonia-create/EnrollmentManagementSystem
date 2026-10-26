<?php

/**
 * Docx pass 251f — lane 49's own tally, which this batch moved by three lines.
 *
 * §28.3's G-18 row states what `lane49-zero-shown-as-absence.php` prints. The Evaluation panel
 * this batch grew added three lines whose dash operand is a name rather than a figure, so the
 * checker's blind-spot count moved 339 → 342 and `lane59-instrument-counts.php` refused the
 * document. Re-measured from the tool after the JSX was final, not shifted by arithmetic: the
 * figure a document states is the one the instrument answers today.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass251f.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass251f-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$textEdits = [
    ['339 lines where the', '342 lines where the', null, 1],
];

echo "pass 251f — text edits (".count($textEdits).")\n";
docxApply($docx, $backup, $textEdits, [], $apply);
echo $apply ? "written.\n" : "DRY RUN — nothing written.\n";
