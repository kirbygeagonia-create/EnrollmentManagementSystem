<?php

/**
 * Docx pass 251d — one cell re-worded, because `§7.3 (G-3)` is not a pointer into §28.3.
 *
 * `lane37-section-reference-sweep.php` reads a `§NN <letter>-<number>` pair as a register
 * reference and asks where the row actually sits. The new enum-vocabulary row in §23 meant
 * "the crediting review, gap G-3 of the development prompt", and the register already holds a
 * §28.3 G-3 that is a different finding entirely — so the document acquired a pointer to a row
 * it was never talking about (WRONG-LOCATION, the sweep's own word). The prompt's gap ids and
 * §28.3's G family share a spelling; a sentence that means the prompt has to say so without the
 * `§NN id` shape.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass251d.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass251d-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$textEdits = [
    ['§7.3 (G-3): only an approved row covers a subject',
        'only an approved row covers a subject — gap G-3 of the development prompt', null, 1],
];

echo "pass 251d — text edits (".count($textEdits).")\n";
docxApply($docx, $backup, $textEdits, [], $apply);
echo $apply ? "written.\n" : "DRY RUN — nothing written.\n";
