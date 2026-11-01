<?php

/**
 * Delivery-gate runner.
 *
 * The three static gates (test discovery, Pint, PHPStan) were launched from a shell one-liner that called bare
 * `php` and `vendor/bin/pint`, and cmd.exe answered 127 — a command not found is not a gate result, and the log
 * still printed an exit-code line that looked like a verdict. This driver names the PHP binary, names each tool's
 * own file, and prints one labelled line per gate with the exit code taken from that gate alone, so a wrapper can
 * never read as success about a command that never ran.
 *
 * Usage: php storage/app/scratch/lane157-run-static-gates.php [--only=discover|pint|stan] [--keep-logs]
 */
$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$known = ['only', 'keep-logs'];
$only = null;

for ($i = 1; $i < count($argv); $i++) {
    if (! preg_match('/^--([a-z0-9-]+)(=(.*))?$/', $argv[$i], $m)) {
        fwrite(STDERR, 'REFUSED: unreadable argument '.$argv[$i]."\n");
        exit(1);
    }
    if (! in_array($m[1], $known, true)) {
        fwrite(STDERR, "REFUSED: unknown flag --{$m[1]} — this driver does not read it\n");
        exit(1);
    }
    if ($m[1] === 'only') {
        $only = $m[3] ?? '';
    }
}

$run = static function (string $label, string $cmd, string $logFile) use ($root, $only): array {
    if ($only !== null && $only !== '' && $only !== $label) {
        return ['label' => $label, 'code' => null, 'note' => 'skipped by --only'];
    }
    $out = [];
    $code = 0;
    chdir($root);
    exec($cmd.' 2>&1', $out, $code);
    file_put_contents($root.'/storage/app/scratch/'.$logFile, implode("\n", $out)."\n");

    return ['label' => $label, 'code' => $code, 'note' => 'log storage/app/scratch/'.$logFile];
};

$results = [];
$results[] = $run('discover',
    escapeshellarg($php).' artisan test --list-tests',
    'lane156-list.txt');
$results[] = $run('pint',
    escapeshellarg($php).' vendor/bin/pint --test',
    'lane156-pint.txt');
$results[] = $run('stan',
    escapeshellarg($php).' vendor/bin/phpstan analyse --no-progress',
    'lane156-phpstan.txt');

$discovered = null;
$listBytes = $root.'/storage/app/scratch/lane156-list.txt';
if (is_file($listBytes)) {
    $text = (string) file_get_contents($listBytes);
    // One leading dash plus the Tests\ namespace is how `--list-tests` prints each discovered case.
    $discovered = preg_match_all('/^\s*-\s+Tests\\\\/m', $text);
}

foreach ($results as $r) {
    printf("%-9s exit=%-5s %s\n", $r['label'],
        $r['code'] === null ? 'n/a' : $r['code'], $r['note']);
}
printf("discovered tests: %s\n", $discovered ?? 'NOT READ');

$failed = array_values(array_filter($results, static fn ($r) => $r['code'] !== null && $r['code'] !== 0));
if ($failed !== []) {
    fwrite(STDERR, 'GATES FAILED: '.implode(', ', array_column($failed, 'label'))."\n");
    exit(1);
}
echo 'ALL STATIC GATES RAN AND PASSED'."\n";
