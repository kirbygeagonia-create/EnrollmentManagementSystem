<?php

/**
 * Docx pass 252d — §24's column aggregate, the last figure G-4 moved.
 *
 * The cross-check row in §24.18 adds every per-table column count in the dictionary and compares
 * the sum with `information_schema`. Three columns joined `examresults`, so 417 becomes 420 — the
 * reading is the server's, taken 2026-10-27, not arithmetic on the previous figure.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass252d.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass252d-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

docxApply($docx, $backup, [['417 columns', '420 columns', null, 1]], [], $apply);
