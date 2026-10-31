<?php

/**
 * Pass 357 — §28.3 G-40's register row names the thirteenth wired table (the audit log) and the LEFT hop it needed.
 *
 * Three jobs from one edit set.
 *
 * (a) The row counted twelve wired pages and 24 of the 36 with no order. The audit log joined on 2026-10-31, so a
 *     sentence that names the work while the work grows is the defect this document has refused in lanes 46, 61, 64
 *     and 90 — both figures move together, and neither is left as the previous pass wrote it.
 * (b) The mechanism the register has been waiting for is now in the build: `ListSort` takes a per-hop
 *     `'type' => 'left'`, defaulted to INNER so the twelve earlier screens keep byte-identical SQL. The reason is
 *     measured, not reasoned: live `ems` holds `auditlogs.userId` NULL on 3,294 of 3,525 rows, so an INNER hop for
 *     the User header would remove 93% of the desk's list rather than order it.
 * (c) Every needle is asserted to occur exactly once before anything is written, and the read-back requires the
 *     retired phrases to be gone and the new ones to be present. An append that embeds its own needle can never
 *     make that needle disappear, so the residual expectation is per edit — pass 335 and pass 341 both learned it.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass357.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive::open answers an error code for a file that is not an archive).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    ['the twelve wired pages call', 'the thirteen wired pages call'],
    ['Examination results screen — 24 of the 36', 'Examination results screen and on the audit log — 23 of the 36'],
    [
        'every desk queue had exactly one order since it was written.',
        'every desk queue had exactly one order since it was written. The audit log joined as the thirteenth table on '
        .'2026-10-31, and it is the first whose order reaches a row that may not exist: `auditlogs.userId` is NULL on '
        .'3,294 of the 3,525 rows live `ems` holds, so its User header goes through a LEFT hop — an INNER one would not '
        .'mis-order those rows, it would delete 93% of the desk list the moment a header was clicked. `App\Support\ListSort` '
        .'carries the spelling per hop and keeps INNER as the default, which is why the twelve earlier screens did not '
        .'change SQL, and its tile is cloned before the order so a joined sort cannot turn the whole-set count into a '
        .'grouped star on MySQL 8.',
    ],
];

foreach ($edits as $i => $edit) {
    $got = substr_count($xml, $edit[0]);
    if ($got !== 1) {
        fwrite(STDERR, sprintf("REFUSED: needle %d occurs %d time(s), wanted 1 [%s]\n", $i, $got, substr($edit[0], 0, 60)));

        exit(1);
    }
    echo sprintf("ok   needle %d occurs once\n", $i);
}

docxApply($docx, $root.'/storage/app/scratch/pass357-backup.docx', $edits, [], $apply);

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

// [needle, expected count]. The retired phrases must be gone; the new ones must be there exactly once.
foreach ([
    ['the twelve wired pages call', 0],
    ['the thirteen wired pages call', 1],
    ['24 of the 36', 0],
    ['23 of the 36', 1],
    ['The audit log joined as the thirteenth table', 1],
    ['3,294 of the 3,525', 1],
] as [$needle, $want]) {
    $got = substr_count($after, $needle);
    if ($got !== $want) {
        echo sprintf("BAD  [%s] occurs %d, wanted %d — restoring.\n", substr($needle, 0, 40), $got, $want);
        copy($root.'/storage/app/scratch/pass357-backup.docx', $docx);

        exit(1);
    }
}

echo "written and read back: G-40 names thirteen wired tables, 23 of the 36 owed, and the LEFT hop with the figure behind it.\n";
