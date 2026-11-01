<?php

/**
 * Pass 373 — the matrix names the case files behind the six newest wired screens, and states no figure.
 *
 * The G40 row's prose says the staff directory, the courses catalogue, the majors list and the grade-scale bands
 * joined the mechanism, and even records the LEFT-hop lesson that came out of the staff directory — but it names no
 * test file for any of them, while the four earlier screens each carry theirs. So a reader asking "which case pins
 * the courses order?" finds no answer in the register, and the next batch has to re-derive it from the tree.
 *
 * The classes are named without case or assertion totals: an assertion count is a property of the run that printed
 * it (lane 58), and a sentence that pairs several classes with one figure is the multi-total defect. The figures are
 * re-taken beside each class name afterwards by lane107-retake-class-figures.php from the run that contains them.
 *
 * Usage: php storage/app/scratch/refresh-matrix-pass373.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
$apply = in_array('--apply', $argv, true);
$rel = 'Documentation/Prompt-Requirement-Matrix-2026-10-26.md';
$path = $root.'/'.$rel;

$text = (string) file_get_contents($path);

// Each class the sentence is about to name must exist, or the register would cite a file the repository does not
// hold — the same shape as a comment that cites a register row the register does not contain (lane 96).
$classes = [
    'tests/Feature/Admin/StaffDirectorySortTest.php',
    'tests/Feature/Sorting/CourseListSortTest.php',
    'tests/Feature/Sorting/MajorListSortTest.php',
    'tests/Feature/Sorting/GradeScaleSortTest.php',
    'tests/Feature/Sorting/SubjectListSortTest.php',
];
foreach ($classes as $c) {
    if (! file_exists($root.'/'.$c)) {
        fwrite(STDERR, "REFUSED: the sentence would name {$c}, which is not in the tree.\n");

        exit(1);
    }
    printf("ok   %s exists\n", $c);
}
$harnesses = [
    'storage/app/scratch/mutate-g40-staff-directory-tests.php',
    'storage/app/scratch/mutate-g40-course-sort-tests.php',
    'storage/app/scratch/mutate-g40-major-sort-tests.php',
    'storage/app/scratch/mutate-g40-gradescale-sort-tests.php',
];
foreach ($harnesses as $h) {
    if (! file_exists($root.'/'.$h)) {
        fwrite(STDERR, "REFUSED: the sentence would cite {$h}, which is not in the tree.\n");

        exit(1);
    }
    printf("ok   %s exists\n", $h);
}

$from = 'and drop a clearance whose term holds none. |';
$appended = 'and drop a clearance whose term holds none. The six newest wired screens carry their own case files — '
    .'`tests/Feature/Admin/StaffDirectorySortTest.php`, `tests/Feature/Sorting/SubjectListSortTest.php`, '
    .'`tests/Feature/Sorting/CourseListSortTest.php`, `tests/Feature/Sorting/MajorListSortTest.php` and '
    .'`tests/Feature/Sorting/GradeScaleSortTest.php` — each proved by its own mutant harness '
    .'(`mutate-g40-staff-directory-tests.php`, `mutate-g40-course-sort-tests.php`, `mutate-g40-major-sort-tests.php`, '
    .'`mutate-g40-gradescale-sort-tests.php`), and no case or assertion total is written here on purpose: an assertion '
    .'count belongs to the run that printed it, and one sentence pairing several classes is how a figure gets read as '
    .'belonging to the wrong one. |';

$got = substr_count($text, $from);
if ($got !== 1) {
    fwrite(STDERR, "REFUSED: the row's closing clause occurs {$got} time(s), wanted 1.\n");

    exit(1);
}
printf("ok   the anchor clause occurs once\n");

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$bak = $root.'/storage/app/scratch/lane143-'.basename($rel).'.bak';
copy($path, $bak);
$new = str_replace($from, $appended, $text);
if ($new === $text) {
    fwrite(STDERR, "REFUSED: the replacement changed nothing.\n");

    exit(1);
}
$written = @file_put_contents($path, $new);
if ($written !== strlen($new)) {
    fwrite(STDERR, 'REFUSED: the write returned '.var_export($written, true)."; restoring.\n");
    copy($bak, $path);

    exit(1);
}

// Read back: the row is still ONE line, its column count is UNCHANGED (measured before the write, not remembered —
// a frozen pipe count is the verifier expectation that ages), the gap rows are all still present, and no named class
// was lost to the write. A raw pipe inside a cell would split the row and silently shrink the table.
if (substr_count($appended, '|') !== substr_count($from, '|')) {
    fwrite(STDERR, 'REFUSED: the replacement carries '.substr_count($appended, '|').' pipe(s) where the anchor carries '
        .substr_count($from, '|')." — a raw pipe inside a cell splits the row. Restoring.\n");
    copy($bak, $path);

    exit(1);
}

$beforeText = (string) file_get_contents($bak);
$beforeRow = '';
foreach (explode("\n", $beforeText) as $line) {
    if (str_starts_with($line, '| G40 |')) {
        $beforeRow = $line;

        break;
    }
}
$pipesBefore = substr_count($beforeRow, '|');

$after = (string) file_get_contents($path);
$row = null;
foreach (explode("\n", $after) as $line) {
    if (str_starts_with($line, '| G40 |')) {
        $row = $line;

        break;
    }
}
if ($row === null) {
    fwrite(STDERR, "REFUSED: the G40 row is gone — the table lost a row.\n");
    copy($bak, $path);

    exit(1);
}
$pipes = substr_count($row, '|');
if ($pipes !== $pipesBefore) {
    fwrite(STDERR, "REFUSED: the G40 row held {$pipesBefore} pipe(s) before the write and holds {$pipes} after — a "
        ."raw pipe inside a cell splits the row.\n");
    copy($bak, $path);

    exit(1);
}
$rowsBefore = preg_match_all('/^\| G\d+ \|/m', $beforeText, $tt) ?: 0;
$rowsAfter = preg_match_all('/^\| G\d+ \|/m', $after, $tt2) ?: 0;
if ($rowsAfter !== $rowsBefore) {
    fwrite(STDERR, "REFUSED: the gap table held {$rowsBefore} G-rows before the write and {$rowsAfter} after.\n");
    copy($bak, $path);

    exit(1);
}
foreach (array_merge($classes, $harnesses) as $n) {
    if (! str_contains($row, basename($n))) {
        fwrite(STDERR, 'REFUSED: '.basename($n).' is not in the row after the write.'."\n");
        copy($bak, $path);

        exit(1);
    }
}
printf("ok   the row kept its %d column separator(s) and the table its %d G-rows\n", $pipes, $rowsAfter);
echo "written and read back: the row is one line, its columns intact, and every named file is cited.\n";
