<?php

/**
 * Re-points the markdown citations a batch aged, using ONLY the pairs `lane127-repoint-md-cites.php` matched byte
 * for byte between HEAD's copy of each touched file and the working one. That tool writes nothing, so this one
 * applies its readings; the needle list is the output of a measurement, never arithmetic.
 *
 * Two guards, both bought by failures elsewhere in this repository:
 *  - every item is VALIDATED before ANY file is written, so a refusal in the last document cannot leave a
 *    half-moved register behind in the first four (a needle that already exists elsewhere cannot be post-checked
 *    by document-wide absence either, so the after-check is scoped to the pair the edit replaced);
 *  - a needle whose replacement is its own text is refused, because both count guards pass by construction and the
 *    run would print `written.` having changed nothing.
 *
 * The file list is the pair of the citation gate's own scope minus the dated documents: a file carrying the
 * `Pointer currency` marker keeps its stale numbers as that day's record, and re-typing them would state lines
 * that were never true on its own day.
 *
 * Usage: php storage/app/scratch/lane193-repoint-md.php [--apply] [--fixture=<dir>]
 *   --fixture=<dir> redirects the file list into a fixture directory so the harness can plant its own needles
 *   instead of measuring a tree whose needles a previous run already consumed.
 */
$root = dirname(__DIR__, 3);
chdir($root);

$apply = false;
$fixtureDir = null;
foreach (array_slice($argv ?? [], 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } elseif (str_starts_with($arg, '--fixture=')) {
        $fixtureDir = substr($arg, strlen('--fixture='));
    } else {
        fwrite(STDERR, 'REFUSED: this tool does not understand '.escapeshellarg($arg).".\n");
        exit(1);
    }
}

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
$planned = [];
foreach ($edits as $file => $pairs) {
    $path = $root.($fixtureDir !== null ? '/'.$fixtureDir : '').'/'.$file;
    if (! is_file($path)) {
        $bad[] = "$file: not on disk at ".str_replace($root.'/', '', $path);

        continue;
    }
    $bytes = (string) file_get_contents($path);
    foreach ($pairs as [$from, $to, $expect]) {
        if ($from === $to) {
            $bad[] = "$file: needle and replacement are identical (".strtr($from, ['|' => '/']).') — a no-op is not a re-point';

            continue;
        }
        $found = substr_count($bytes, $from);
        if ($found !== $expect) {
            $bad[] = "$file: needle ".strtr($from, ['|' => '/'])." occurs {$found} time(s), expected {$expect}";

            continue;
        }
        $alreadyThere = substr_count($bytes, $to);
        $after = substr_count(str_replace($from, $to, $bytes), $to);
        if ($after !== $alreadyThere + $expect) {
            $bad[] = "$file: replacement would grow to {$after}, an expected ".($alreadyThere + $expect);

            continue;
        }
        printf("  %-52s %s -> %s  (%d, already %d)\n", basename($file), $from, $to, $expect, $alreadyThere);
    }
    $planned[$file] = [$path, $bytes];
}

if ($bad !== []) {
    fwrite(STDERR, "REFUSED: nothing was written.\n  ".implode("\n  ", $bad)."\n");
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — nothing written.\n";
    exit(0);
}

foreach ($planned as $file => [$path, $bytes]) {
    $next = $bytes;
    foreach ($edits[$file] as [$from, $to, $expect]) {
        $next = str_replace($from, $to, $next);
    }
    if (file_put_contents($path, $next) === false) {
        fwrite(STDERR, "REFUSED: the write to {$file} returned false — the file may be open elsewhere.\n");
        exit(1);
    }
}
echo "written.\n";
