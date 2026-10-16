<?php

/**
 * One mutant per process, for the #91 claims. Same shape as the lane-25 harness, because that one
 * learned two things the hard way: a single process running every mutant reported the first test as
 * failing under all of them, and Pest truncates names in its summary block — so each mutant is applied,
 * run, logged and restored in its own PHP invocation, and the failing test is read from the per-test
 * cross glyph line rather than a header.
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';

$mutants = [
    'A' => [
        'what' => 'the migration stops changing anything',
        'file' => 'database/migrations/2026_10_13_1000000001_drop_database_stamped_audit_defaults.php',
        'find' => "        Schema::table('auditlogs', function (Blueprint \$table) {\n            \$table->dateTime('createdAt')->change();\n        });",
        'fix' => '        // mutant: the first change is removed',
        'expect' => 'neither audit column defers the timestamp',
    ],
    'B' => [
        'what' => 'assignRoles stops passing createdAt',
        'file' => 'app/Http/Controllers/Admin/UserManagementController.php',
        'find' => "            'ipAddress' => \$request->ip(),\n            'createdAt' => now(),\n        ]);\n\n        return back()->with('success', 'Roles assigned.');",
        'fix' => "            'ipAddress' => \$request->ip(),\n        ]);\n\n        return back()->with('success', 'Roles assigned.');",
        'expect' => 'assigning roles stamps the audit row',
    ],
    'C' => [
        'what' => 'the create migration no longer declares useCurrent (the guard\'s premise moves)',
        'file' => 'database/migrations/2026_08_02_1000000012_create_auditlogs_table.php',
        'find' => "\$table->dateTime('createdAt')->useCurrent();",
        'fix' => "\$table->dateTime('createdAt')->nullable();",
        'expect' => 'the original migrations do still create the default',
    ],
];

$id = $argv[1] ?? '';
if (! isset($mutants[$id])) {
    fwrite(STDERR, "usage: php ".basename(__FILE__)." A|B|C\n");
    exit(2);
}

$m = $mutants[$id];
$path = $root.'/'.$m['file'];
$baseline = file_get_contents($path);
if ($baseline === false) {
    fwrite(STDERR, "cannot read {$path}\n");
    exit(2);
}

$code = 0;
$lines = [];
$restored = false;

try {
    $needle = strpos($baseline, $m['find']);
    if ($needle === false) {
        fwrite(STDERR, sprintf("mutant %s NOT PLANTED: find string absent from %s\n", $id, basename($path)));
        exit(3);
    }

    $mutated = substr_replace($baseline, $m['fix'], $needle, strlen($m['find']));
    if ($mutated === $baseline) {
        fwrite(STDERR, sprintf("mutant %s NOT PLANTED: file unchanged\n", $id));
        exit(3);
    }

    file_put_contents($path, $mutated);
    clearstatcache(true, $path);
    if (file_get_contents($path) === $baseline) {
        fwrite(STDERR, sprintf("mutant %s NOT PLANTED: disk refused the write\n", $id));
        exit(3);
    }

    exec(sprintf('cd "%s" && "%s" artisan test tests/Feature/Observers/AuditTimestampStampingTest.php 2>&1',
        $root, $php), $lines, $code);
    file_put_contents($root."/storage/app/scratch/stamp-mut-{$id}.log", implode("\n", $lines));
} finally {
    file_put_contents($path, $baseline);
    clearstatcache(true, $path);
    $restored = file_get_contents($path) === $baseline;
}

$summary = '';
$failing = [];
foreach ($lines as $line) {
    $t = trim(preg_replace('/\s+/', ' ', $line));
    if (str_contains($t, 'Tests:')) {
        $summary = $t;
    }
    if (preg_match('/^\W*\s*(\p{L}.*?)\s+[\d.]+m?s\s*$/u', $t, $x) === 1 && str_contains($t, '⨯')) {
        $failing[] = trim(preg_replace('/^\W*\s*/u', '', $t));
    }
}

printf("mutant %s  %s\n", $id, $m['what']);
printf("  exit=%d  %s\n", $code, $summary === '' ? '(no summary captured)' : $summary);
printf("  failing: %s\n", $failing === [] ? '(none captured — read the log)' : implode(' | ', $failing));
printf("  expected to break: %s\n", $m['expect']);
printf("  restored: %s\n", $restored ? 'yes' : 'NO — fix '.$path.' by hand');

$hit = false;
foreach ($failing as $name) {
    if (str_contains($name, $m['expect'])) {
        $hit = true;
    }
}

$ok = $code !== 0 && $hit && $restored;
printf("  %s\n\n", $ok ? 'ATTRIBUTED' : ($code !== 0 ? '*** ran red but not the expected test ***' : '*** SURVIVED ***'));
exit($ok ? 0 : 1);
