<?php

/**
 * Pass 370 — the grade-scale bands join the sortable tables, and this time no citation aged.
 *
 * §28.3 G-40's row counts the wired screens and the owed ones: seventeen and 19 today, eighteen and 18 after this.
 * The day's §1.1 record grows one sentence. What the sentence records is the cheap case, and it is only knowable
 * because the diff was read rather than assumed: all three of this batch's hunks in `ReferenceDataController.php`
 * start at old line 693, which is below every line the document cites in that file (47-123, 108, 400, 572), so the
 * twenty-two net lines inserted nothing above a pointer. The pass asserts that by comparing HEAD's export with the
 * working file at exactly those numbers — a claim of "nothing moved" that no bounds sweep can make, because a
 * stale pointer still lands on a live line of the right file.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass370.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = __DIR__.'/pass370-backup.docx';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$headPath = __DIR__.'/lane133-head-referencedatacontroller.php';
if (! file_exists($headPath)) {
    fwrite(STDERR, "REFUSED: the HEAD export is missing. Re-take it with:\n"
        ."  git show HEAD:app/Http/Controllers/Admin/ReferenceDataController.php > "
        ."storage/app/scratch/lane133-head-referencedatacontroller.php\n");

    exit(1);
}
$head = explode("\n", (string) file_get_contents($headPath));
$live = explode("\n", (string) file_get_contents($root.'/app/Http/Controllers/Admin/ReferenceDataController.php'));

// The four spans the document cites in this file, plus the AGENTS.md pointer, each with the text that makes it a
// citation rather than a coincidence. Comparing HEAD's line with the working line at the same number is the whole
// claim: if they are equal, the pointer did not age, and no shift run is owed.
$spans = [
    'the 47-123 range opens here' => 47,
    'the grouped-search read AGENTS.md cites' => 83,
    'the courses action’s Academicunits read' => 108,
    'the subject-validation read' => 400,
    'the school-year read' => 572,
];
foreach ($spans as $label => $n) {
    $h = $head[$n - 1] ?? '(past the end of HEAD)';
    $w = $live[$n - 1] ?? '(past the end of the working file)';
    if ($h !== $w) {
        fwrite(STDERR, sprintf("REFUSED: line %d (%s) moved. HEAD [%s] / working [%s] — a shift run is owed, not this pass.\n",
            $n, $label, trim($h), trim($w)));

        exit(1);
    }
    printf("ok   line %d (%s) is byte-identical to HEAD: %s\n", $n, $label, substr(trim($w), 0, 56));
}

$from = 'resolver is run after the last insertion of a batch and not after each one.';
$appended = $from.' The grade-scale bands joined as the eighteenth the same day, and they are the first screen in '
    .'this tranche whose insertion aged nothing: all three hunks land below every line the document cites in that '
    .'controller, which the pass proves by comparing HEAD’s bytes with the working bytes at each cited number rather '
    .'than by hoping a bounds sweep would notice. A clean sweep over a file nobody shifted is the same printed zero '
    .'as a clean sweep over one that aged every pointer below line 47, so the equality is the finding here.';

$edits = [
    ['the seventeen wired pages call', 'the eighteen wired pages call', null, 1],
    ['on the courses catalogue and on the majors list — 19 of the 36',
        'on the courses catalogue, on the majors list and on the grade-scale bands — 18 of the 36', null, 1],
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
    ['the seventeen wired pages call', 0],
    ['the eighteen wired pages call', 1],
    ['19 of the 36', 0],
    ['18 of the 36', 1],
    ['and on the grade-scale bands — 18 of the 36', 1],
    ['The grade-scale bands joined as the eighteenth the same day', 1],
    // The four cited spans must still read exactly as they did before the pass, because the pass wrote no number.
    ['ReferenceDataController.php:47-123', 1],
    ['ReferenceDataController.php:108', 1],
    ['ReferenceDataController.php:400', 1],
    ['ReferenceDataController.php:572', 1],
] as [$n, $want]) {
    $got = substr_count($after, $n);
    if ($got !== $want) {
        echo sprintf("BAD  [%s] occurs %d, wanted %d — restoring.\n", substr($n, 0, 56), $got, $want);
        copy($backup, $docx);

        exit(1);
    }
}

echo "written and read back: eighteen wired tables, 18 owed, and no citation in the file moved.\n";
