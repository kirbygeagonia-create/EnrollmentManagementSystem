<?php

/**
 * Re-cut the matrix's two §10 privacy rows.
 *
 * A hand edit that replaced only the HEAD of one of these rows left the old body appended to the new one, so the row
 * carried eight pipes' worth of cells and both the retired claim and its replacement. This writes each row whole,
 * from the current file, and refuses unless every row has exactly five cells.
 *
 * The redaction pattern's own name is spelled in prose rather than quoted as `/password|hash|token|secret/i`: a pipe
 * inside a markdown table cell is the shape lane 69 records as a mis-split, and the exact literal lives in
 * `app/Observers/AuditLogObserver.php:43`, which the row cites.
 *
 * Usage: php storage/app/scratch/fix-matrix-privacy-rows.php [--apply]
 */
$root = dirname(__DIR__, 3);
$file = $root.'/Documentation/Prompt-Requirement-Matrix-2026-10-26.md';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$bytes = (string) file_get_contents($file);
$lines = explode("\n", $bytes);

$rows = [
    396 => '| 10 | Never log or leak passwords, tokens, or unnecessary sensitive records | Working (G-48, closed 2026-10-29) | Write-time redaction is deliberate and works: `app/Observers/AuditLogObserver.php:43` holds the `SENSITIVE_KEY_PATTERN` list that keeps password, hash, token and secret keys out of `auditlogs.newValue`. What this row recorded was that `app/Models/Students.php` declared no hidden-attributes list where `app/Models/Staffusers.php:30-33` hides the credential hash, while `app/Http/Controllers/StudentController.php:117` passes the whole model to Student 360 — so a bcrypt student credential hash travelled in every payload any `students.view` account could open. `app/Models/Students.php:25-27` now hides `passwordHash` on that sibling precedent; nothing in `app/` reads the column after the fact (every hit is a write: intake, reset, import, admin create), so hiding it is safe. `students.username` still travels, as it does on the staff model — a login handle is not the secret this row was about. Pinned by `tests/Feature/Student/Student360PrivacyTest.php`, which asserts the hash is absent for two different desks while the column is still asserted to hold one, and proved by `storage/app/scratch/mutate-g48-g49-privacy-tests.php`. → **G-48** |',
    397 => '| 10 | Minimise what offices share — a desk needs the clinic *outcome*, not the notes | Working for the notes (G-49, closed 2026-10-29); how far the gate reaches waits on the school | The clearance path is clean — no Clinic read exists in the clearance or registrar controllers. What this row recorded was that `StudentController::show()` eager-loaded `enrollments.clinicrecords` for every Student 360 while `resources/js/Pages/Students/Show.jsx:506` and `resources/js/Pages/Students/Show.jsx:518-519` rendered the card including each record\'s findings text, and nothing asked whether the requester may see clinical notes — the prompt\'s own worked example of what must not be shared. The load now sits inside a clinic-right test at `app/Http/Controllers/StudentController.php:112-114`, so a non-holder\'s payload carries no clinical key at all and the section has nothing to print; the case is directional in both directions, the clinic still receiving its findings. Whether Guidance or Advising reads notes as well is item 3 on the 2026-10-29 sheet, and the privacy-programme question it belongs to is registered as G-38. → **G-49** |',
];

foreach ($rows as $number => $new) {
    $current = $lines[$number - 1] ?? null;
    if ($current === null) {
        fwrite(STDERR, "REFUSED: the file has no line {$number}.\n");

        exit(1);
    }
    $head = $number === 396 ? '| 10 | Never log or leak passwords' : '| 10 | Minimise what offices share';
    if (! str_starts_with($current, $head)) {
        fwrite(STDERR, "REFUSED: line {$number} does not start with the row this pass re-cuts: [{$head}]\n");

        exit(1);
    }
    printf("line %d: was %d cell-separators, becomes %d\n", $number, substr_count($current, '|'), substr_count($new, '|'));
    $lines[$number - 1] = $new;
}

$headerNumber = 389;
$header = $lines[$headerNumber - 1] ?? '';
if (! str_starts_with($header, '| § | Requirement')) {
    fwrite(STDERR, "REFUSED: line {$headerNumber} is not the table header this pass derives its width from: [".mb_substr($header, 0, 40)."]\n");

    exit(1);
}
// The width is read from the header, never recalled: a row written to a remembered column count is how a table
// starts disagreeing with itself the day someone adds a column.
$wantCells = count(explode('|', $header)) - 2;
printf("table header at line %d declares %d column(s)\n", $headerNumber, $wantCells);

foreach ($rows as $number => $new) {
    $cells = count(explode('|', $new)) - 2;
    if ($cells !== $wantCells) {
        fwrite(STDERR, "REFUSED: the new row at line {$number} has {$cells} cells, not {$wantCells}.\n");

        exit(1);
    }
    if (str_contains($new, 'PARTLY')) {
        fwrite(STDERR, "REFUSED: the new row at line {$number} still carries the retired verdict word.\n");

        exit(1);
    }
}

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/matrix-privacy-rows-'.date('Ymd-His').'.bak';
if (! copy($file, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup.\n");

    exit(1);
}

$out = implode("\n", $lines);
if (strlen($out) === strlen($bytes)) {
    fwrite(STDERR, "REFUSED: the rewrite is byte-identical to the file — nothing was changed.\n");

    exit(1);
}
file_put_contents($file, $out);

$again = explode("\n", (string) file_get_contents($file));
foreach ($rows as $number => $new) {
    if ($again[$number - 1] !== $new) {
        fwrite(STDERR, "REFUSED: line {$number} did not read back as what was written. Restore ".basename($backup).".\n");

        exit(1);
    }
}

echo 'APPLIED: both rows re-cut whole; backup '.basename($backup)."\n";
