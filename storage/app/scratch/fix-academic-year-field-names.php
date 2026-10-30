<?php

/**
 * The three remaining pages that spell an academic year from a field no model supplies.
 *
 * `AcademicYearLabelSurfaceTest` forbids the class, and on the run that proved itself it named three more sites
 * beyond the six clearance ones already re-taken: the Admission intake and case pages and the Examination form build
 * their term label as `${semester} ${academicYear?.year}`. `academicyears` has no `year` column, no accessor and no
 * append — so where the clearance pages interpolated the missing value into the word `undefined`, these three fall
 * back to `''` and the desk simply never sees which academic year a term belongs to: two dropdowns offer "1st",
 * "2nd" with no year to tell one academic year's "1st" from another, and the admission case page prints a bare
 * semester beside the dash it uses for absence.
 *
 * Same field the rest of the system reads: `yearLabel` already spells "2025-2026".
 *
 * Usage: php storage/app/scratch/fix-academic-year-field-names.php [--apply]
 */
$root = dirname(__DIR__, 3);
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$files = [
    $root.'/resources/js/Pages/Admission/Create.jsx' => [
        ['label: `${t.semester} ${t.academicYear?.year || \'\'}`', 'label: `${t.semester} ${t.academicYear?.yearLabel || \'\'}`'],
    ],
    $root.'/resources/js/Pages/Admission/Show.jsx' => [
        ['{admission.term?.academicYear?.year || \'\'}', '{admission.term?.academicYear?.yearLabel || \'\'}'],
    ],
    $root.'/resources/js/Pages/Exam/Create.jsx' => [
        ['label: `${t.semester} ${t.academicYear?.year || \'\'}`', 'label: `${t.semester} ${t.academicYear?.yearLabel || \'\'}`'],
    ],
];

$plans = [];
foreach ($files as $file => $edits) {
    $bytes = (string) file_get_contents($file);
    foreach ($edits as $i => [$from, $to]) {
        // The trailing space in the two dropdown needles is part of the template, not padding: drop it and the
        // count goes to zero and the fix silently misses the site it exists for.
        $hits = substr_count($bytes, $from);
        if ($hits !== 1) {
            fwrite(STDERR, sprintf("REFUSED: %s edit %d needle occurs %d time(s), want 1: [%s]\n", basename($file), $i, $hits, $from));

            exit(1);
        }
        $bytes = str_replace($from, $to, $bytes);
    }
    if (preg_match('/academicYear\??\.year\b/', $bytes)) {
        fwrite(STDERR, sprintf("REFUSED: %s still reads .year\n", basename($file)));

        exit(1);
    }
    $plans[$file] = $bytes;
}

printf("%s: %d site(s) re-spelled from yearLabel\n", $apply ? 'APPLY' : 'DRY RUN', count($plans));

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

foreach ($plans as $file => $bytes) {
    $backup = $file.'.pre-year-names-'.date('Ymd-His');
    copy($file, $backup);
    file_put_contents($file, $bytes);
    if ((string) file_get_contents($file) !== $bytes) {
        copy($backup, $file);
        fwrite(STDERR, 'REFUSED: '.basename($file).' did not read back; restored.'.basename($backup)."\n");

        exit(1);
    }
}

echo "APPLIED: the term label names a year again on all three surfaces.\n";
