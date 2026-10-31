<?php

/**
 * Pass 361 — four docx pointers into the user-management controller were aged by the block inserted into `index()`.
 *
 * The line sweep flagged only the range that landed on a blank line; the other three sit on live code in the right
 * file and say nothing (lane 88's defect, met the same week it was written down). Every new number here was proved by
 * comparing the WHOLE cited window in HEAD's export with the working file byte for byte — not by adding thirty-eight
 * to each, which is how a map written from arithmetic extends a citation past the construct its own sentence names.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass361.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = __DIR__.'/pass361-backup.docx';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$head = 'storage/app/scratch/lane127-head-UserManagementController.php';
if (! is_file(__DIR__.'/'.basename($head))) {
    fwrite(STDERR, "REFUSED: no HEAD export of the controller. Take it first:\n"
        ."  git show HEAD:app/Http/Controllers/Admin/UserManagementController.php > storage/app/scratch/lane127-head-UserManagementController.php\n");

    exit(1);
}
$headLines = explode("\n", (string) file_get_contents($head));
$liveLines = explode("\n", (string) file_get_contents($root.'/app/Http/Controllers/Admin/UserManagementController.php'));
$window = function (array $lines, int $a, int $b): string {
    return implode("\n", array_slice($lines, $a - 1, $b - $a + 1));
};

// [old start, old end, new start, new end, expected occurrences of the full token]
$shifts = [
    [472, 492, 510, 530, 2],
    [495, 495, 533, 533, 1],
    [505, 505, 543, 543, 1],
    [516, 561, 554, 599, 1],
];

$edits = [];
$expectAfter = [];
foreach ($shifts as [$o1, $o2, $n1, $n2, $want]) {
    if ($window($headLines, $o1, $o2) !== $window($liveLines, $n1, $n2)) {
        fwrite(STDERR, sprintf("REFUSED: HEAD %d-%d does not equal the working %d-%d byte for byte.\n", $o1, $o2, $n1, $n2));

        exit(1);
    }
    $oldTok = 'UserManagementController.php:'.($o2 > $o1 ? $o1.'-'.$o2 : (string) $o1);
    $newTok = 'UserManagementController.php:'.($n2 > $n1 ? $n1.'-'.$n2 : (string) $n1);
    $got = substr_count($xml, $oldTok);
    if ($got !== $want) {
        fwrite(STDERR, sprintf("REFUSED: [%s] occurs %d time(s), wanted %d.\n", $oldTok, $got, $want));

        exit(1);
    }
    printf("ok   %-42s window proved, %d occurrence(s)\n", $oldTok.' -> '.$newTok, $want);
    // Slot 3 is the anchor (null here, because the token itself is unique per copy) and slot 4 is the count the
    // caller expects; the engine replaces every copy only when it is told how many there are.
    $edits[] = [$oldTok, $newTok, null, $want];
    $expectAfter[] = [$oldTok, 0];
    $expectAfter[] = [$newTok, $want];
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
        echo sprintf("BAD  [%s] occurs %d, wanted %d — restoring the backup.\n", substr($needle, 0, 50), $got, $want);
        copy($backup, $docx);

        exit(1);
    }
}

echo "written and read back: every pointer into the controller names the line that carries its claim today.\n";
