<?php

/**
 * Re-take the requirement matrix's G40 row for the twenty-fifth and twenty-sixth wired tables.
 *
 * Every figure in this row is read from `lane140-measure-wired-screens.php` (the tree answers 26 wired and 10 owed
 * over 36 pages on the run this patch was written against) and none is typed from arithmetic. A markdown table row
 * is ONE physical line, so each edit is a string replacement inside that line and the patch refuses if any needle
 * does not occur exactly once — an edit that wraps the row mid-cell does not fail, it shrinks the table.
 */
$path = 'Documentation/Prompt-Requirement-Matrix-2026-10-26.md';
$before = (string) file_get_contents($path);

$record25 = 'The twenty-fifth table is the intake requirement catalogue (`ReferenceDataController::admissionRequirements` serving `Admin/ReferenceData/AdmissionRequirements.jsx`), and it is the first screen in the tranche where the FILTER and the FORM were reading two different lists: the page carried a module-level `appliesToOptions` of five hand-typed values beside a `Select` on the same field, while the handler already answered the enum. `the_type_filter_and_the_type_form_read_one_list_from_the_server` asserts the constant is gone, the picker is built from the `appliesTo` prop, and the payload\'s choices equal the PHP enum, so a sixth type added to the column cannot arrive in the form and be silently refused by the filter. Its `appliesTo` order is asked of the connection through `Tests\Support\EnumColumnOrder`, and its five-row fixture gives four different sequences across the two enum readings and both id orders. `tests/Feature/Admin/AdmissionRequirementSortTest.php` carries it with `mutate-g40-admission-requirement-tests.php`, each printing its own total. That harness taught two lessons the screen did not. Its first run reported a SURVIVED leg on the flag header, and the fixture was the defect: three rows named so that sorting by `requirementName` produced exactly the sequence sorting by the flag produced, so the mutant satisfied the assertion and the comment claiming the two orders differed was false as written — lane 91\'s rule (re-pick the fixture so the target order disagrees with BOTH id orders) met from the column-pair side. The harness also carried an asleep landing check: its multi-pair helper used `$originals` without importing it, so every plant\'s "did it reach the disk" test compared against null and warned per leg while still reporting killed — the guard that never fires, in my own instrument, five lines from the code it guards. ';

$record26 = 'The twenty-sixth table is the clearance checklist catalogue (`ReferenceDataController::clearanceRequirements` serving `Admin/ReferenceData/ClearanceRequirements.jsx`), and choosing its opening order was a measurement rather than a copy. The page paints two columns, one of them an office name one hop away through `clearancerequirements.officeId`, which the migration declares NOT NULL with a foreign key and live `ems` answers with 9 rows, 0 nulls, 9 distinct offices and no orphan, so an INNER hop cannot drop a requirement. Making that hop the fallback changed what the desk sees first: the screen had ordered by `officeId` (Registrar, Accounting, Scholarship…), which is the seeder\'s numbering, while the office-name order puts Academic Department first, and both sequences were read off the live table and do differ — so the change is pinned, not mentioned. The screen\'s own nullable column is the other claim: `requirementName` is nullable and the page paints a warning in its place, so a case writes one unworded requirement and asserts it stays on the list its own header arranged, because an order over a nullable column may not quietly become a filter on the row the desk needs to finish. `tests/Feature/Admin/ClearanceRequirementSortTest.php` and `mutate-g40-clearance-requirement-tests.php` carry it, printing their own totals, and the class\'s first run found the assertion rather than the code wrong: it demanded `officeId` be ABSENT from the row, but that key is the requirement\'s own column which the edit form seeds itself from, so the claim is now that it stays. ';

$edits = [
    // 1 + 2: the two present-tense wired claims, and the owed figure beside them.
    ['the mechanism exists and twenty-four of the 36 pages that render the shared table use it.',
     'the mechanism exists and twenty-six of the 36 pages that render the shared table use it.'],
    ['and the twenty-four wired pages call it — 12 of the 36 pages using the shared table still offer no order.',
     'and the twenty-six wired pages call it — 10 of the 36 pages using the shared table still offer no order.'],
    // 3: the two screens join the named pairs.
    ['`tests/Feature/Admin/BlockRosterSortTest.php` with `mutate-g40-block-roster-tests.php` — and the pairs are named',
     '`tests/Feature/Admin/BlockRosterSortTest.php` with `mutate-g40-block-roster-tests.php` and `tests/Feature/Admin/AdmissionRequirementSortTest.php` with `mutate-g40-admission-requirement-tests.php` and `tests/Feature/Admin/ClearanceRequirementSortTest.php` with `mutate-g40-clearance-requirement-tests.php` — and the pairs are named'],
    // 4: the new records land before the owed-plan sentence, which also moves.
    ['The plan for the twelve screens still owed is derived rather than remembered:',
     $record25.$record26.'The plan for the ten screens still owed is derived rather than remembered:'],
    // 5: the status cell.
    ['Partly built: the mechanism exists and twenty-four of thirty-six tables use it |',
     'Partly built: the mechanism exists and twenty-six of thirty-six tables use it |'],
];

$after = $before;
foreach ($edits as $i => [$from, $to]) {
    $hits = substr_count($after, $from);
    if ($hits !== 1) {
        fwrite(STDERR, "REFUSED: edit {$i} needle occurs {$hits} time(s), wanted exactly 1\n");
        exit(1);
    }
    if ($from === $to) {
        fwrite(STDERR, "REFUSED: edit {$i} is a no-op\n");
        exit(1);
    }
    $after = str_replace($from, $to, $after);
}

// The row must still be ONE physical line, and no retired figure may survive anywhere in the file.
if (substr_count($after, "\n") !== substr_count($before, "\n")) {
    $delta = substr_count($after, "\n") - substr_count($before, "\n");
    fwrite(STDERR, "REFUSED: the patch changed the file's line count by {$delta}; a table row is one line\n");
    exit(1);
}
foreach (['twenty-four of the 36 pages', 'twenty-four of thirty-six tables', 'the twenty-four wired pages',
    '12 of the 36 pages using the shared table', 'twelve screens still owed'] as $retired) {
    if (str_contains($after, $retired)) {
        fwrite(STDERR, "REFUSED: the retired claim survives: [{$retired}]\n");
        exit(1);
    }
}
file_put_contents($path, $after);

$lines = count(explode("\n", $after));
echo 'written; file line count unchanged at '.$lines
    ;
echo "\nwired claims now: ".substr_count($after, 'twenty-six of the 36 pages').' + '
    .substr_count($after, 'twenty-six of thirty-six tables').' + '
    .substr_count($after, 'the twenty-six wired pages')
    ."; owed now: ".substr_count($after, '10 of the 36 pages using the shared table')
    .'; plan now: '.substr_count($after, 'ten screens still owed')."\n";
