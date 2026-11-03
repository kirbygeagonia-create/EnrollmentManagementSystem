<?php

/**
 * Build refresh-docx-pass423.php from pass 422's machinery, replacing only what this batch changes: the guard's
 * action name, the §1.1 row's text, the clone anchor (the newest row is now the twenty-fourth's), the three
 * §28.3 count pairs, the read-back anchors and the backup file names.
 *
 * Spans are cut on their own delimiters and every anchor is asserted to occur exactly once, because a needle that
 * matches twice writes a half-applied register — the defect this file has refused since pass 247.
 */
$src = 'storage/app/scratch/refresh-docx-pass422.php';
$dst = 'storage/app/scratch/refresh-docx-pass423.php';
$s = (string) file_get_contents($src);

$fail = static function (string $why): never {
    fwrite(STDERR, "REFUSED: {$why}\n");
    exit(1);
};

$replaceSpan = static function (string &$body, string $fromMarker, string $toMarker, string $new) use ($fail): void {
    $a = strpos($body, $fromMarker);
    if ($a === false || substr_count($body, $fromMarker) !== 1) {
        $fail("start anchor [{$fromMarker}] occurs ".substr_count($body, $fromMarker).' time(s), wanted 1');
    }
    $b = strpos($body, $toMarker, $a);
    if ($b === false) {
        $fail("end anchor [{$toMarker}] not found after the start");
    }
    $b += strlen($toMarker);
    $body = substr_replace($body, $new, $a, $b - $a);
};

$one = static function (string &$body, string $from, string $to) use ($fail): void {
    $n = substr_count($body, $from);
    if ($n !== 1) {
        $fail("needle occurs {$n} time(s), wanted exactly 1: [{$from}]");
    }
    $body = str_replace($from, $to, $body);
};

// ---------------------------------------------------------------- 1. guard: the action this batch grew
$one($s, "str_contains((string) \$x, 'public function blocks(')", "str_contains((string) \$x, 'public function clearanceRequirements(')");
$one($s, "the blocks() action was not found", "the clearanceRequirements() action was not found");

// ---------------------------------------------------------------- 2. the §1.1 row's own text
$clauses = <<<'PHPTEXT'
$opening = 'The twenty-fifth and twenty-sixth list tables sort, and one of them changed what a desk sees first.';
$clauses = [
    $opening,
    "The intake requirement catalogue — `ReferenceDataController::admissionRequirements` serving `Admin/ReferenceData/AdmissionRequirements.jsx` — offers Name, Applies To and Status. Its Applies To order is an ENUM, so the sequence is asked of the connection rather than typed into the case. What that screen produced is not a sorting finding: the page carried a module-level list of five hand-typed applicant types beside a picker on the same field, while the handler already answered the enum, so a sixth value added to the column would appear in the form and be silently refused by the filter. One case now asserts the constant is gone, the picker is built from the server's own prop, and the choices the payload answers equal the choices the model declares.",
    "Its falsifier taught two lessons the screen did not. Its first run reported a surviving leg on the Status header, and the fixture was the defect: the three requirements were named so that sorting by name produced exactly the sequence sorting by the flag produced, so the mutant satisfied the assertion and the comment claiming the two orders differed was false as written. The same run warned on every leg that a helper used a variable it had never imported, which means the plant's own did-it-reach-the-disk check compared against null and would have reported killed whether the write landed or not — the guard that never fires, inside my own instrument, five lines from the code it guards.",
    "The clearance checklist catalogue — `ReferenceDataController::clearanceRequirements` serving `Admin/ReferenceData/ClearanceRequirements.jsx` — paints two columns, one of them an office name one hop away through a key the migration declares NOT NULL with a foreign key and live `ems` answers with nine requirements, zero nulls, nine distinct offices and no orphan, so an INNER hop cannot drop a row from an order. Making that hop the opening order changed the sequence the Registrar sees first: the screen had ordered by the office's own numbering (Registrar, Accounting, Scholarship…), while the name order puts Academic Department first, and both sequences were read off the live table before this sentence was written. The change is therefore pinned by a case rather than mentioned in prose.",
    "The same screen's other claim is about an absence. Its requirement wording column is nullable and the page paints a warning in its place, so a case writes one unworded requirement and asserts it stays on the list its own header arranged — an order over a nullable column may not quietly become a filter on the very row the desk exists to finish. That class's own first run found the assertion wrong rather than the code: it demanded the office key be ABSENT from the row, but the key is the requirement's own column, which the edit form seeds itself from, so the claim now is that it stays.",
    "Both screens carry their own case file and their own falsifier, and each prints its own total, so none is written here. No pointer aged, and that is asserted rather than assumed: this pass collects every citation into the changed controller out of the flat dump, takes the highest, and refuses if it sits at or below the line the batch grew. The register's counts were written by `storage/app/scratch/lane140-measure-wired-screens.php`, which named four stale claims in the requirement matrix and three here and refused until each equalled the tree; the dated §1.1 rows it reported as records were left as written. The plan for the ten screens still owed is derived by `storage/app/scratch/lane215-plan-remaining-screens.php`, which resolves each page to its action, its model and that table's columns and compares its list against this instrument's page for page, because a count-only agreement would still pass if two screens swapped. `lane46-filter-contract.php` reads the page-to-handler contract after both echoes joined the filters, and its own harness answers in full.",
];
PHPTEXT;
$replaceSpan($s, "\$opening = 'The twenty-fourth list table sorts", "\$body = implode(' ', \$clauses);", $clauses."\n".'$body = implode(\' \', $clauses);');

