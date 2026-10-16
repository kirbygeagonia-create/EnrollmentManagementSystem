<?php

/**
 * Mutants for the order-independence fix: one per process, verdict read from the log.
 *
 *   X  — DevReferenceDataSeeder stops re-deriving roles (the fresh install regresses to role-less heads)
 *   Y  — the office-7 pairing loses DeptEvaluator (the head of the owning desk can no longer sign)
 *
 * Each is applied to a file, run against the test file, then restored from an in-memory baseline in a
 * finally, and the script refuses to report anything unless the mutation is verified to have landed and
 * the file is verified to be back.
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';

$cases = [
    'X' => [
        'what' => 'the dev seeder no longer re-derives roles',
        'file' => 'database/seeders/DevReferenceDataSeeder.php',
        'find' => '        RbacSeeder::assignDeskRolesFromEnums();',
        'fix' => '        // mutant: derivation removed',
    ],
    'Y' => [
        'what' => 'office 7 loses its DeptEvaluator pairing',
        'file' => 'database/seeders/RbacSeeder.php',
        'find' => "                    OfficeId::Academic->value => ['DeptEvaluator', 'OfficeHead'],",
        'fix' => "                    OfficeId::Academic->value => ['OfficeHead'],",
    ],
];

$id = $argv[1] ?? '';
if (! isset($cases[$id])) {
    fwrite(STDERR, "usage: php ".basename(__FILE__)." X|Y\n");
    exit(2);
}

$c = $cases[$id];
$path = $root.'/'.$c['file'];
$baseline = file_get_contents($path);
$code = 0;
$lines = [];
$restored = false;

try {
    if (substr_count($baseline, $c['find']) !== 1) {
        fwrite(STDERR, sprintf("%s NOT PLANTED: %d matches\n", $id, substr_count($baseline, $c['find'])));
        exit(3);
    }
    file_put_contents($path, str_replace($c['find'], $c['fix'], $baseline));
    clearstatcache(true, $path);
    if (file_get_contents($path) === $baseline) {
        fwrite(STDERR, "{$id} NOT PLANTED: file unchanged\n");
        exit(3);
    }

    exec(sprintf('cd "%s" && "%s" artisan test tests/Feature/Rbac/SeededAccountRoleTest.php 2>&1', $root, $php), $lines, $code);
    file_put_contents($root."/storage/app/scratch/roles-mut-{$id}.log", implode("\n", $lines));
} finally {
    file_put_contents($path, $baseline);
    clearstatcache(true, $path);
    $restored = file_get_contents($path) === $baseline;
}

printf("mutant %s  %s\n", $id, $c['what']);
foreach ($lines as $line) {
    if (str_contains($line, 'Tests:') || str_contains($line, '⨯')) {
        echo '   '.trim(preg_replace('/\s{2,}/', '  ', $line))."\n";
    }
}
printf("  exit=%d  restored=%s\n", $code, $restored ? 'yes' : 'NO');
exit(($code !== 0 && $restored) ? 0 : 1);
