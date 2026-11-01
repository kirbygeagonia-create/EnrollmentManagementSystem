<?php

/**
 * Pass 367 — the authority document follows the sixteenth wired table, and records what the re-point found.
 *
 * §28.3 G-40's row counts the wired screens and the owed ones; both move. The day's §1.1 row grows one sentence
 * naming the two things this screen added to the family: a column set where four of the seven painted columns are
 * booleans, and a hop that may be INNER because the schema says the key cannot be null — the same question the staff
 * directory answered with LEFT, answered differently because the table answered differently.
 *
 * The sentence also records the finding the re-point turned up, because it is the class this document exists to
 * name: §24's `academicunits` row cited line 83 and its `academicyears` row cited 490 for "read as a dropdown",
 * while both numbers sat on live code belonging to another construct — the units read and the school-year read were
 * thirty and sixty-one lines elsewhere. A bounds sweep clears both, every run has cleared both, and only reading the
 * sentence against the file said so.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass367.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = __DIR__.'/pass367-backup.docx';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$appendFrom = 'one default per list, stated where the list is built.';
$appended = $appendFrom.' The courses catalogue joined as the sixteenth the same day: four of the seven columns it '
    .'paints are booleans, so a board desk that wants the flagged programs together asks with a click rather than by '
    .'reading sixteen rows, and its Unit header reaches `academicunits` through an INNER hop because that migration '
    .'declares `unitId` NOT NULL and no row on live `ems` is missing one — the same question the staff directory '
    .'answered with LEFT, answered differently because the schema said something different. Re-pointing this screen\'s '
    .'citations found two §24 rows whose numbers had never carried their claim: `academicunits` cited line 83 and '
    .'`academicyears` cited 490 for "read as a dropdown", while both lines held live code of another construct and the '
    .'two reads sat five and thirty-one lines away. Both are bounds-clean, so every sweep had passed them; both now '
    .'name the line that holds the read.';

$edits = [
    ['the fifteen wired pages call', 'the sixteen wired pages call', null, 1],
    ['on the subjects catalogue and on the staff directory — 21 of the 36',
        'on the subjects catalogue, on the staff directory and on the courses catalogue — 20 of the 36', null, 1],
    [$appendFrom, $appended, null, 1],
];

foreach ($edits as $i => $edit) {
    $got = substr_count($xml, $edit[0]);
    if ($got !== 1) {
        fwrite(STDERR, sprintf("REFUSED: needle %d occurs %d time(s), wanted 1 [%s]\n", $i, $got, substr($edit[0], 0, 60)));

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

foreach ([
    ['the fifteen wired pages call', 0],
    ['the sixteen wired pages call', 1],
    ['21 of the 36', 0],
    ['20 of the 36', 1],
    ['The courses catalogue joined as the sixteenth the same day', 1],
    ['Re-pointing this screen', 1],
    [$appendFrom, 1],
] as [$needle, $want]) {
    $got = substr_count($after, $needle);
    if ($got !== $want) {
        echo sprintf("BAD  [%s] occurs %d, wanted %d — restoring.\n", substr($needle, 0, 52), $got, $want);
        copy($backup, $docx);

        exit(1);
    }
}

echo "written and read back: G-40 counts sixteen wired tables and 20 owed, and the day's record names the two pointers that never carried their claim.\n";