// ---------------------------------------------------------------- 3. clone anchor + read-back anchors
$one($s, "\$rows = \$rowsForTitle(\$xml, 'The twenty-third list table sorts');",
        "\$rows = \$rowsForTitle(\$xml, 'The twenty-fourth list table sorts');");
$one($s, "\$newRows = \$rowsForAnchor(\$read, \$date.' The twenty-fourth list table sorts');",
        "\$newRows = \$rowsForAnchor(\$read, \$date.' The twenty-fifth and twenty-sixth list tables sort');");
$one($s, "count(\$rowsForTitle(\$read, 'The twenty-third list table sorts')) === 1\n        && count(\$rowsForTitle(\$read, 'The twentieth and twenty-first catalogue tables sort')) === 1",
        "count(\$rowsForTitle(\$read, 'The twenty-fourth list table sorts')) === 1\n        && count(\$rowsForTitle(\$read, 'The twenty-third list table sorts')) === 1");

// ---------------------------------------------------------------- 4. the three living counts
$pairs = <<<'PHPPAIRS'
$pairs = [
    ['twenty-four of the thirty-six tables that render the shared component', 'twenty-six of the thirty-six tables that render the shared component'],
    ['12 of the 36 pages that use the shared table still offer no order', '10 of the 36 pages that use the shared table still offer no order'],
    ['the twenty-four wired pages call', 'the twenty-six wired pages call'],
];
PHPPAIRS;
$replaceSpan($s, '$pairs = [', "['the twenty-three wired pages call', 'the twenty-four wired pages call'],\n];", $pairs);

// ---------------------------------------------------------------- 5. this pass's own file names
$one($s, "pass422-dryrun-backup.docx", "pass423-dryrun-backup.docx");
$one($s, "pass422-engine-backup.docx", "pass423-engine-backup.docx");
$one($s, "$root.'/storage/app/scratch/pass422-backup-'", "$root.'/storage/app/scratch/pass423-backup-'");
$one($s, "three counts re-taken", "three counts re-taken (24 to 26 wired, 12 to 10 owed)");

file_put_contents($dst, $s);
echo "built {$dst}; ";
echo 'anchors replaced: guard, row text, clone title, read-back titles, 3 pairs, 3 file names'."\n";
