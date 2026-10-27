<?php

/**
 * The one BlockingController pointer the byte shift could not take: a bare continuation.
 *
 * `lane76-shift-cites.php` re-pointed 38 number tokens into `BlockingController.php` after this batch
 * extracted `detectConflicts()` into `App\Support\TimetableConflicts`, but it refused `:116` — the token
 * is `( :116 )`, a bare continuation with no file in front of it, and its own attribution pass named a
 * different file for it than its range list did. Two answers to "which file owns this pointer" inside
 * one instrument is the defect AGENTS.md has already paid for twice, so this pass takes the pointer by
 * hand, asserting the line it lands on before writing anything.
 *
 * §12's sentence reads: a block "states its own year level on the form it is created with
 * ( BlockingController.php:179 ) and filters its roster by it ( :116 )". Measured in the working copy,
 * line 117 is `->where('yearLevel', $block->yearLevel)` and line 116 is the `termId` above it — so the
 * roster filter moved +1 with the rest of the file, and only this pointer is being corrected.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass256.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass256-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$live = file_get_contents($root.'/app/Http/Controllers/Blocking/BlockingController.php');
$lines = explode("\n", $live);

$assertions = [
    116 => 'termId',
    117 => 'yearLevel',
];

foreach ($assertions as $number => $mustContain) {
    $text = trim($lines[$number - 1] ?? '');

    if (! str_contains($text, $mustContain)) {
        fwrite(STDERR, "REFUSED: line {$number} of BlockingController.php reads '{$text}', which does not contain {$mustContain}\n");

        exit(1);
    }

    printf("ok   BlockingController.php:%d reads %s\n", $number, $text);
}

docxApply(
    $docx,
    $backup,
    [
        [':116', ':117', 'roster by it', 1],
    ],
    [],
    $apply
);
