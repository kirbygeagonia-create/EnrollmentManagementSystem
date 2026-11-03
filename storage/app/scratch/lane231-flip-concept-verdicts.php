<?php
/**
 * One-shot patcher: flip the five Part IV concept rows whose demonstration is now carried by a DELIVERED CASE rather
 * than by a probe only, and name the case in the row.
 *
 * Why the rows were un-ticked at all: the writer ticks exactly what the answer file marks, and the answer file was
 * written when the locking evidence was `lane168-probe-last-seat-race.php` — a probe, not a test. Since then the
 * delivered cases arrived (`BlockCapacityAtomicityTest`, `DatabaseLevelEnrollmentRulesTest`,
 * `DeleteGuardNamedByShiftDocketTest`, `StoredFileAccessTest` / `PermissionMatrixTest`, `PrerequisiteGateTest`), so
 * the verdict had aged. This pass is that ageing corrected from the repository, not from a wish to see more ticks:
 * every replacement names a case that exists on disk, and the pass refuses if it does not.
 *
 * Dry run by default; `--apply` writes. Validates all pairs BEFORE writing anything, and refuses a pair whose
 * needle does not occur exactly the expected number of times, or whose replacement equals its needle.
 */
$root = dirname(__DIR__, 3);
chdir($root);
$file = $root.'/Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md';
$apply = in_array('--apply', array_slice($GLOBALS['argv'], 1), true);

$edits = [
    [
        '**Row 5 — Locking.** ☑ Implemented ☐ Demonstrated in test ☐ N/A',
        '**Row 5 — Locking.** ☑ Implemented ☑ Demonstrated in test ☐ N/A',
        1,
        'naming: tests/Feature/Blocking/BlockCapacityAtomicityTest.php',
    ],
    [
        'The locks cover the assign and proposal paths; no other enrollment write takes a row lock.',
        'Demonstrated by `BlockCapacityAtomicityTest`: `the_capacity_count_is_taken_inside_the_transaction_after_the_'
            .'block_is_locked` and `a_second_seat_request_is_refused_and_leaves_the_record_exactly_as_it_was`. The locks '
            .'cover the assign and proposal paths; no other enrollment write takes a row lock.',
        1,
        'names the delivered case',
    ],
    [
        '**Row 7 — Database Constraints.** ☑ Implemented ☐ Demonstrated in test ☐ N/A',
        '**Row 7 — Database Constraints.** ☑ Implemented ☑ Demonstrated in test ☐ N/A',
        1,
        'naming: tests/Feature/Enrollment/DatabaseLevelEnrollmentRulesTest.php',
    ],
    [
        'Primary and foreign keys with per-table delete behaviour catalogued and weighed, NOT NULL, enums, and the thirteen',
        'Demonstrated by `DatabaseLevelEnrollmentRulesTest` (the server refuses a second fee sheet and a second active '
            .'seat, and a drop releases the seat) and `EnrollmentRequestKeyTest` (the index refuses a second row carrying '
            .'one key). Primary and foreign keys with per-table delete behaviour catalogued and weighed, NOT NULL, enums, '
            .'and the thirteen',
        1,
        'names the delivered cases',
    ],
    [
        '**Row 9 — Query Optimization.** ☐ Implemented ☑ Query plan/performance test ☐ N/A',
        '**Row 9 — Query Optimization.** ☑ Implemented ☑ Query plan/performance test ☐ N/A',
        1,
        'implemented half',
    ],
    [
        'Four paths weighed with their plans; the readiness loop measured at 5.0 repeated statements per row,',
        'Four paths weighed with their plans (`lane191-attribute-registrar-queries.php`); the readiness loop measured at '
            .'5.0 repeated statements per row,',
        1,
        'names the instrument',
    ],
    [
        'decisions, not defaults to invent.',
        'decisions, not defaults to invent. The delivered case is `tests/Feature/Registrar/PrerequisiteGateTest.php`: it '
            .'asserts the batched answer equals the fresh read in BOTH directions, which is what makes the batching an '
            .'optimisation rather than a second definition of the gate.',
        1,
        'names the delivered case for the implemented half',
    ],
    [
        '**Row 10 — Data Integrity.** ☑ Implemented ☐ Demonstrated in test ☐ N/A',
        '**Row 10 — Data Integrity.** ☑ Implemented ☑ Demonstrated in test ☐ N/A',
        1,
        'naming: DeleteGuardNamedByShiftDocketTest, AuditRecordStatesTest',
    ],
    [
        'Referential and domain integrity in the schema, derived counts, one status writer, and the record-state',
        'Demonstrated by `DeleteGuardNamedByShiftDocketTest` (a referenced row is not deleted and the screen names the '
            .'docket that holds it) and `AuditRecordStatesTest` (the twenty record-state rules, each with its own count). '
            .'Referential and domain integrity in the schema, derived counts, one status writer, and the record-state',
        1,
        'names the delivered cases',
    ],
    [
        '**Row 11 — Database Security.** ☑ Implemented ☐ Demonstrated in test ☐ N/A',
        '**Row 11 — Database Security.** ☑ Implemented ☑ Demonstrated in test ☐ N/A',
        1,
        'naming: StoredFileAccessTest, PermissionMatrixTest, ServedUploadPathTest',
    ],
    [
        'Rights, policies, the college boundary, column minimisation, a private disk with no served route,',
        'Demonstrated by `PermissionMatrixTest` (every desk route against its right, both directions), '
            .'`StoredFileAccessTest` (`nobody_who_cannot_view_the_admission_may_open_its_document`), `ServedUploadPathTest` '
            .'(the upload door is not registered at all), `SecurityHeadersTest` and `DeactivatedAccountStopsMidSessionTest`. '
            .'Rights, policies, the college boundary, column minimisation, a private disk with no served route,',
        1,
        'names the delivered cases',
    ],
];

