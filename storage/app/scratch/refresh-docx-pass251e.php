<?php

/**
 * Docx pass 251e — §24's column aggregate, which the census checker found on its own.
 *
 * The seven columns this batch added to two domain tables move the dictionary's own total, and
 * `lane60-ratio-claims.php` named it (`documentHeadings.domainStatedColumns claimed 410, the
 * server answers 417`). Recorded as its own pass because the aggregate is the row that goes stale
 * silently: every per-table heading can be right while the sum underneath them is wrong.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass251e.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass251e-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$textEdits = [
    ['410 columns documented for 55 domain tables', '417 columns documented for 55 domain tables', null, 1],
];

echo "pass 251e — text edits (".count($textEdits).")\n";
docxApply($docx, $backup, $textEdits, [], $apply);
echo $apply ? "written.\n" : "DRY RUN — nothing written.\n";
