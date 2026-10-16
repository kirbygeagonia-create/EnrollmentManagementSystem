<?php

/**
 * Remove only `Auditlogs::booted()`'s creating() line and require the tests to say so.
 *
 * The suite already proved the other half of this pair on its own: with the database default gone and no
 * model default at all, `HiddenBugsRemediationTest::fix5` failed with `NOT NULL constraint failed:
 * auditlogs.createdAt`. This mutant asks the narrower question — with the schema still default-free, does
 * the new test actually depend on the model, or would it pass anyway?
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$path = $root.'/app/Models/Auditlogs.php';
$find = 'static::creating(fn (self $log) => $log->createdAt ??= now());';
$fix = '// mutant: the model no longer fills createdAt';

$baseline = file_get_contents($path);
$code = 0;
$lines = [];
$restored = false;

try {
    if (substr_count($baseline, $find) !== 1) {
        fwrite(STDERR, sprintf("NOT PLANTED: %d matches\n", substr_count($baseline, $find)));
        exit(3);
    }
    file_put_contents($path, str_replace($find, $fix, $baseline));
    clearstatcache(true, $path);
    if (file_get_contents($path) === $baseline) {
        fwrite(STDERR, "NOT PLANTED: unchanged\n");
        exit(3);
    }

    exec(sprintf('cd "%s" && "%s" artisan test tests/Feature/Observers/AuditTimestampStampingTest.php tests/Feature/HiddenBugsRemediationTest.php 2>&1',
        $root, $php), $lines, $code);
    file_put_contents($root.'/storage/app/scratch/mut-model-stamp.log', implode("\n", $lines));
} finally {
    file_put_contents($path, $baseline);
    clearstatcache(true, $path);
    $restored = file_get_contents($path) === $baseline;
}

echo "mutant: Auditlogs no longer fills createdAt in creating()\n";
foreach ($lines as $line) {
    if (str_contains($line, 'Tests:') || str_contains($line, '⨯')) {
        echo '   '.trim(preg_replace('/\s{2,}/', '  ', $line))."\n";
    }
}
printf("  exit=%d  restored=%s\n", $code, $restored ? 'yes' : 'NO — restore app/Models/Auditlogs.php by hand');
exit(($code !== 0 && $restored) ? 0 : 1);
