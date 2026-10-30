<?php

/**
 * Pass 319b: the day's §1.1 row stops writing citation shapes into prose about citations.
 *
 * Pass 319's own sentence named the numbers `:243` and `:290` in the colon form while explaining why they could not
 * be mapped, and the sweep duly read them as pointers — one landed on the blind list because no file named it inside
 * the attribution window. This file has the rule already ("a citation-shaped token is a citation even in prose about
 * citations"), and the fix is the documented one: spell the numbers as words and keep only the pointers that are
 * actually live.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass319b.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

require __DIR__.'/docx-edit-engine.php';

$from = 'because the numbers :243 and :290 are also the tails of another file’s ranges — '
    .'`Preflight.php:86-104 … :228-243` and `ClearanceController.php:290-304` —';
$to = 'because the numbers 243 and 290 are also the tails of another file’s ranges — Preflight.php lines 86 to 104, '
    .'whose application-key rule runs from 228 to 243, and ClearanceController.php:290-304 —';

$tmp = tempnam(sys_get_temp_dir(), 'p319b');
file_put_contents($tmp, (string) file_get_contents($docx));
$zip = new ZipArchive;
if ($zip->open($tmp) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");
    unlink($tmp);

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
unlink($tmp);

foreach ([[$from, 1], [$to, 0]] as [$needle, $want]) {
    $h = substr_count($xml, $needle);
    if ($h !== $want) {
        fwrite(STDERR, "REFUSED: [".mb_substr($needle, 0, 60)."...] occurs {$h}, want {$want} — the run-split or the wording is not what pass 319 wrote.\n");

        exit(1);
    }
}

printf("%s: the row's own pointers stop being prose tokens\n", $apply ? 'APPLY' : 'DRY RUN');

docxApply($docx, $apply ? $root.'/storage/app/scratch/pass319b-backup-'.date('Ymd-His').'.docx'
    : $root.'/storage/app/scratch/pass319b-dry.docx', [[$from, $to]], [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
if ($again->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open the written archive.\n");

    exit(1);
}
$after = (string) $again->getFromName('word/document.xml');
$again->close();

foreach ([
    [$to, 1],
    [$from, 0],
    ['the numbers :243 and :290', 0],
] as [$needle, $want]) {
    $h = substr_count($after, $needle);
    if ($h !== $want) {
        fwrite(STDERR, "REFUSED after the write: [".mb_substr($needle, 0, 60)."...] occurs {$h}, want {$want}.\n");

        exit(1);
    }
}

echo "APPLIED.\n";
