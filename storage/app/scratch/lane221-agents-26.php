<?php

/**
 * Insert the two AGENTS bullets for the twenty-fifth and twenty-sixth wired tables immediately BEFORE the
 * twenty-fourth table's bullet, so that bullet's indented continuation lines stay attached to it. A structural
 * insert anchored on the line below the intended gap is how this file has eaten a paragraph twice.
 */
$path = 'AGENTS.md';
$before = (string) file_get_contents($path);
$linesBefore = count(explode("\n", $before));

$anchor = "- **A hop is honest only when the key behind it is proven, and a compound label is not a column** (the twenty-fourth";
if (substr_count($before, $anchor) !== 1) {
    fwrite(STDERR, 'REFUSED: the twenty-fourth table bullet occurs '.substr_count($before, $anchor)." time(s), wanted 1\n");
    exit(1);
}

$bulletA = <<<'TXT'
- **A page's own copy of a value the server already answers is a second vocabulary, and a fixture whose two orders
coincide hides the mutant** (the twenty-fifth table, `Admin/ReferenceData/AdmissionRequirements`). The page held a
module-level `appliesToOptions` of five hand-typed applicant types beside the `Select` that filters that same
column, while `admissionRequirements()` already answered the enum — so a sixth type added to the column would appear
in the create form and be silently refused by the filter, and no gate in this repository reads a JSX list against a
PHP enum. Both controls now read the handler's prop and one case asserts the constant is gone, so the picker and the
filter cannot drift apart. The order half is ordinary (three stored columns, the ENUM sequence asked of
`tests/Support/EnumColumnOrder.php`), and both lessons came out of the falsifier instead of the screen: its first run
reported a SURVIVED leg on the Status header because the three fixtures were named so that `requirementName` order
equalled flag order — lane 91's "re-pick the fixture so the target order disagrees with both id orders", met from the
column-pair side rather than the id side — and its multi-plant helper used `$originals` without importing it, so the
landing check compared against null and warned on every leg while still printing *killed*. A guard that never fires is
the same defect as a checker that reads the wrong construct, and this time it was five lines from the code it guards.
TXT;

$bulletB = <<<'TXT'
- **A screen's opening order is a claim about what the desk sees first, and an order over a nullable column may not
become a filter** (the twenty-sixth table, `Admin/ReferenceData/ClearanceRequirements`). Its Office header is a hop
through a key the migration declares NOT NULL with a foreign key and live `ems` answers with 9 requirements, 0 nulls,
9 distinct offices and no orphan, so INNER is proven rather than copied from the last screen — and making that hop the
fallback replaced the seeder's numbering (Registrar, Accounting, Scholarship…) with the office name (Academic
Department first). Both sequences were read off the live table before the sentence was written, because a default is a
behaviour change and the register should say which one a desk now sees; the case pins the payload's applied column,
the direction and the sequence, and a harness leg flips the default to descending. The screen's other claim is about
an absence: `requirementName` is nullable and the page paints a warning in its place, so a case writes one unworded
requirement and asserts it survives the order that reads it. That class's own first run found the assertion wrong, not
the code: it demanded `officeId` be ABSENT from the row, but the key is the requirement's own column, which the edit
form seeds itself from — G-51's pin means the hop adds nothing, not that the row loses what it already had.
TXT;

$after = str_replace($anchor, $bulletA."\n".$bulletB."\n".$anchor, $before);
$linesAfter = count(explode("\n", $after));
// The expectation is derived, not remembered: the replacement adds A's own newlines, one joining newline, B's own
// newlines and one more before the anchor — which is exactly lines(A) + lines(B).
$expectedGrowth = count(explode("\n", $bulletA)) + count(explode("\n", $bulletB));
if ($linesAfter !== $linesBefore + $expectedGrowth) {
    fwrite(STDERR, 'REFUSED: the file grew by '.($linesAfter - $linesBefore)." lines, wanted {$expectedGrowth}\n");
    exit(1);
}
foreach (['twenty-fifth table', 'twenty-sixth table'] as $needle) {
    if (substr_count($after, $needle) < 1) {
        fwrite(STDERR, "REFUSED: neither bullet names the {$needle}\n");
        exit(1);
    }
}
if (preg_match('/[A-Za-z0-9_]+\.(php|jsx|js|json):\d/', $bulletA.$bulletB) === 1) {
    fwrite(STDERR, "REFUSED: a bullet carries a citation-shaped token this pass cannot verify\n");
    exit(1);
}
file_put_contents($path, $after);
echo "written: two bullets inserted above the twenty-fourth table's bullet; line count {$linesBefore} -> {$linesAfter}\n";
