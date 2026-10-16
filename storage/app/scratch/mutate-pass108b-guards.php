<?php

/**
 * Mutate pass 108b and require a refusal, one mutant per process.
 *
 *   A  the replacement keeps the stacked `while` it was written to remove  → the "old joiner is gone" guard
 *   B  the replacement loses the measured figure it was told to preserve   → the "figures survive" guard
 *   C  a second copy of the anchor appears in the document                 → the count assertion refuses
 *
 * Usage: php mutate-pass108b-guards.php A|B|C   (exit 0 = refused)
 */

$root = dirname(__DIR__, 3);
$engine = $root.'/storage/app/scratch/refresh-docx-pass108b.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$src = file_get_contents($engine);

$cases = [
    'A' => [
        'what' => 'the joiner left standing after the rewrite',
        'find' => "    .'the Guidance office id 4, and that is how';",
        'fix' => "    .'the Guidance office id 4 — which is how';",
    ],
    'B' => [
        'what' => 'the measured in-progress figure dropped from the sentence',
        'find' => "\$to = '15 of them still in progress in three different colleges. The launcher, meanwhile, keyed the card to '",
        'fix' => "\$to = 'the three colleges it spans. The launcher, meanwhile, keyed the card to '",
    ],
    'C' => [
        'what' => 'the anchor asserted a second occurrence the document does not have',
        'find' => 'if ($n !== 1) {',
        'fix' => 'if ($n !== 2) {',
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
    printf("mutant %s  %-56s NOT USABLE: anchor occurs %d time(s)\n", $id, $c['what'], $hits);
    exit(1);
}

$body = str_replace($c['find'], $c['fix'], $src);
file_put_contents($root.'/storage/app/scratch/mutant-pass108b.php', $body);
clearstatcache(true, $root.'/storage/app/scratch/mutant-pass108b.php');

$lint = [];
exec(sprintf('cd "%s" && "%s" -l storage/app/scratch/mutant-pass108b.php 2>&1', $root, $php), $lint, $lintCode);
if ($lintCode !== 0) {
    printf("mutant %s  %-56s NOT USABLE: does not parse — %s\n", $id, $c['what'], trim(implode(' ', $lint)));
    @unlink($root.'/storage/app/scratch/mutant-pass108b.php');
    exit(1);
}

$lines = [];
exec(sprintf('cd "%s" && "%s" storage/app/scratch/mutant-pass108b.php 2>&1', $root, $php), $lines, $code);
@unlink($root.'/storage/app/scratch/mutant-pass108b.php');
file_put_contents($root."/storage/app/scratch/mutate-pass108b-{$id}.log", implode("\n", $lines)."\n");

printf("mutant %s  %-56s exit=%d  %s\n", $id, $c['what'], $code,
    $code !== 0 ? 'refused' : '*** ACCEPTED — decorative ***');
exit($code !== 0 ? 0 : 1);
