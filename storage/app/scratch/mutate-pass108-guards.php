<?php

/**
 * Mutate pass 108 and require a refusal each time, one mutant per process, with each verdict read out of
 * that run's own log.
 *
 *   A  the enrollment count in the new sentence is wrong              → the measurement refuses
 *   B  the "15 still in progress" expectation is moved                → the measurement refuses
 *   C  the overstatement is left standing in the replacement          → the "overstatement is gone" guard
 *   D  the boundary ruling reworded away from its own anchor          → the "present once" guard
 *   E  the appended ruling injects a paragraph                       → the structure guard
 *   F  the guard's existence check aimed at a class that is not there → the "class the ruling names" guard
 *
 * Usage: php mutate-pass108-guards.php A|B|C|D|E|F   (exit 0 = refused, 1 = accepted)
 */

$root = dirname(__DIR__, 3);
$engine = $root.'/storage/app/scratch/refresh-docx-pass108.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$src = file_get_contents($engine);

$cases = [
    'A' => [
        'what' => 'the sentence claims 30 enrollments where live reads 29',
        'find' => ".'history: the install credits those four accounts with no enrollment at all, while 29 rows name '",
        'fix' => ".'history: the install credits those four accounts with no enrollment at all, while 30 rows name '",
    ],
    'B' => [
        'what' => 'the in-progress expectation moved off the measured figure',
        'find' => 'if ($inProgress !== 15 || $collegesOfHeld !== 3) {',
        'fix' => 'if ($inProgress !== 16 || $collegesOfHeld !== 3) {',
    ],
    'C' => [
        'what' => 'the correction leaves the overstated clause in place',
        'find' => ".'evaluation rights over every program from one anonymous desk — and it was only ever a reach, not a '",
        'fix' => ".'evaluated every program from one anonymous desk, and it was only ever a reach, not a '",
    ],
    'D' => [
        'what' => 'the boundary ruling written under a different opening phrase',
        'find' => "' Ruled the same day, the boundary those desks work inside:",
        'fix' => "' Ruled the same day, the limits those desks work inside:",
    ],
    'E' => [
        'what' => 'the appended ruling injects a paragraph',
        'find' => ".'through that person’s enrollments and applications), and the shift docket, where the paper '",
        'fix' => ".'through </w:t></w:r></w:p><w:p><w:r><w:t>enrollments and applications), and the shift docket, where the paper '",
    ],
    'F' => [
        'what' => 'the class existence check aimed at a class that was never written',
        'find' => "is_file(\$root.'/app/Support/CollegeScope.php')",
        'fix' => "is_file(\$root.'/app/Support/CollegeBoundaries.php')",
    ],
];

$id = $argv[1] ?? '';
if (! isset($cases[$id])) {
    fwrite(STDERR, "usage: php ".basename(__FILE__)." A|B|C|D|E|F\n");
    exit(2);
}

$c = $cases[$id];
$hits = substr_count($src, $c['find']);
if ($hits !== 1) {
    printf("mutant %s  %-58s NOT USABLE: anchor occurs %d time(s)\n", $id, $c['what'], $hits);
    exit(1);
}

$body = str_replace($c['find'], $c['fix'], $src);
file_put_contents($root.'/storage/app/scratch/mutant-pass108.php', $body);
clearstatcache(true, $root.'/storage/app/scratch/mutant-pass108.php');

$lint = [];
exec(sprintf('cd "%s" && "%s" -l storage/app/scratch/mutant-pass108.php 2>&1', $root, $php), $lint, $lintCode);
if ($lintCode !== 0) {
    printf("mutant %s  %-58s NOT USABLE: does not parse — %s\n", $id, $c['what'], trim(implode(' ', $lint)));
    @unlink($root.'/storage/app/scratch/mutant-pass108.php');
    exit(1);
}

$lines = [];
exec(sprintf('cd "%s" && "%s" storage/app/scratch/mutant-pass108.php 2>&1', $root, $php), $lines, $code);
@unlink($root.'/storage/app/scratch/mutant-pass108.php');

file_put_contents($root."/storage/app/scratch/mutate-pass108-{$id}.log", implode("\n", $lines)."\n");

printf("mutant %s  %-58s exit=%d  %s\n", $id, $c['what'], $code,
    $code !== 0 ? 'refused' : '*** ACCEPTED — this check is decorative ***');

exit($code !== 0 ? 0 : 1);
