<?php

/**
 * Pass 352 — the register row follows the ninth wired table (§28.3 G-40).
 *
 * The Department Evaluation queue became the ninth desk table that sorts on the server through `App\Support\ListSort`
 * (13 cases / 65 assertions, proved by `mutate-g40-evaluation-sort-tests.php` at seven legs, all killed). The row
 * named eight screens and stated that twenty-eight of the thirty-six pages rendering `DataTable` offer no order, and
 * both of those figures moved with the wiring — a tally that names the work while the work grows is lane 61's defect
 * in a new place, which is exactly what pass 300 corrected for the same sentence.
 *
 * Three needles, each asserted to occur exactly once before anything is written. The middle one is an APPEND: the
 * replacement keeps its needle as its own head, so the read-back expects the retired phrase to SURVIVE (the
 * per-edit residual rule pass 341 met from the other side), while the two count phrases must disappear.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass352.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx to read word/document.xml.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    // The list of screens the row names. An append: the old tail stays, because it is still true.
    ['validation queue — 28 of the 36 pages that use the shared table still offer no order',
        'validation queue, and on the Department Evaluation queue — 27 of the 36 pages that use the shared table still offer no order'],
    ['the eight wired pages call', 'the nine wired pages call'],
];

foreach ($edits as $i => [$from, $to]) {
    $n = substr_count($xml, $from);
    if ($n !== 1) {
        fwrite(STDERR, sprintf("REFUSED: needle %d occurs %d time(s), wanted 1 [%s].\n", $i, $n, substr($from, 0, 60)));

        exit(1);
    }
    echo sprintf("ok   needle %d occurs once: %s\n", $i, substr($from, 0, 60));
}

docxApply($docx, $root.'/storage/app/scratch/pass352-backup.docx', $edits, [], $apply);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen the written docx.\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

// [needle, expected count, why]. The append keeps its own head, so `validation queue — 28` is gone while
// `validation queue, and on the Department Evaluation queue` arrived exactly once.
$checks = [
    ['validation queue — 28 of the 36', 0, 'the retired tally must not survive anywhere'],
    ['validation queue, and on the Department Evaluation queue — 27 of the 36', 1, 'the new tally, in the same cell'],
    ['the eight wired pages call', 0, 'the retired screen count'],
    ['the nine wired pages call', 1, 'the new screen count'],
];
$bad = 0;
foreach ($checks as [$needle, $want, $why]) {
    $got = substr_count($after, $needle);
    if ($got !== $want) {
        echo sprintf("BAD  [%s] occurs %d time(s), wanted %d — %s\n", substr($needle, 0, 40), $got, $want, $why);
        $bad++;
    }
}
if ($bad > 0) {
    copy($root.'/storage/app/scratch/pass352-backup.docx', $docx);
    fwrite(STDERR, "REFUSED: the read-back failed, so the backup was restored.\n");

    exit(1);
}
echo "written and read back: G-40 now names nine wired tables and 27 of 36 still offering no order.\n";
