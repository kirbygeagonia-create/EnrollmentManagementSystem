<?php

/**
 * P05 fixture repair: every payload that posts `evaluation.profile.capture` must carry the version of the student it
 * reads, because the controller now compares it instead of overwriting silently.
 *
 * This is a script rather than hand edits because the sites are seven and one file holds four of them: a per-file
 * anchor is asserted to match an expected COUNT before anything is written, so a drift in the fixtures produces a
 * refusal instead of a half-repaired tree. Only profile payloads are touched — `formIssuedDate` also appears inside
 * `Enrollments::create` arrays (RetentionGateTest lines 154 and 381) and inside an assertion (ProfileCaptureTest
 * line 238), and adding a student column to those would be the wrong-target defect this repository already names.
 *
 * Dry run by default; --apply writes.
 */

$root = dirname(__DIR__, 3);
$apply = in_array('--apply', $GLOBALS['argv'], true);

$edits = [
    [
        'file' => 'tests/Feature/Evaluation/ProfileCaptureTest.php',
        'from' => "            'formIssuedDate' => now()->toDateString(),\n        ], \$overrides);",
        'to' => "            'formIssuedDate' => now()->toDateString(),\n            'rowVersion' => \$this->student->rowVersion,\n        ], \$overrides);",
        'expect' => 1,
        'why' => "the one payload builder behind five of this file's posts",
    ],
    [
        'file' => 'tests/Feature/Evaluation/RetentionGateTest.php',
        'from' => "            'formIssuedDate' => now()->toDateString(),\n        ];",
        'to' => "            'formIssuedDate' => now()->toDateString(),\n            'rowVersion' => \$this->student->rowVersion,\n        ];",
        'expect' => 1,
        'why' => 'the profile payload builder, distinguished from the two Enrollments::create sites by its closing bracket',
    ],
    [
        'file' => 'tests/Feature/E2E/EnrollmentWalkthroughTest.php',
        'from' => "                'formIssuedDate' => now()->toDateString(),\n            ])",
        'to' => "                'formIssuedDate' => now()->toDateString(),\n                'rowVersion' => \$enrollment->student->rowVersion,\n            ])",
        'expect' => 4,
        'why' => 'the four desk walks that capture the profile',
    ],
];

$plan = [];
foreach ($edits as $e) {
    $path = $root.'/'.$e['file'];
    if (! is_file($path)) {
        fwrite(STDERR, 'REFUSED: '.$e['file'].' is not here.'."\n");
        exit(1);
    }
    $bytes = (string) file_get_contents($path);
    $got = substr_count($bytes, $e['from']);
    if ($got !== $e['expect']) {
        fwrite(STDERR, sprintf("REFUSED: %s anchor matched %d time(s), expected %d (%s).\n",
            $e['file'], $got, $e['expect'], $e['why']));
        exit(1);
    }
    if (str_contains($bytes, "'rowVersion' =>")) {
        fwrite(STDERR, 'REFUSED: '.$e['file'].' already carries a rowVersion — this pass has already run, or the '
            ."fixture was repaired by hand. Re-read before writing again.\n");
        exit(1);
    }
    $plan[] = [$path, $e['from'], $e['to'], $got, $e['file']];
    printf("planned %-52s %d replacement(s)  (%s)\n", $e['file'], $got, $e['why']);
}

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";
    exit(0);
}

foreach ($plan as [$path, $from, $to, $count, $rel]) {
    $before = (string) file_get_contents($path);
    $after = str_replace($from, $to, $before);
    if (substr_count($after, $to) !== $count) {
        fwrite(STDERR, "REFUSED: after the replace, {$rel} would hold ".substr_count($after, $to)
            ." of {$count} expected results.\n");
        exit(1);
    }
    if (@file_put_contents($path, $after) === false) {
        fwrite(STDERR, "REFUSED: could not write {$rel}.\n");
        exit(1);
    }
    // Verify from disk, not from memory: a OneDrive-backed tree can refuse a write and return false quietly.
    if ((string) file_get_contents($path) !== $after) {
        fwrite(STDERR, "REFUSED: {$rel} does not read back as written.\n");
        exit(1);
    }
    printf("wrote %-52s %d site(s)\n", $rel, $count);
}

echo "\nDone. Run: php artisan test tests/Feature/Evaluation/ProfileCaptureTest.php "
    ."tests/Feature/Evaluation/RetentionGateTest.php tests/Feature/E2E/EnrollmentWalkthroughTest.php "
    ."tests/Feature/Rbac/PermissionMatrixTest.php\n";
exit(0);