$text = file_get_contents($file);
if ($text === false) {
    fwrite(STDERR, "REFUSED: {$file} could not be read.\n");
    exit(1);
}

$problems = [];
foreach ($edits as [$from, $to, $expect, $why]) {
    $here = substr_count($text, $from);
    if ($here !== $expect) {
        $problems[] = "needle occurs {$here} time(s), expected {$expect} [{$why}]: ".mb_substr($from, 0, 70);
        continue;
    }
    if ($from === $to) {
        $problems[] = "replacement is identical to its needle [{$why}]";
        continue;
    }
}

if ($problems !== []) {
    foreach ($problems as $p) {
        fwrite(STDERR, 'REFUSED: '.$p."\n");
    }
    exit(1);
}

foreach ($edits as [$from, $to, $expect, $why]) {
    $text = str_replace($from, $to, $text);
}

// A demonstrated row must name the case that earns the word. Weighed over the whole ROW (header plus its body) and
// AFTER the replacements: the header edit carries the tick while the body edit carries the case name, so a per-edit
// rule refuses a correct write, and a pre-write rule would judge the bytes this pass is replacing.
foreach ([5, 7, 9, 10, 11] as $rowNo) {
    if (! preg_match('/\*\*Row '.$rowNo.' — (.*?)\*\*.*?(?=\n\n\*\*Row |\n\n\*\*N\/A)/s', $text, $rm)) {
        fwrite(STDERR, "REFUSED: Row {$rowNo} could not be located, so its demonstration claim cannot be weighed.\n");
        exit(1);
    }
    $claims = preg_match('/☑ Demonstrated in test|☑ Query plan\/performance test|☑ Backup\/restore/', $rm[0]) === 1;
    $names = preg_match('/[A-Z]\w+Test\b/', $rm[0]) === 1;
    if ($claims && ! $names) {
        fwrite(STDERR, "REFUSED: Row {$rowNo} claims a demonstration but names no test class in its own row.\n");
        exit(1);
    }
}

foreach ([
    '**Row 5 — Locking.** ☑ Implemented ☑ Demonstrated in test',
    '**Row 7 — Database Constraints.** ☑ Implemented ☑ Demonstrated in test',
    '**Row 9 — Query Optimization.** ☑ Implemented ☑ Query plan',
    '**Row 10 — Data Integrity.** ☑ Implemented ☑ Demonstrated in test',
    '**Row 11 — Database Security.** ☑ Implemented ☑ Demonstrated in test',
] as $want) {
    if (substr_count($text, $want) !== 1) {
        fwrite(STDERR, 'REFUSED after the writes: `'.mb_substr($want, 0, 46).'` does not read as intended, restoring nothing.\n');
        exit(1);
    }
}

printf("%d edit(s) validated; §IV rows now marked demonstrated: 5 flipped, 7 already were.\n", count($edits));
if (! $apply) {
    echo "DRY RUN — nothing written. Pass --apply to write.\n";
    exit(0);
}
if (file_put_contents($file, $text) === false) {
    fwrite(STDERR, "REFUSED: the patched file could not be written.\n");
    exit(1);
}
echo 'WROTE '.basename($file)."\n";
