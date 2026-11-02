<?php

/**
 * Re-points the markdown citations this batch aged, using ONLY the pairs `lane127-repoint-md-cites.php` matched
 * byte for byte between HEAD's copy of each touched file and the working one. The tool writes nothing, so this
 * script applies its readings; the needle list is that output, not arithmetic.
 *
 * Refuses when a needle does not occur the expected number of times, and refuses when a replacement already
 * occurs, so a half-applied re-point cannot look like a finished one.
 *
 * `Documentation/UI-Workflow-Conformance-Audit-2026-09-17.md` is deliberately NOT touched: it carries the
 * `Pointer currency` marker, so its stale numbers are dated records and re-typing them would state lines that
 * were never true on its own day (lane 72's rule, and the reason the gate prints them as DATED rather than as
 * problems).
 *
 * Usage: php storage/app/scratch/lane193-repoint-md.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
$apply = in_array('--apply', $argv ?? [], true);

$edits = [
    'AGENTS.md' => [
        ['EnrollmentReadiness.php:61-63', 'EnrollmentReadiness.php:62-64', 2],
        ['RegistrarController.php:211-223', 'RegistrarController.php:220-232', 1],
    ],
    'Documentation/DATABASE-CONCEPT-COMPLIANCE-ADS.md' => [
        ['RegistrarController.php:321', 'RegistrarController.php:330', 1],
    ],
    'Documentation/Prompt-Requirement-Matrix-2026-10-26.md' => [
        ['RegistrarController.php:304-326', 'RegistrarController.php:313-335', 1],
    ],
    'Documentation/REQUIREMENTS-TRACEABILITY-ADS.md' => [
        ['RegistrarController.php:97-106', 'RegistrarController.php:106-115', 1],
        ['RegistrarController.php:321', 'RegistrarController.php:330', 1],
        ['RegistrarController.php:368', 'RegistrarController.php:377', 1],
        ['EnrollmentReadiness.php:65', 'EnrollmentReadiness.php:66', 1],
        ['EnrollmentReadiness.php:133', 'EnrollmentReadiness.php:139', 1],
    ],
    'Documentation/TESTING-AND-VERIFICATION-ADS.md' => [
        ['RegistrarController.php:368', 'RegistrarController.php:377', 1],
    ],
];

$bad = [];
foreach ($edits as $file => $pairs) {
    $path = $root.'/'.$file;
    if (! is_file($path)) {
        $bad[] = "$file: not on disk";

        continue;
    }
    $bytes = (string) file_get_contents($path);
    foreach ($pairs as [$from, $to, $expect]) {
        $found = substr_count($bytes, $from);
        if ($found !== $expect) {
            $bad[] = "$file: needle ".strtr($from, ['|' => '/']).' occurs '.$found.' time(s), expected '.$expect;

            continue;
        }
        // A replacement that already occurs elsewhere cannot be post-checked by document-wide absence, so the
        // after-assertion is scoped: the old needle must be gone and the new one must have grown by exactly the
        // count this edit writes.
        $alreadyThere = substr_count($bytes, $to);
        $bytes = str_replace($from, $to, $bytes);
        $after = substr_count($bytes, $to);
        if ($after !== $alreadyThere + $expect) {
            $bad[] = "$file: replacement grew to {$after} from an expected ".($alreadyThere + $expect);

            continue;
        }
        printf("  %-52s %s -> %s  (%d, already %d)\n", basename($file), $from, $to, $expect, $alreadyThere);
    }
    if ($apply && $bad === []) {
        if (file_put_contents($path, $bytes) === false) {
            $bad[] = "$file: the write returned false";
        }
    }
}

if ($bad !== []) {
    fwrite(STDERR, "REFUSED: nothing more was written.\n  ".implode("\n  ", $bad)."\n");
    exit(1);
}
echo $apply ? "written.\n" : "DRY RUN — nothing written.\n";
