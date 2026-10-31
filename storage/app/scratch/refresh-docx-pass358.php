<?php

/**
 * Pass 358 — §28.3 G-40's register row names the fourteenth wired table (the subjects catalogue).
 *
 * Two figures and one fact. The row said thirteen pages call the shared control and 23 of the 36 offer no order;
 * a catalogue that opens on the code ascending joined the mechanism, so both move together — leaving either
 * standing is the membership defect this document has refused in lanes 46, 61, 64, 69 and 90.
 *
 * The fact worth recording in the authority and not only in the working agreement: a queue and a catalogue have
 * different defaults, and `ListSort` answers `desc` when no direction is sent. The handler states `asc` on its own
 * behalf, which is why no helper signature changed and the thirteen earlier screens keep the SQL they were proved on.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass358.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    ['the thirteen wired pages call', 'the fourteen wired pages call'],
    [
        'Examination results screen and on the audit log — 23 of the 36',
        'Examination results screen, on the audit log and on the subjects catalogue — 22 of the 36',
    ],
    [
        'into a grouped star on MySQL 8.',
        'into a grouped star on MySQL 8. The subjects catalogue joined as the fourteenth table the same day and is the '
        .'first wired screen that is not a queue: a queue is read newest-first while a catalogue is read top to bottom, so '
        .'the handler states `asc` on its own behalf beside its fallback column instead of widening the helper — one '
        .'default per list, stated where the list is built.',
    ],
];

foreach ($edits as $i => $edit) {
    $got = substr_count($xml, $edit[0]);
    $want = $i === 2 ? 1 : 1;
    if ($got !== $want) {
        fwrite(STDERR, sprintf("REFUSED: needle %d occurs %d time(s), wanted %d [%s]\n", $i, $got, $want, substr($edit[0], 0, 60)));

        exit(1);
    }
    echo sprintf("ok   needle %d occurs once\n", $i);
}

docxApply($docx, $root.'/storage/app/scratch/pass358-backup.docx', $edits, [], $apply);

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

foreach ([
    ['the thirteen wired pages call', 0],
    ['the fourteen wired pages call', 1],
    ['23 of the 36', 0],
    ['22 of the 36', 1],
    ['The subjects catalogue joined as the fourteenth table', 1],
] as [$needle, $want]) {
    $got = substr_count($after, $needle);
    if ($got !== $want) {
        echo sprintf("BAD  [%s] occurs %d, wanted %d — restoring.\n", substr($needle, 0, 44), $got, $want);
        copy($root.'/storage/app/scratch/pass358-backup.docx', $docx);

        exit(1);
    }
}

echo "written and read back: G-40 names fourteen wired tables, 22 of the 36 owed, and the catalogue's own ascending default.\n";
