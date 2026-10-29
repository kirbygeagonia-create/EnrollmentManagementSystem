<?php

/**
 * G-43: the last three DATE columns written from the machine's civil day.
 *
 * lane 51's rule says a day filed in PHP is the school's civil day, read from SchoolTime, and lane 85 fixed
 * the first three sites — but its checker reads the *shapes* `now()->toDateString()` and `whereDate(...)`, so a
 * write of `'xDate' => now()` into a `date` column is invisible to it. Eloquent formats a `date` cast in the
 * application's timezone, which is UTC, so anything between 16:00Z and midnight files the day before.
 *
 * Each replacement is asserted to occur a known number of times before writing, and every file is linted by
 * the caller afterwards. The two `submittedDate` sites in one controller are the same defect on the same
 * column of the same table, so both move together.
 *
 * Usage: php storage/app/scratch/g43-school-day-writes.php [--apply]
 */
$root = dirname(__DIR__, 3);

$jobs = [
    [
        'file' => 'app/Http/Controllers/Clearance/ClearanceController.php',
        'edits' => [
            ["                'approvalDate' => now(),\n", "                'approvalDate' => SchoolTime::today(),\n", 1],
        ],
    ],
    [
        'file' => 'app/Http/Controllers/ID/IDController.php',
        'edits' => [
            ["use App\\Models\\Idrequests;\n", "use App\\Models\\Idrequests;\nuse App\\Support\\SchoolTime;\n", 1],
            ["            'requestDate' => now(),\n", "            'requestDate' => SchoolTime::today(),\n", 1],
        ],
    ],
    [
        'file' => 'app/Http/Controllers/Admission/AdmissionController.php',
        'edits' => [
            ["                'submittedDate' => now(),\n", "                'submittedDate' => SchoolTime::today(),\n", 2],
        ],
    ],
    [
        'file' => 'app/Http/Controllers/Assessment/AssessmentController.php',
        'edits' => [
            // Reported, not rewritten: this site was converted earlier in the batch, so the old text must
            // occur zero times. A guard that expects to find it would refuse the whole run for the wrong reason.
            ["                'assessmentDate' => now(),\n", null, 0],
        ],
    ],
];

$apply = in_array('--apply', $argv, true);
$failures = [];
$plan = [];

foreach ($jobs as $job) {
    $path = $root.'/'.$job['file'];
    if (! is_file($path)) {
        $failures[] = "{$job['file']}: no such file";

        continue;
    }
    $src = (string) file_get_contents($path);
    foreach ($job['edits'] as [$from, $to, $expect]) {
        $hits = substr_count($src, $from);
        if ($hits !== $expect) {
            $failures[] = "{$job['file']}: [".trim($from)."] occurs {$hits}, expected {$expect}";

            continue;
        }
        if ($to === null) {
            echo "already done  {$job['file']}: ".trim($from)."\n";

            continue;
        }
        if (str_contains($src, 'use App\\Support\\SchoolTime;') === false && ! str_starts_with($from, 'use App')) {
            $failures[] = "{$job['file']}: SchoolTime is imported nowhere — the write would fatal";
        }
        $src = str_replace($from, $to, $src);
        echo 'staged        '.$job['file'].': '.trim($from).' -> '.trim($to)."\n";
    }
    $plan[$path] = $src;
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
    echo 'written       '.basename($path)."\n";
}
