<?php

/**
 * Re-take the requirement matrix's G40 row for the twenty-second wired table.
 *
 * Every figure comes from `lane140-measure-wired-screens.php`, which named these four phrases and refused; nothing
 * here is arithmetic. Each needle is asserted to occur exactly once, the replacement is asserted to change bytes,
 * and the row is asserted to keep its cell count — a markdown table row is ONE line, and an edit that wraps it
 * mid-cell does not fail, it shrinks the table.
 *
 * Usage: php storage/app/scratch/lane202-retake-matrix-office.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
$apply = in_array('--apply', array_slice($GLOBALS['argv'], 1), true);
$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$file = 'Documentation/Prompt-Requirement-Matrix-2026-10-26.md';
$body = (string) file_get_contents($root.'/'.$file);
$pipesBefore = substr_count($body, '|');

$pairs = [
    ['the twenty-one wired pages call', 'the twenty-two wired pages call'],
    ['twenty-one of the 36 pages that render the shared table use it', 'twenty-two of the 36 pages that render the shared table use it'],
    ['twenty-one of thirty-six tables use it', 'twenty-two of thirty-six tables use it'],
    ['15 of the 36 pages using the shared table still offer no order', '14 of the 36 pages using the shared table still offer no order'],
    // The pair list gains the newest class and its harness; the row names pairs rather than counting them, which is
    // the reason a figure over a set that grows every screen cannot live here.
    ["`tests/Feature/Admin/ScholarshipTypeSortTest.php` with `mutate-g40-scholarship-sort-tests.php` — and the pairs are named",
     "`tests/Feature/Admin/ScholarshipTypeSortTest.php` with `mutate-g40-scholarship-sort-tests.php` and `tests/Feature/Admin/OfficeDirectorySortTest.php` with `mutate-g40-office-directory-tests.php` — and the pairs are named"],
];

foreach ($pairs as $i => [$from, $to]) {
    $hits = substr_count($body, $from);
    if ($hits !== 1) {
        $fail('pair '.($i + 1).": needle occurs {$hits} time(s), wanted exactly 1 — [".$from.'].');
    }
    if ($from === $to) {
        $fail('pair '.($i + 1).' is a no-op by construction.');
    }
    if (str_contains($to, "\n")) {
        $fail('pair '.($i + 1).' would wrap a table row.');
    }
    $body = str_replace($from, $to, $body);
}

// The new screen's own sentence, appended to the same row before its next-phase cell.
$anchor = 'The falsifier’s own first run reported a BAD leg rather than a survivor';
if (substr_count($body, $anchor) !== 0) {
    // The changelog owns that wording; inside the matrix the row ends with the scholarship sentence.
    $anchor = 'and one leg failed on its own arithmetic first';
}
$sentence = 'The twenty-second table is the office directory (`ReferenceDataController::offices` serving '
    .'`Admin/ReferenceData/Offices.jsx`), and it is the first screen in the tranche whose real claim is an offer '
    .'WITHHELD: the page paints four columns and the query holds two, because the desk name is answered from '
    .'`App\Enums\OfficeId` after the rows come back and the three usage figures are grouped counts over other tables. '
    .'`only_the_two_columns_the_query_holds_are_offered_as_orders` asserts the offered set EXACTLY rather than merely '
    .'present, and the harness plants the inversion twice — once by handing the query a `workflowName` key it can only '
    .'answer with a different column, once by spelling a `sortKey` for `staffCount` in the page. A header over a '
    .'derived label is not a wrong sequence, it is a control the server cannot obey, which is §28.3’s filter defect '
    .'reached from the sort side. ';
$found = 0;
foreach (['The twenty-first table is the scholarship catalogue', $anchor] as $needle) {
    if (substr_count($body, $needle) === 1) {
        $found++;
    }
}
if ($found === 0) {
    $fail('no anchor for the new sentence — the row’s own prose moved and this pass would append an orphan.');
}
// Insert before the scholarship sentence so the newest screen reads last in the row's own chronology.
$body = str_replace('The twenty-first table is the scholarship catalogue',
    $sentence.'The twenty-first table is the scholarship catalogue', $body);

if (substr_count($body, '|') !== $pipesBefore) {
    $fail('the row gained or lost a cell separator ('.$pipesBefore.' before, '.substr_count($body, '|').' now).');
}
if (! str_contains($body, 'twenty-two of thirty-six tables use it')) {
    $fail('the re-taken Status cell did not land.');
}

if (! $apply) {
    printf("DRY RUN: %d pair(s) re-taken and one sentence drafted (%d bytes).\n", count($pairs), strlen($sentence));
    exit(0);
}
$w = @file_put_contents($root.'/'.$file, $body);
if ($w !== strlen($body)) {
    $fail('the write returned '.var_export($w, true).'.');
}
printf("written: %d pair(s) re-taken, one sentence added; %d bytes.\n", count($pairs), $w);
