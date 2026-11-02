<?php

/**
 * Re-point every markdown pointer into the four files this batch moved, from the readings lane127 printed.
 *
 * The markdown gate flags only a pointer that lands on a BLANK line, so of the twenty-one numbers this batch aged
 * it reported three; the other eighteen sit on live lines that belong to a construct one row away — the wrong-target
 * shape this repository has met from the docx side many times and from the markdown side rarely.
 *
 * Each pair is a whole pointer string as the document spells it, with every number it carries resolved by reading
 * HEAD's bytes against the working file. Pointers are applied longest-needle-first, because `:275,283` is a prefix of
 * `:275,283,344` and a short replace would corrupt the longer one. Nothing is written unless every needle matches its
 * expected count exactly, so a moved document cannot end up half-repointed.
 *
 * Usage: php storage/app/scratch/lane210-repoint-g37-md.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);

$apply = in_array('--apply', array_slice($GLOBALS['argv'], 1), true);

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

/** file => list of [needle, replacement, expected count] */
$plan = [
    'AGENTS.md' => [
        ['bootstrap/app.php:140', 'bootstrap/app.php:143', 2],
        ['EnrollmentIssuer.php:133', 'EnrollmentIssuer.php:137', 1],
        ['EnrollmentIssuer.php:131', 'EnrollmentIssuer.php:135', 1],
    ],
    'Documentation/Prompt-Requirement-Matrix-2026-10-26.md' => [
        ['AuditLogObserver.php:94-101', 'AuditLogObserver.php:97-104', 1],
        ['UserManagementController.php:78-86,211-228', 'UserManagementController.php:79-87,212-229', 1],
        ['UserManagementController.php:275,283,344', 'UserManagementController.php:276,284,345', 1],
        ['UserManagementController.php:275,283', 'UserManagementController.php:276,284', 2],
        ['UserManagementController.php:277,285', 'UserManagementController.php:278,286', 1],
        ['UserManagementController.php:220,318', 'UserManagementController.php:221,319', 1],
        ['UserManagementController.php:126,190', 'UserManagementController.php:127,191', 1],
        ['UserManagementController.php:118,153', 'UserManagementController.php:119,154', 1],
        ['UserManagementController.php:116,182', 'UserManagementController.php:117,183', 1],
        ['AuditLogObserver.php:43', 'AuditLogObserver.php:44', 1],
        ['EnrollmentIssuer.php:39-41', 'EnrollmentIssuer.php:40-42', 1],
    ],
    'Documentation/DATABASE-CONCEPT-COMPLIANCE-ADS.md' => [
        ['EnrollmentIssuer.php:39-45', 'EnrollmentIssuer.php:40-46', 1],
        ['EnrollmentIssuer.php:109', 'EnrollmentIssuer.php:113', 1],
    ],
    'Documentation/REQUIREMENTS-TRACEABILITY-ADS.md' => [
        ['EnrollmentIssuer.php:39-45', 'EnrollmentIssuer.php:40-46', 1],
        ['EnrollmentIssuer.php:109', 'EnrollmentIssuer.php:113', 2],
        ['EnrollmentIssuer.php:58', 'EnrollmentIssuer.php:59', 1],
    ],
];

$bodies = [];
foreach ($plan as $file => $edits) {
    if (! is_file($file)) {
        $fail("{$file} is not in the tree.");
    }
    $bodies[$file] = (string) file_get_contents($file);
}

// Validate everything first. A document left half-re-pointed is a register whose rows disagree with each other,
// and the gate would then flag only the pointers that happen to hit a blank line.
foreach ($plan as $file => $edits) {
    foreach ($edits as [$from, $to, $expect]) {
        if ($from === $to) {
            $fail("{$file}: needle and replacement are the same text [{$from}] — the pair list has aged.");
        }
        $hits = substr_count($bodies[$file], $from);
        if ($hits !== $expect) {
            $fail("{$file}: [{$from}] occurs {$hits} time(s), expected {$expect}.");
        }
    }

    // A short pointer can be the head of a longer one in the same document — `:275,283` occurs twice here, once as
    // itself and once as the head of `:275,283,344` — which is why the plan is applied longest-needle-first and the
    // shorter row counts the head it will inherit. Assert that ordering for every pair that actually collides,
    // rather than demanding the whole plan be sorted by length (which would refuse two unrelated filenames).
    $order = array_column($edits, 0);
    foreach ($order as $i => $short) {
        foreach ($order as $j => $long) {
            if ($j > $i && str_starts_with($long, $short)) {
                $fail("{$file}: [{$short}] is applied before the longer pointer it heads, [{$long}].");
            }
        }
    }
}

foreach ($plan as $file => $edits) {
    $changed = 0;
    foreach ($edits as [$from, $to, $expect]) {
        $bodies[$file] = str_replace($from, $to, $bodies[$file]);
        $changed += $expect;
    }
    printf("%-58s %d pointer number(s) re-taken\n", $file, $changed);
}

if (! $apply) {
    echo "DRY RUN — nothing written.\n";
    exit(0);
}

foreach ($bodies as $file => $body) {
    if (file_put_contents($file, $body) === false) {
        $fail("writing {$file} failed.");
    }
}

// Read back: no retired pointer survives inside the pairs this run replaced, and the replacement grew by exactly
// the count asserted. Document-wide absence is not the test — the same digits legitimately belong to other files.
foreach ($plan as $file => $edits) {
    $after = (string) file_get_contents($file);
    foreach ($edits as [$from, $to, $expect]) {
        if (substr_count($after, $from) !== 0) {
            $fail("{$file}: [{$from}] still occurs after the write — the replacement did not land where it was counted.");
        }
        if (substr_count($after, $to) < $expect) {
            $fail("{$file}: [{$to}] occurs ".substr_count($after, $to)." time(s), wanted at least {$expect}.");
        }
    }
}

echo "written.\n";
