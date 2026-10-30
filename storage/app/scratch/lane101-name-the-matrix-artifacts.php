<?php

/**
 * Task #43, second half: make the two `NO-ARTIFACT` rows resolvable by naming what they were built in.
 *
 * `lane94-matrix-claims.php` prints "a Built claim that names no file" rather than clearing such a row, and once the
 * matrix grew a Status column that verdict became present-tense for every row — so G-7 and G-39, whose cells say the
 * work happened without saying where, stood out. Both were checked against the tree before being written: the billing
 * label lives in three files under `resources/js` (a grep counts four occurrences, two of them in `Assessment/Show.jsx`),
 * and the tone map is pinned by `tests/Feature/Enrollment/EnrollmentToneVocabularyTest.php`, which the row named only
 * as a class-like phrase.
 *
 * Usage: php storage/app/scratch/lane101-name-the-matrix-artifacts.php [--apply]
 */
$root = dirname(__DIR__, 3);
$file = $root.'/Documentation/Prompt-Requirement-Matrix-2026-10-26.md';
$apply = in_array('--apply', $argv, true);

// needle => replacement, each with the evidence that was read to write it.
$edits = [
    [
        'Read back off the files (four sites), ESLint silent, Vite built.',
        'Read back off the files today: `MegaAppLauncher.jsx`, `Accounting/Show.jsx` and `Assessment/Show.jsx` carry the label '
        .'(a grep counts four occurrences across those three). ESLint silent, Vite built.',
    ],
    [
        'which is how `YearLevelVocabularyTest` pins the PHP-to-JS pair',
        'which is how `YearLevelVocabularyTest` pins the PHP-to-JS pair; the tone rule itself is '
        .'`tests/Feature/Enrollment/EnrollmentToneVocabularyTest.php`, which reads the `.jsx` sources because there is no '
        .'JavaScript suite',
    ],
];

$text = (string) file_get_contents($file);
foreach ($edits as [$from, $to]) {
    $n = substr_count($text, $from);
    if ($n !== 1) {
        fwrite(STDERR, "REFUSED: the needle occurs {$n} time(s), want exactly 1:\n  ".mb_substr($from, 0, 70)."\n");

        exit(1);
    }
    if (! file_exists($root.'/resources/js/Components/navigation/MegaAppLauncher.jsx')
        || ! file_exists($root.'/tests/Feature/Enrollment/EnrollmentToneVocabularyTest.php')) {
        fwrite(STDERR, "REFUSED: one of the artifacts this pass names does not exist in the tree.\n");

        exit(1);
    }
}

printf("%s: two rows gain the files their Built claim always meant\n", $apply ? 'APPLY' : 'DRY RUN');

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

file_put_contents($file, str_replace(array_column($edits, 0), array_column($edits, 1), $text));
echo "APPLIED.\n";
