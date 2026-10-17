<?php

/**
 * Pass 141f — the suite figure the owner's second ruling produced, read from the whole run.
 *
 * `php artisan test` after the return-path fix: Tests: 731 passed (4602 assertions), Duration: 1325.44s, exit 0.
 * The revision row still carried the previous run's 730 / 4599, so it is corrected here rather than left as the
 * document's own count of a build that no longer exists.
 *
 * The row also gains the clause the second ruling made true: a record opened from either tab returns to that
 * list. §18.3 and the G-10 row itself already say it (pass 141e); this is the issue register.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

docxApply(
    $src,
    $root.'/storage/app/scratch/docx-backup-2026-10-17-pass141f.docx',
    [
        ['The suite stands at 730 tests and 4599 assertions',
            'The suite stands at 731 tests and 4602 assertions'],
        ['gained an Issued tab beside the queue and each status tile now counts the set its own tab shows',
            'gained an Issued tab beside the queue, each status tile counts the set its own tab shows, and a '
            .'record opened from either tab is sent back to the list it came from'],
    ],
    [],
    $apply
);
