<?php

/**
 * Lane 55 — print the brace span of every method in the two files the docx cites, so §17's evidence cells can be
 * re-pointed by meaning rather than by arithmetic.
 *
 * Read-only. Usage: php storage/app/scratch/lane55-method-spans.php [file...]
 */

$root = dirname(__DIR__, 3);
$files = array_slice($argv, 1);
$files = $files === [] ? [
    'app/Http/Controllers/Blocking/BlockingController.php',
    'app/Console/Commands/AuditRecordStates.php',
] : $files;

foreach ($files as $rel) {
    $lines = array_map(fn (string $l): string => rtrim($l, "\r\n"), (array) file($root.'/'.$rel));
    echo "== {$rel} (".count($lines).' lines) ==';
    echo "\n";
    foreach ($lines as $i => $line) {
        if (preg_match('/^\s*(?:public|private|protected)\s+(?:static\s+)?function\s+(\w+)\(/', $line, $m) === 1) {
            $depth = 0;
            $opened = false;
            $end = $i;
            for ($j = $i; $j < count($lines); $j++) {
                $depth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');
                if ($depth > 0) {
                    $opened = true;
                }
                if ($opened && $depth === 0) {
                    $end = $j;
                    break;
                }
            }
            printf("  :%-4d-%-4d %s()\n", $i + 1, $end + 1, $m[1]);
            $i = $end;
        }
    }
    echo "\n";
}

echo "== anchors the prose names ==\n";
$bl = array_map(fn (string $l): string => rtrim($l, "\r\n"), (array) file($root.'/app/Http/Controllers/Blocking/BlockingController.php'));
foreach ([
    'the eligibility filter over the block roster' => '/->whereNotIn\(\'enrollmentId\'/',
    'per-row enrolled guard' => '/enrollmentStatus !== EnrollmentStatus::Enrolled/',
    'next uncompleted box is Blocking' => '/workflowsteps\(\)->where\(\'stepStatus\'/',
    'block cap' => '/currentEnrolled \+ \$requestedCount > \$block->maxStudents/',
    'room cap' => '/roomEnrolled \+ \$requestedCount > \$room->capacity/',
    'sets scheduleStatus final' => '/scheduleStatus = [\'"]final/',
    'signed as part of the assignment' => '/OfficeId::Blocking->value\s*\)?;|signStepByOffice/',
] as $what => $re) {
    $hits = [];
    foreach ($bl as $i => $l) {
        if (preg_match($re, $l) === 1) {
            $hits[] = $i + 1;
        }
    }
    printf("  %-38s %s\n", $what, $hits === [] ? 'NOT FOUND' : implode(', ', $hits));
}
