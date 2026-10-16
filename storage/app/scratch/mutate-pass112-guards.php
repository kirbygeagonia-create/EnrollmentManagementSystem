<?php

/**
 * Mutate pass 112 and require a refusal, one mutant per process.
 *
 *   A  a corrected number aimed at the PIPELINE_ORDER constant it was written to leave  → the code-reading check
 *   B  the rename applied to every `stepsFor` mention instead of the one label sentence → the count guards
 *   C  the filing-refusals range shortened until one of its three messages falls out     → the code-reading check
 *
 * Usage: php mutate-pass112-guards.php A|B|C   (exit 0 = refused)
 */

$root = dirname(__DIR__, 3);
$engine = $root.'/storage/app/scratch/refresh-docx-pass112.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$src = file_get_contents($engine);

$cases = [
    'A' => [
        'what' => 'signStep citation aimed at the constant instead',
        'find' => "'WorkflowService.php:126-131', 'WorkflowService.php:258-262',",
        'fix' => "'WorkflowService.php:126-131', 'WorkflowService.php:112-118',",
    ],
    'B' => [
        'what' => 'the rename reaching all five mentions',
        'find' => "substr_count(\$xml, 'WorkflowService::stepsFor') === 4",
        'fix' => "substr_count(\$xml, 'WorkflowService::stepsFor') === -4",
    ],
    'C' => [
        'what' => 'filing-refusals range that misses the third refusal',
        'find' => "'ShiftRequestController.php:45', 'ShiftRequestController.php:137-179',",
        'fix' => "'ShiftRequestController.php:45', 'ShiftRequestController.php:137-150',",
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
    printf("mutant %s  %-52s NOT USABLE: anchor occurs %d time(s)\n", $id, $c['what'], $hits);
    exit(1);
}

$body = str_replace($c['find'], $c['fix'], $src);
file_put_contents($root.'/storage/app/scratch/mutant-pass112.php', $body);
clearstatcache(true, $root.'/storage/app/scratch/mutant-pass112.php');

$lint = [];
exec(sprintf('cd "%s" && "%s" -l storage/app/scratch/mutant-pass112.php 2>&1', $root, $php), $lint, $lintCode);
if ($lintCode !== 0) {
    printf("mutant %s  %-52s NOT USABLE: %s\n", $id, $c['what'], trim(implode(' ', $lint)));
    @unlink($root.'/storage/app/scratch/mutant-pass112.php');
    exit(1);
}

$lines = [];
exec(sprintf('cd "%s" && "%s" storage/app/scratch/mutant-pass112.php 2>&1', $root, $php), $lines, $code);
@unlink($root.'/storage/app/scratch/mutant-pass112.php');
file_put_contents($root."/storage/app/scratch/mutate-pass112-{$id}.log", implode("\n", $lines)."\n");

printf("mutant %s  %-52s exit=%d  %s\n", $id, $c['what'], $code,
    $code !== 0 ? 'refused' : '*** ACCEPTED — decorative ***');
exit($code !== 0 ? 0 : 1);
