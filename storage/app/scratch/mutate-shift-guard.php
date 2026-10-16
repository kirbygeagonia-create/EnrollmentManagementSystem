<?php

/**
 * Mutate the shift-filing guard and require a refusal, one mutant per process.
 *
 *   G  the guard removed — a desk could file a paper it will never see again   → the refusal test must die
 *   H  the guard inverted to refuse everybody                                   → the two accepting tests die
 *
 * The accepting cases come first in the test on purpose: a mutant that makes everything refuse proves the
 * test can tell refusal from boundary, which a test that only ever asserts refusals cannot.
 *
 * Usage: php mutate-shift-guard.php G|H   (exit 0 = refused)
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$target = $root.'/app/Http/Controllers/Evaluation/ShiftRequestController.php';
$baseline = file_get_contents($target);

$cases = [
    'G' => [
        'what' => 'the college check dropped from the filing route',
        'find' => "        if (! \$mine) {",
        'fix' => "        if (false && ! \$mine) {",
    ],
    'H' => [
        'what' => 'the college check made to refuse every filing',
        'find' => "        \$mine = CollegeScope::holds(\$request->user(), \$current->course?->unitId)",
        'fix' => "        \$mine = false && CollegeScope::holds(\$request->user(), \$current->course?->unitId)",
    ],
];

$id = $argv[1] ?? '';
if (! isset($cases[$id])) {
    fwrite(STDERR, "usage: php ".basename(__FILE__)." G|H\n");
    exit(2);
}

$c = $cases[$id];
$hits = substr_count($baseline, $c['find']);
if ($hits !== 1) {
    printf("mutant %s  %-56s NOT USABLE: anchor occurs %d time(s)\n", $id, $c['what'], $hits);
    exit(1);
}

$restored = false;
$code = 0;
try {
    file_put_contents($target, str_replace($c['find'], $c['fix'], $baseline));
    clearstatcache(true, $target);

    $lint = [];
    exec(sprintf('cd "%s" && "%s" -l app/Http/Controllers/Evaluation/ShiftRequestController.php 2>&1', $root, $php), $lint, $lintCode);
    if ($lintCode !== 0) {
        printf("mutant %s  %-56s NOT USABLE: does not parse — %s\n", $id, $c['what'], trim(implode(' ', $lint)));
        exit(1);
    }

    $lines = [];
    exec(sprintf('cd "%s" && "%s" artisan test tests/Feature/Rbac/CollegeDeskScopeTest.php 2>&1', $root, $php), $lines, $code);
    file_put_contents($root."/storage/app/scratch/mutate-shift-{$id}.log", implode("\n", $lines)."\n");

    $died = [];
    $summary = '';
    foreach ($lines as $line) {
        $t = trim(preg_replace('/\s+/', ' ', strip_tags($line)));
        if (stripos($t, 'FAILED') !== false) {
            $died[] = $t;
        }
        if (stripos($t, 'Tests:') !== false) {
            $summary = $t;
        }
    }

    printf("mutant %s  %-56s exit=%d  %s\n", $id, $c['what'], $code,
        $code !== 0 ? 'CAUGHT' : '*** SURVIVED — the guard is not being tested ***');
    foreach (array_slice($died, 0, 3) as $d) {
        printf("     died: %s\n", mb_substr($d, 0, 110));
    }
    printf("     %s\n", $summary !== '' ? $summary : 'no summary line — do not trust this verdict');
} finally {
    file_put_contents($target, $baseline);
    clearstatcache(true, $target);
    $restored = file_get_contents($target) === $baseline;
}

if (! $restored) {
    fwrite(STDERR, "THE SOURCE WAS NOT RESTORED — repair ShiftRequestController.php by hand\n");
    exit(1);
}

exit($code !== 0 ? 0 : 1);
