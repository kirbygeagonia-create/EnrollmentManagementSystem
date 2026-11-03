<?php

/**
 * Append the changelog rows for the twenty-fifth and twenty-sixth wired tables and for the figure verifier,
 * then re-take the AGENTS bullets that state what the tranche now measures.
 *
 * The changelog is a TWO-CELL table: a row written with three cells silently shrinks the table, and every checker
 * still reads it as a row. So each new line is asserted to hold exactly three pipes, and the file's line count is
 * asserted to grow by exactly the number of rows added.
 */
$path = 'Documentation/IMPLEMENTATION-CHANGELOG-ADS.md';
$before = (string) file_get_contents($path);
$linesBefore = count(explode("\n", $before));

$anchor = '| `storage/app/scratch/refresh-docx-pass422.php` + `storage/app/scratch/lane215-plan-remaining-screens.php`';
$at = strpos($before, $anchor);
if ($at === false || substr_count($before, $anchor) !== 1) {
    fwrite(STDERR, "REFUSED: the anchor row occurs ".substr_count($before, $anchor)." time(s), wanted 1\n");
    exit(1);
}
$lineEnd = strpos($before, "\n", $at);
if ($lineEnd === false) {
    fwrite(STDERR, "REFUSED: the anchor row has no line terminator\n");
    exit(1);
}

$rows = [
    '| `ReferenceDataController::admissionRequirements()` + `resources/js/Pages/Admin/ReferenceData/AdmissionRequirements.jsx` | the twenty-fifth list table wired to the one sort mechanism. Name, Applies To and Status are offered, all three stored on the row, so no hop is written and no order can widen or drop a record; `appliesTo` is an ENUM and its sequence is asked of the connection through `tests/Support/EnumColumnOrder.php` rather than typed. The finding this screen produced is a vocabulary one: the page held a module-level list of five hand-typed applicant types beside a picker on the same field while the handler already answered the enum, so a sixth value would appear in the form and be refused by the filter. The constant is gone and both controls read the server prop now. |',
    '| `tests/Feature/Admin/AdmissionRequirementSortTest.php` + `storage/app/scratch/mutate-g40-admission-requirement-tests.php` | the case file and its falsifier for that catalogue, including the type-filter/type-form equality case and the echo of all five keys a desk can set at once. Two lessons came out of the harness rather than the screen. Its first run reported a surviving leg on the Status header and the fixture was the defect: three rows named so that sorting by `requirementName` produced exactly the sequence sorting by the flag produced, so the mutant satisfied the assertion and the comment claiming the two orders differed was false as written. And its multi-pair helper used `$originals` without importing it, so the plant\'s own did-it-land check compared against null and warned per leg while still reporting killed. |',
    '| `ReferenceDataController::clearanceRequirements()` + `resources/js/Pages/Admin/ReferenceData/ClearanceRequirements.jsx` + `tests/Feature/Admin/ClearanceRequirementSortTest.php` + `storage/app/scratch/mutate-g40-clearance-requirement-tests.php` | the twenty-sixth list table wired to the one sort mechanism, and the one whose DEFAULT was a measurement. Two columns are painted; the Office header is a hop through `clearancerequirements.officeId`, which the migration declares NOT NULL with a foreign key and live `ems` answers with 9 rows, 0 nulls, 9 distinct offices and no orphan, so the INNER hop cannot drop a requirement. Making it the opening order replaced the seeder numbering (Registrar, Accounting, Scholarship…) with the office name (Academic Department first), and both sequences were read off the live table before the sentence was written, then pinned by a case. `requirementName` is nullable and the page paints a warning in its place, so a case asserts an unworded requirement survives the order that reads it; the class\'s own first run found that assertion wrong rather than the code, because it demanded the row\'s own `officeId` be absent. |',
    '| `storage/app/scratch/lane217-verify-actor-figures.mjs` + `mutate-lane217-figure-verifier.php` | an independent reader for the use-case deliverable, adding the one check the generator structurally cannot make: a breadth-first closure from every actor, so a use case no actor can arrive at and a node with no edge are refused rather than drawn as boxes. The answer today is zero of each, and the 11 use cases with no association of their own are printed with the «include»/«extend» chain that reaches them, because arriving through a chain is legitimate UML, not a finding. Its first run printed 43 BADs and every one was the reader\'s own guess (`e.rel` where the model writes `e.kind`, associations matched only actor-first when 8 of 97 are written the other way, and a label-scoped chain count set against the generator\'s figure-scoped one), and the chain-count assertion was withdrawn rather than softened. Surfaces are addressable (`--model=`, `--manifest=`, `--index=`, `--figures=`) so each of its 12 legs plants in a throwaway copy. |',
];
foreach ($rows as $i => $r) {
    $pipes = substr_count($r, '|');
    if ($pipes !== 3) {
        fwrite(STDERR, "REFUSED: new row {$i} holds {$pipes} pipes, wanted exactly 3 for a two-cell table\n");
        exit(1);
    }
    if (str_contains($r, "\n")) {
        fwrite(STDERR, "REFUSED: new row {$i} is not one physical line\n");
        exit(1);
    }
}

$after = substr_replace($before, "\n".implode("\n", $rows), $lineEnd, 0);
$linesAfter = count(explode("\n", $after));
if ($linesAfter !== $linesBefore + count($rows)) {
    fwrite(STDERR, "REFUSED: the file grew by ".($linesAfter - $linesBefore).' lines, wanted '.count($rows)."\n");
    exit(1);
}
file_put_contents($path, $after);
echo 'written: '.count($rows)." changelog rows appended after the pass-422 row; file line count {$linesBefore} -> {$linesAfter}\n";
