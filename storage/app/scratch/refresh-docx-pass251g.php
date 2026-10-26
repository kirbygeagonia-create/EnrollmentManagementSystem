<?php

/**
 * Docx pass 251g — the leg count in §1.1, corrected against the harness's own print.
 *
 * The revision row wrote "16 legs" for `mutate-g3-crediting-decision-tests.php`. The file holds
 * thirteen planted defects plus a control before and after, and its own summary line prints
 * "All 15 legs passed" — the number was written from the plan, not from the run, which is the
 * lane 59 defect class in a sentence this document is meant to outlive.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass251g.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass251g-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$textEdits = [
    ['crediting-decision-tests.php (16 legs', 'crediting-decision-tests.php (15 legs', null, 1],
];

echo "pass 251g — text edits (".count($textEdits).")\n";
docxApply($docx, $backup, $textEdits, [], $apply);
echo $apply ? "written.\n" : "DRY RUN — nothing written.\n";
