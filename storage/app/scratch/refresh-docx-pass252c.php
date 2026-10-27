<?php

/**
 * Docx pass 252c — the two figures pass 252 could not reach.
 *
 * Pass 252 rewrote §24's `examresults` heading by editing the XML it had already read, then
 * handed the file to `docxApply()` — which re-reads from disk, so the heading change was
 * computed and thrown away. `lane60-ratio-claims.php` found it the way it always finds these:
 * the document said 8 columns and the server holds 11. A pre-edit to `$xml` in a script that
 * then calls the engine is the same mistake as editing a copy, so both remaining figures go
 * through the engine as text edits, where the byte offset it reports is the byte that changed.
 *
 * §22's preamble is the other one: it declares the catalogue's size, and `exam.verify` made it
 * 94. The same sentence's role count stays 17 — the batch added a right, not a role.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass252c.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass252c-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$textEdits = [
    ['examresults  —  8 columns', 'examresults  —  11 columns', null, 1],
    ['93 permissions across 14 modules', '94 permissions across 14 modules', null, 2],
];

docxApply($docx, $backup, $textEdits, [], $apply);
