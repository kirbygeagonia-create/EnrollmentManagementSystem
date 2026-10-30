<?php

/**
 * Pass 317b: §28.3 G-40's TITLE drops the pagination claim its own body retracts.
 *
 * The row's body says the earlier reading — that two queues carry no pagination — "was never measured", and the
 * lane-90 probe answered 29 paginated / 3 collections / 4 client-mapped with no work queue among the three. The
 * title still asserted it, and a register row's title is what a reader takes to the meeting: no instrument judges a
 * title's content, because lane 61 reads status words and lane 59 reads figures.
 *
 * The wording matches the matrix's own G40 row so the two registers say the same thing about one finding.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass317b.php [--apply]
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

$from = 'No list table sorts, and two carry no pagination at all.';
$to = 'No list table sorts, and the pagination claim this row first carried was never measured.';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ([[$from, 1], [$to, 0]] as [$needle, $want]) {
    if (($h = substr_count($xml, $needle)) !== $want) {
        fwrite(STDERR, "REFUSED: [{$needle}] occurs {$h}, want {$want}.\n");

        exit(1);
    }
}
if (($h = substr_count($xml, 'two queues carry none')) !== 1) {
    fwrite(STDERR, "REFUSED: the body's own retraction [two queues carry none] occurs {$h}, want 1 — the sentence this title is being aligned to is not where it was read.\n");

    exit(1);
}

printf("%s: the G-40 title drops the claim the body already retracts\n", $apply ? 'APPLY' : 'DRY RUN');

docxApply($docx, $apply ? $root.'/storage/app/scratch/pass317b-backup-'.date('Ymd-His').'.docx'
    : $root.'/storage/app/scratch/pass317b-dry.docx', [[$from, $to]], [], $apply);

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

foreach ([[$from, 0], [$to, 1], ['two queues carry none', 1]] as [$needle, $want]) {
    if (($h = substr_count($after, $needle)) !== $want) {
        fwrite(STDERR, "REFUSED after the write: [{$needle}] occurs {$h}, want {$want}.\n");

        exit(1);
    }
}

echo "APPLIED.\n";
