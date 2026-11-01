<?php

/**
 * Pass 368 — the majors list joins the sortable tables, and two pointers this batch wrote hours ago are already stale.
 *
 * §28.3 G-40's row counts the wired screens and the owed ones: sixteen and 20 today, seventeen and 19 after this.
 * The day's §1.1 record grows one sentence, and two citations written by pass 364 move again for a reason worth
 * stating: `majors()` sits ABOVE the two lines pass 364 re-pointed, so the twenty-seven lines this batch inserted
 * aged 379 and 551 by twenty-one exactly as the previous batch aged everything below `index()`. A file that keeps
 * gaining screens in the middle re-ages its own re-points the same day, which is why the resolver runs after the
 * last insertion of a batch rather than after each one — and why a bounds sweep is no help: 379 and 551 are live
 * lines of the right file belonging to other constructs.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass368.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = __DIR__.'/pass368-backup.docx';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/ReferenceDataController.php');
$lines = explode("\n", $controller);
foreach ([400 => "'subjectId' => 'required|exists:subjects,subjectId'", 572 => '$years = Academicyears::all('] as $n => $claim) {
    $actual = trim($lines[$n - 1] ?? '');
    if (! str_contains($actual, $claim)) {
        fwrite(STDERR, sprintf("REFUSED: line %d reads [%s], not the claim [%s].\n", $n, substr($actual, 0, 70), $claim));

        exit(1);
    }
    printf("ok   line %d carries %s\n", $n, $claim);
}

$from = 'both now name the line that holds the read.';
if (! str_contains($xml, $from)) {
    fwrite(STDERR, "REFUSED: the §1.1 sentence pass 367 appended is not in the document; this pass would write an orphan.\n");

    exit(1);
}

$appended = $from.' The majors list joined as the seventeenth the same day — three painted columns, one '
    .'search, and a hop over `majors.courseId`, which the migration declares NOT NULL and no live row is missing, so '
    .'the INNER spelling is the measured answer here rather than a copy of the staff directory’s LEFT. And two of the '
    .'numbers this batch had just re-taken moved again for that reason: `majors()` sits above them, so twenty-seven '
    .'inserted lines aged the subject-validation read and the school-year read by twenty-one apiece, which is why the '
    .'resolver is run after the last insertion of a batch and not after each one.';

$edits = [
    ['ReferenceDataController.php:379', 'ReferenceDataController.php:400', null, 1],
    ['ReferenceDataController.php:551', 'ReferenceDataController.php:572', null, 1],
    ['the sixteen wired pages call', 'the seventeen wired pages call', null, 1],
    ['on the staff directory and on the courses catalogue — 20 of the 36',
        'on the staff directory, on the courses catalogue and on the majors list — 19 of the 36', null, 1],
    [$from, $appended, null, 1],
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
    ['ReferenceDataController.php:379', 0],
    ['ReferenceDataController.php:400', 1],
    ['ReferenceDataController.php:551', 0],
    ['ReferenceDataController.php:572', 1],
    ['the sixteen wired pages call', 0],
    ['the seventeen wired pages call', 1],
    ['20 of the 36', 0],
    ['19 of the 36', 1],
    ['The majors list joined as the seventeenth the same day', 1],
] as [$n, $want]) {
    $got = substr_count($after, $n);
    if ($got !== $want) {
        echo sprintf("BAD  [%s] occurs %d, wanted %d — restoring.\n", substr($n, 0, 56), $got, $want);
        copy($backup, $docx);

        exit(1);
    }
}

echo "written and read back: seventeen wired tables, 19 owed, and the two aged reads named on their lines today.\n";
