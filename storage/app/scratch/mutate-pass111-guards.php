<?php

/**
 * Mutate pass 111 and require a refusal, one mutant per process.
 *
 *   A  the Gate::before citation pointed at a real-but-wrong block (blocking.manageBlocks)  → the proof step
 *   B  a citation moved to a range whose last line is not the construct's end               → the proof step
 *   C  the occurrence count of a two-place citation relaxed to one                          → the anchor count
 *
 * Each aims at a different protection, because "exit non-zero" is not attribution: the harness prints the
 * refusal from the run's own log so the check that fired can be seen to be the one being tested.
 *
 * Usage: php mutate-pass111-guards.php A|B|C   (exit 0 = refused)
 */

$root = dirname(__DIR__, 3);
$engine = $root.'/storage/app/scratch/refresh-docx-pass111.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$src = file_get_contents($engine);

$cases = [
    'A' => [
        'what' => 'Gate::before cited onto the blocking.manageBlocks block',
        'find' => "['AuthServiceProvider.php:97-101', 'AuthServiceProvider.php:106-110', \$provider,",
        'fix' => "['AuthServiceProvider.php:97-101', 'AuthServiceProvider.php:121-123', \$provider,",
    ],
    'B' => [
        'what' => 'a range ended one line past the construct it names',
        'find' => "['RegistrarController.php:144-146', 'RegistrarController.php:180-182',",
        'fix' => "['RegistrarController.php:144-146', 'RegistrarController.php:180-183',",
    ],
    'C' => [
        'what' => 'the two-place citation told to rewrite only one occurrence',
        'find' => "->hasRole('SysAdmin')\", 2],",
        'fix' => "->hasRole('SysAdmin')\", 1],",
    ],
];

$id = $argv[1] ?? '';
if (! isset($cases[$id])) {
    fwrite(STDERR, "usage: php ".basename(__FILE__)." A|B|C\n");
    exit(2);
}

$c = $cases[$id];
$hits = substr_count($src, $c['find']);
if ($hits !== 1) {
    printf("mutant %s  %-58s NOT USABLE: anchor occurs %d time(s)\n", $id, $c['what'], $hits);
    exit(1);
}

$body = str_replace($c['find'], $c['fix'], $src);
file_put_contents($root.'/storage/app/scratch/mutant-pass111.php', $body);
clearstatcache(true, $root.'/storage/app/scratch/mutant-pass111.php');

$lint = [];
exec(sprintf('cd "%s" && "%s" -l storage/app/scratch/mutant-pass111.php 2>&1', $root, $php), $lint, $lintCode);
if ($lintCode !== 0) {
    printf("mutant %s  %-58s NOT USABLE: does not parse — %s\n", $id, $c['what'], trim(implode(' ', $lint)));
    @unlink($root.'/storage/app/scratch/mutant-pass111.php');
    exit(1);
}

$lines = [];
exec(sprintf('cd "%s" && "%s" storage/app/scratch/mutant-pass111.php 2>&1', $root, $php), $lines, $code);
@unlink($root.'/storage/app/scratch/mutant-pass111.php');
file_put_contents($root."/storage/app/scratch/mutate-pass111-{$id}.log", implode("\n", $lines)."\n");

printf("mutant %s  %-58s exit=%d  %s\n", $id, $c['what'], $code,
    $code !== 0 ? 'refused' : '*** ACCEPTED — this check is decorative ***');
exit($code !== 0 ? 0 : 1);
