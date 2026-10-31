<?php

/**
 * Pass 360 — the staff directory joins the sortable tables in the authority document, and one filter bug is recorded.
 *
 * Three counts move and one sentence is added. The register row (§28.3 G-40) carried "the fourteen wired pages call"
 * and "22 of the 36"; a fifteenth screen is wired, so both move in the same edit — a tally that names the work while
 * the work grows is lane 61's defect in a new place, and this row has already been caught doing it twice. The §1.1
 * record grows by one dated sentence naming the two things this screen added to the family: an order that reaches a
 * nullable key through a LEFT hop on a second screen, and a search whose alternatives were chained at the builder's
 * top level, which silently ORed away the office and status filters beside it.
 *
 * The appended sentence is checked as an append, not as a replacement: its needle survives inside the new text, so a
 * read-back demanding the old phrase disappear would refuse a correct write and restore its own backup — the trap
 * pass 341 met and recorded.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass360.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = __DIR__.'/pass360-backup.docx';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$append = 'one default per list, stated where the list is built.';
$appended = 'one default per list, stated where the list is built. The staff directory joined as the fifteenth the same '
    .'day, and it is the second screen whose order reaches a nullable key: its Office header goes through a LEFT hop, '
    .'because `staffusers.officeId` is nullable and an INNER join added only to order would delete an office-less '
    .'account from the list rather than mis-place it. Its search had to be grouped first — chained at the builder’s '
    .'top level, its own `orWhere` alternatives ORed away the office and status filters set beside them, measured on '
    .'live `ems` as eleven of twenty-one accounts answering a request that named one office holding three.';

$edits = [
    ['the fourteen wired pages call', 'the fifteen wired pages call'],
    ['on the audit log and on the subjects catalogue — 22 of the 36', 'on the audit log, on the subjects catalogue and on the staff directory — 21 of the 36'],
    [$append, $appended],
];

// [needle, expected count after the write] — an append keeps its own needle, so the retired form is expected once.
$expectAfter = [
    ['the fourteen wired pages call', 0],
    ['the fifteen wired pages call', 1],
    ['22 of the 36', 0],
    ['21 of the 36', 1],
    [$append, 1],
    ['The staff directory joined as the fifteenth the same day', 1],
];

foreach ($edits as $i => $edit) {
    $got = substr_count($xml, $edit[0]);
    if ($got !== 1) {
        fwrite(STDERR, sprintf("REFUSED: needle %d occurs %d time(s), wanted 1 [%s]\n", $i, $got, substr($edit[0], 0, 60)));

        exit(1);
    }
    if ($edit[0] === $edit[1]) {
        fwrite(STDERR, sprintf("REFUSED: edit %d is a no-op\n", $i));

        exit(1);
    }
    echo sprintf("ok   needle %d occurs once\n", $i);
}

docxApply($docx, $backup, $edits, [], $apply);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen the docx.\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ($expectAfter as [$needle, $want]) {
    $got = substr_count($after, $needle);
    if ($got !== $want) {
        echo sprintf("BAD  [%s] occurs %d, wanted %d — restoring the backup.\n", substr($needle, 0, 52), $got, $want);
        copy($backup, $docx);

        exit(1);
    }
}

echo "written and read back: the register counts fifteen wired screens and 21 owed, and the fifteenth’s LEFT hop and grouped search are stated in the day’s record.\n";
