<?php

/**
 * Lane 43 mutant harness — proves `lane43-delete-guards.php` can actually fail.
 *
 * The checker reads the controllers and the live schema, so the mutants are planted in the real tree and
 * restored immediately; every restore is verified against the hash taken before the plant, and a mismatch
 * aborts rather than leaving a mutant behind.
 *
 * Four classes, because the parser had to learn four idioms and each one is a way it could go blind:
 *   A  the literal array passed to blockedDeletionMessage()
 *   B  the ->exists() chain in UserManagementController
 *   C  a model class named directly (destroySubject)
 *   D  a table name handed to a closure inside a private helper (officeUsage) — the case that taught the
 *      checker to read quoted literals at all, since no `DB::table('x')` appears in that helper
 *
 * Usage: php storage/app/scratch/mutate-lane43-delete-guards.php
 */

$root = dirname(__DIR__, 3);
$checker = $root.'/storage/app/scratch/lane43-delete-guards.php';
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';

$targets = [
    'reference' => $root.'/app/Http/Controllers/Admin/ReferenceDataController.php',
    'users' => $root.'/app/Http/Controllers/Admin/UserManagementController.php',
];

$run = function () use ($php, $root, $checker): array {
    $out = [];
    $code = 0;
    exec('cd /d '.escapeshellarg($root).' && '.escapeshellarg($php).' '.escapeshellarg($checker).' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};

$failures = 0;

$case = function (string $name, string $file, string $from, string $to, string $expectNeedle) use ($targets, $run, &$failures): void {
    $path = $targets[$file];
    $before = hash_file('sha256', $path);
    $src = (string) file_get_contents($path);

    if (substr_count($src, $from) !== 1) {
        printf("  BAD  %-34s the plant target is not unique (%d occurrences) — nothing was touched\n",
            $name, substr_count($src, $from));
        $failures++;

        return;
    }

    file_put_contents($path, str_replace($from, $to, $src));
    try {
        [$code, $out] = $run();
    } finally {
        file_put_contents($path, $src);
    }

    $restored = hash_file('sha256', $path) === $before;
    $caught = $code !== 0 && str_contains($out, $expectNeedle);

    printf("  %s %-34s exit=%d  needle %-10s  restored=%s\n",
        $caught && $restored ? 'ok  ' : 'BAD ', $name, $code, $caught ? 'found' : 'MISSING',
        $restored ? 'yes' : 'NO — hash differs');

    if (! $caught || ! $restored) {
        $failures++;
    }
};

echo "=== baseline: the unmutated tree must be unflagged ===\n";
[$code, $out] = $run();
printf("  %s clean run exit=%d\n", $code === 0 ? 'ok  ' : 'BAD ', $code);
if ($code !== 0) {
    echo "\n".$out."\n";
    fwrite(STDERR, "\nbaseline is not green — fix the checker or the guards before planting mutants\n");
    exit(1);
}

echo "\n=== planted defects ===\n";

// A and B first tried renaming the label and the column, and the checker still passed the run — correct
// behaviour, bad mutant: the guard's job is to name the TABLE, so a mutant that keeps the table named is not a
// defect the checker could ever see. Both plants now remove the table reference itself.
$case(
    'A  blockedDeletionMessage entry',
    'reference',
    "            'shift request' => DB::table('shiftingrequests')",
    "            'shift request' => DB::table('shiftRequestsGone')",
    'destroyCourse'
);

$case(
    'B  exists() chain in users',
    'users',
    "|| Shiftingrequests::where('requestedBy', \$user->userId)->exists()",
    "|| ShiftingrequestsGone::where('requestedBy', \$user->userId)->exists()",
    'staffusers'
);

$case(
    'C  a model named directly',
    'reference',
    "            'enrollment' => DB::table('enrollments')->where('courseId', \$course->courseId)->count(),",
    "            'enrollment' => 0,",
    'destroyCourse'
);

$case(
    'D  table handed to a helper closure',
    'reference',
    "            'staff' => \$countBy('staffusers'),",
    "            'staff' => \$countBy('staffusersX'),",
    'destroyOffice'
);

echo "\n=== final: unmutated again ===\n";
[$code, $out] = $run();
printf("  %s clean run exit=%d\n", $code === 0 ? 'ok  ' : 'BAD ', $code);
if ($code !== 0) {
    echo $out."\n";
    $failures++;
}

printf("\n  %d case(s) failed.\n", $failures);

exit($failures === 0 ? 0 : 1);
