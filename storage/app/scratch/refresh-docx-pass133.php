<?php

/**
 * Pass 133 — the §1.1 revision row said "my.cnf still owed on the school's server", which read as forgotten
 * work rather than as an install-time step for a machine nobody has built.
 *
 * The owner asked what was actually owed, and the honest answer turned out to be nothing: no host, credential,
 * compose file or deploy script exists anywhere in the repository, every `.env` host is 127.0.0.1, and
 * `Documentation/First_Build_Plan.md:185` still carries the on-campus Linux server as an unchecked box. The
 * dev-box half is verified in force (re-measured 2026-10-16: `@@global.time_zone` is `+00:00`, `NOW()` equals
 * `UTC_TIMESTAMP()`, `ems:preflight` prints `OK Clocks agree`). So the clause was not a pending action — it
 * was guidance wearing the costume of a task, which is the same failure mode as a note nobody can close.
 *
 * DEPLOYMENT.md §2 was reworded the same way in this batch, and the tracking task was retired rather than
 * left pending forever.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['with my.cnf still owed on the school’s server',
        '; the server-side my.cnf line is an install-time step for whichever machine hosts the campus install, '
        .'and that machine does not exist yet'],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-16-pass133.docx',
    $textEdits, [], in_array('--apply', $argv, true));
