<?php

/**
 * Pass 173d — the §1.1 row's gate sentence was read as a claim with no subject.
 *
 * lane58-suite-claims.php classifies before it judges, and "Gates on this box: 780 tests, 5,220 assertions" gave it
 * a figure, a surface and no noun to bind the run to: UNBOUND-SUBJECT, which refuses rather than passing. The fix
 * is in the sentence, not the checker: name the suite before its numbers, the way the row above does ("The suite's
 * own size is what phpunit --list-tests discovers: 780 cases today").
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$flat = (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt');
$needle = 'Gates on this box: 780 tests, 5,220 assertions; CI answers 771 passed with 5,013 assertions on '
    .'its SQLite job and 5,015 on its MySQL one, which is why an assertion total now cites a run rather than the code.';
if (substr_count($flat, $needle) !== 1) {
    printf("REFUSED: the gate sentence is present %d time(s), not once.\n", substr_count($flat, $needle));
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/pass173d-backup.docx', [
    [
        $needle,
        'The suite on this box answers 780 tests and 5,220 assertions, and CI answers 5,013 assertions on its '
            .'SQLite job and 5,015 on its MySQL job for the same 771 tests, which is why an assertion total now cites '
            .'a run rather than the code.',
    ],
], [], $apply);
