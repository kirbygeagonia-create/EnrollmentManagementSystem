<?php

/**
 * Falsifier for P05 (the optimistic version on the student record).
 *
 * One leg per promise the feature makes, each requiring the case that NAMES that promise to go red. Written because
 * the guard is the kind that looks fine: a comparison that never fires, a bump that never lands and a `required`
 * quietly downgraded to `nullable` all leave every screen rendering exactly as before.
 *
 * Usage: php storage/app/scratch/mutate-p05-version-tests.php
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
chdir($root);

$controller = 'app/Http/Controllers/Evaluation/EvaluationController.php';
$model = 'app/Models/Students.php';
$testFile = 'tests/Feature/Evaluation/ProfileCaptureTest.php';

$targets = [$controller, $model];
$original = [];
foreach ($targets as $t) {
    $bytes = @file_get_contents($root.'/'.$t);
    if ($bytes === false) {
        fwrite(STDERR, "REFUSED: cannot read {$t}.\n");
        exit(1);
    }
    $original[$t] = $bytes;
}

register_shutdown_function(function () use ($root, $original) {
    foreach ($original as $rel => $bytes) {
        if ((string) @file_get_contents($root.'/'.$rel) !== $bytes) {
            file_put_contents($root.'/'.$rel, $bytes);
        }
    }
});

$run = function (string $method) use ($root, $php, $testFile): array {
    $cmd = sprintf('cd /d %s && %s artisan test %s%s 2>&1',
        escapeshellarg($root), escapeshellarg($php), escapeshellarg($testFile),
        $method === '' ? '' : ' --filter '.escapeshellarg($method));
    // The target is asserted inside the command: a closure that fails to capture it silently measures the whole
    // suite and prints a tally that reads like a delivered run (mutate-p17 met exactly this).
    if (! str_contains($cmd, 'ProfileCaptureTest.php')) {
        throw new RuntimeException('the run lost its target file; refusing to measure something broader');
    }
    $out = preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec($cmd));
    if (! preg_match('/Tests:\s+([^\r\n]+)/', $out, $m)) {
        return [-1, 'no-tally'];
    }

    return [str_contains($m[1], 'failed') ? 1 : 0, trim($m[1])];
};

$plant = function (string $rel, string $from, string $to) use ($root, $original): void {
    if (substr_count($original[$rel], $from) !== 1) {
        throw new RuntimeException("anchor `".substr($from, 0, 55).'…` occurs '
            .substr_count($original[$rel], $from).' time(s) in '.$rel.', expected 1');
    }
    if (@file_put_contents($root.'/'.$rel, str_replace($from, $to, $original[$rel])) === false) {
        throw new RuntimeException("could not write the plant to {$rel}");
    }
    if (! str_contains((string) file_get_contents($root.'/'.$rel), $to)) {
        throw new RuntimeException("the plant is not on disk in {$rel}");
    }
};

$legs = [];
$say = function (string $name, bool $ok, string $detail) use (&$legs): void {
    $legs[] = [$name, $ok];
    printf("  %s %-42s %s\n", $ok ? 'ok  ' : 'BAD ', $name, $detail);
};

[$rc, $tally] = $run('');
$say('control-green', $rc === 0, "rc={$rc} Tests: {$tally}");
if ($rc !== 0) {
    fwrite(STDERR, "REFUSED: the control is not green; nothing below can be read as a kill.\n");
    exit(1);
}

$stale = 'a_stale_version_is_refused_and_the_newer_save_survives';
$bump = 'a_successful_save_bumps_the_version_by_exactly_one';
$missing = 'a_payload_without_a_version_is_refused_rather_than_overwriting';
$fillable = 'the_version_is_not_something_a_payload_may_write_directly';

// 1. the comparison never fires: a second desk overwrites the first
$plant($controller, 'if ($held !== $posted) {', 'if (false && $held !== $posted) {');
[$rc, $t] = $run($stale);
$say('version-never-compared', $rc === 1, "rc={$rc} Tests: {$t}");

// 2. compared but never bumped: every screen keeps the same version and the guard is decoration
$plant($controller, '$student->rowVersion = $held + 1;', '$student->rowVersion = $held;');
[$rc, $t] = $run($bump);
$say('compared-but-not-bumped', $rc === 1, "rc={$rc} Tests: {$t}");

// 3. `required` downgraded to `nullable`: an older screen keeps the right to overwrite
$plant($controller, "'rowVersion' => 'required|integer|min:1',", "'rowVersion' => 'nullable|integer',");
[$rc, $t] = $run($missing);
$say('missing-version-accepted', $rc === 1, "rc={$rc} Tests: {$t}");

// 4. the refusal arrives without saying what was refused: the desk is told nothing
$plant($controller, "return back()->withErrors([\n                'rowVersion'",
    "return back()->with('info', 'x')->withErrors([\n                '_unused'");
[$rc, $t] = $run($stale);
$say('refusal-names-nothing', $rc === 1, "rc={$rc} Tests: {$t}");

// 5. the version becomes client-writable: a stale screen can simply post a higher number
$plant($model, "'email', 'username', 'passwordHash', 'status']", "'email', 'username', 'passwordHash', 'status', 'rowVersion']");
[$rc, $t] = $run($fillable);
$say('version-made-mass-assignable', $rc === 1, "rc={$rc} Tests: {$t}");

// 6. a filter that selects nothing is never read as a kill
[$rc, $t] = $run('this_case_does_not_exist_at_all');
$say('bogus-filter-is-not-green', $t === 'no-tally' || str_contains($t, 'No tests'), "Tests: {$t}");

// 7. the tree is the bytes this run opened, measured after an explicit restore
$drift = [];
foreach ($original as $rel => $bytes) {
    $path = $root.'/'.$rel;
    if ((string) file_get_contents($path) !== $bytes) {
        @file_put_contents($path, $bytes);
    }
    if ((string) file_get_contents($path) !== $bytes) {
        $drift[] = $rel;
    }
}
[$rc, $t] = $run('');
$say('tree-restored-and-control-green-again', $drift === [] && $rc === 0,
    $drift === [] ? "restored; Tests: {$t}" : 'drift: '.implode(', ', $drift));

$bad = count(array_filter($legs, fn ($l) => ! $l[1]));
printf("\n%d leg(s), %d bad\n", count($legs), $bad);
exit($bad === 0 ? 0 : 1);
