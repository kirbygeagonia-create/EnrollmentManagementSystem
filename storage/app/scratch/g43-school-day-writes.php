<?php

/**
 * G-43: every app-side write of a DATE column from `now()`, in one pass.
 *
 * lane 51's rule is that a day written in PHP is the school's civil day, read through SchoolTime, and lane 85
 * fixed the first three sites — but its checker reads the SHAPES `now()->toDateString()` and `whereDate(...)`,
 * so `'xDate' => now()` into a `date` column is invisible to it and answers 0. Eloquent formats a Carbon for
 * a `date` cast in the application's timezone, which is UTC, so anything between 16:00Z and midnight files the
 * day before the school's on the record the next desk reads.
 *
 * The site list is `lane87-date-write-columns.php`'s output, not a remembered set: seven writes across six
 * controllers. Three of them already import SchoolTime, so they change one line each and no citation into
 * those files ages; the other three gain one import line, and the caller re-points the document's pointers
 * into exactly those three.
 *
 * The import is spliced at the first `use Illuminate` line — where `App\Support` sorts — by OFFSET, because
 * str_replace would hit every Illuminate import.
 *
 * Usage: php storage/app/scratch/g43-school-day-writes.php [--apply]
 */
$root = dirname(__DIR__, 3);

$writes = [
    ['file' => 'app/Http/Controllers/Admission/AdmissionController.php', 'from' => "'submittedDate' => now(),", 'to' => "'submittedDate' => SchoolTime::today(),", 'times' => 2, 'import' => false],
    ['file' => 'app/Http/Controllers/Admission/AdmissionController.php', 'from' => "'uploadedDate' => now(),", 'to' => "'uploadedDate' => SchoolTime::today(),", 'times' => 1, 'import' => false],
    ['file' => 'app/Http/Controllers/Assessment/AssessmentController.php', 'from' => "'assessmentDate' => now(),", 'to' => "'assessmentDate' => SchoolTime::today(),", 'times' => 1, 'import' => true],
    ['file' => 'app/Http/Controllers/Clearance/ClearanceController.php', 'from' => "'approvalDate' => now(),", 'to' => "'approvalDate' => SchoolTime::today(),", 'times' => 1, 'import' => true],
    ['file' => 'app/Http/Controllers/ID/IDController.php', 'from' => "'requestDate' => now(),", 'to' => "'requestDate' => SchoolTime::today(),", 'times' => 1, 'import' => true],
    ['file' => 'app/Http/Controllers/Evaluation/EvaluationController.php', 'from' => "'formSignedDate' => now(),", 'to' => "'formSignedDate' => SchoolTime::today(),", 'times' => 1, 'import' => false],
    ['file' => 'app/Http/Controllers/Registrar/RegistrarController.php', 'from' => "'enrolledDate' => now(),", 'to' => "'enrolledDate' => SchoolTime::today(),", 'times' => 1, 'import' => false],
];

$apply = in_array('--apply', $argv, true);
$failures = [];
$plan = [];

foreach ($writes as $job) {
    $path = $root.'/'.$job['file'];
    if (! is_file($path)) {
        $failures[] = "{$job['file']}: no such file";

        continue;
    }
    $src = $plan[$path] ?? (string) file_get_contents($path);

    $hits = substr_count($src, $job['from']);
    if ($hits !== $job['times']) {
        $failures[] = "{$job['file']}: [".$job['from']."] occurs {$hits}, expected {$job['times']}";

        continue;
    }
    if ($job['import']) {
        if (str_contains($src, "use App\\Support\\SchoolTime;\n")) {
            $failures[] = "{$job['file']}: already imports SchoolTime — the job list is stale";

            continue;
        }
        $at = strpos($src, 'use Illuminate');
        if ($at === false) {
            $failures[] = "{$job['file']}: no Illuminate import to anchor the new use statement";

            continue;
        }
        $src = substr_replace($src, "use App\\Support\\SchoolTime;\n", $at, 0);
        echo 'import spliced    '.$job['file']." at byte {$at}\n";
    }

    $plan[$path] = str_replace($job['from'], $job['to'], $src);
    echo 'staged            '.$job['file'].': '.trim($job['from']).' -> '.trim($job['to'])."\n";
}

// Nothing may reference SchoolTime in a file the plan did not give an import to.
foreach ($plan as $path => $src) {
    if (str_contains($src, 'SchoolTime::today()') && ! str_contains($src, 'use App\\Support\\SchoolTime;')) {
        $failures[] = basename($path).': uses SchoolTime but imports nowhere — it would fatal at runtime';
    }
}

if ($failures !== []) {
    fwrite(STDERR, "REFUSED: nothing written.\n  ".implode("\n  ", $failures)."\n");

    exit(1);
}

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

foreach ($plan as $path => $src) {
    file_put_contents($path, $src);
    echo 'written           '.basename($path)."\n";
}

echo "\n".count($plan)." file(s) written; ".count($writes)." write sites moved to the school's day.\n";
