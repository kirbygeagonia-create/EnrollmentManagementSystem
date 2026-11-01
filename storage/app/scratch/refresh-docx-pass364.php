<?php

/**
 * Pass 364 — the four docx pointers into the reference-data controller, re-taken by reading both versions.
 *
 * Two of them are a plain shift: the lines they name moved down by thirty when the courses action gained its sort
 * block, and each new number was proved by comparing HEAD's window with the working file byte for byte.
 *
 * The other two are older and worse. §24's `academicunits` row cites line 83 for "read as a dropdown", but HEAD's 83
 * sits inside `courses()`'s pagination chain — the units read is `$units = Academicunits::all([...])`, thirty lines
 * away. §24's `academicyears` row cites 490 for "read only as a dropdown source", and HEAD's 490 is a
 * `prerequisiteSubjectId` clause in the subject delete guard. Both are bounds-clean, both name the right file, and
 * neither carries the claim its own sentence makes: the wrong-target shape this repository has met in every batch
 * that opens a pointer queue, arriving here from a screen this batch wired.
 *
 * So they are re-pointed to the lines that hold the claim — not shifted by the delta, which would have carried on
 * pointing at the wrong construct with a newer number.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass364.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = __DIR__.'/pass364-backup.docx';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

// Every new number is asserted against the line it names before anything is written.
$controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/ReferenceDataController.php');
$lines = explode("\n", $controller);
$assertions = [
    108 => '$units = Academicunits::all(',
    551 => '$years = Academicyears::all(',
    379 => "'subjectId' => 'required|exists:subjects,subjectId'",
    123 => 'public function storeCourse(Request $request): RedirectResponse',
    47 => '*/',
];
foreach ($assertions as $n => $needle) {
    $actual = trim($lines[$n - 1] ?? '');
    if (! str_contains($actual, $needle)) {
        fwrite(STDERR, sprintf("REFUSED: line %d reads [%s], not the claim [%s] this pass would write.\n", $n, substr($actual, 0, 80), $needle));

        exit(1);
    }
    printf("ok   line %d carries %s\n", $n, $needle);
}

$edits = [
    ['ReferenceDataController.php:83', 'ReferenceDataController.php:108', null, 1],
    ['ReferenceDataController.php:490', 'ReferenceDataController.php:551', null, 1],
    ['ReferenceDataController.php:349', 'ReferenceDataController.php:379', null, 1],
    ['ReferenceDataController.php:47-93', 'ReferenceDataController.php:47-123', null, 1],
];

foreach ($edits as $i => $edit) {
    $got = substr_count($xml, $edit[0]);
    if ($got !== 1) {
        fwrite(STDERR, sprintf("REFUSED: needle %d occurs %d time(s), wanted 1 [%s]\n", $i, $got, $edit[0]));

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
    ['ReferenceDataController.php:83', 0],
    ['ReferenceDataController.php:108', 1],
    ['ReferenceDataController.php:490', 0],
    ['ReferenceDataController.php:551', 1],
    ['ReferenceDataController.php:349', 0],
    ['ReferenceDataController.php:379', 1],
    ['ReferenceDataController.php:47-93', 0],
    ['ReferenceDataController.php:47-123', 1],
] as [$needle, $want]) {
    $got = substr_count($after, $needle);
    if ($got !== $want) {
        echo sprintf("BAD  [%s] occurs %d, wanted %d — restoring.\n", $needle, $got, $want);
        copy($backup, $docx);

        exit(1);
    }
}

echo "written and read back: two pointers moved with the file and two were re-taken to the lines that hold their claim.\n";
