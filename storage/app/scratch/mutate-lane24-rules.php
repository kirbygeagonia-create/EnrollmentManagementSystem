<?php

/**
 * Prove the three lane-24 rules can actually fail.
 *
 * Each mutant deletes one branch of one check, runs the single PreflightTest row that branch is
 * there to serve, and requires that row to go RED. A rule that keeps passing after its own
 * condition is removed is a test of the test, not of the server — and both new checks are of the
 * "refuse a configuration" kind, which is exactly where an always-OK bug hides.
 *
 * Every mutant asserts it landed before it is trusted: the source is re-read and compared to the
 * baseline, so a find-string that matches nothing (the failure mode this harness has hit twice)
 * reports NOT PLANTED instead of a false "caught".
 */

$root = dirname(__DIR__, 3);
$target = $root.'/app/Console/Commands/Preflight.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';

$baseline = file_get_contents($target);

$mutants = [
    [
        'name' => 'M1  the blank-key branch never fires',
        'find' => "        if (\$key === '') {",
        'fix' => "        if (false) {",
        'test' => 'a_server_that_never_generated_its_key_fails_and_names_the_command',
    ],
    [
        'name' => 'M2  the key length is never checked',
        'find' => "        if (! is_string(\$raw) || strlen(\$raw) !== 32) {",
        'fix' => "        if (false) {",
        'test' => 'a_key_of_the_wrong_length_fails_because_the_cipher_would_refuse_the_first_login',
    ],
    [
        'name' => 'M3  a public served disk is not noticed',
        'find' => "            if ((\$disk['visibility'] ?? 'private') === 'public') {",
        'fix' => "            if (false) {",
        'test' => 'a_served_disk_declared_public_fails_because_that_line_switches_the_gate_off',
    ],
];

$missed = [];

foreach ($mutants as $m) {
    if (substr_count($baseline, $m['find']) !== 1) {
        echo "\n".$m['name']." → NOT PLANTED (find string matched ".substr_count($baseline, $m['find'])." times)\n";
        $missed[] = $m['name'].' (not planted)';
        continue;
    }

    file_put_contents($target, str_replace($m['find'], $m['fix'], $baseline));
    clearstatcache(true, $target);
    if (file_get_contents($target) === $baseline) {
        echo "\n".$m['name']." → NOT PLANTED (write did not change the file)\n";
        $missed[] = $m['name'].' (write refused)';
        continue;
    }

    exec(sprintf('%s %s/artisan test tests/Feature/Console/PreflightTest.php --filter=%s 2>&1',
        escapeshellarg($php), escapeshellarg($root), escapeshellarg($m['test'])), $lines, $code);
    $out = implode("\n", $lines);
    $lines = [];

    $caught = $code !== 0;
    echo "\n".$m['name']."\n  exit=$code → ".($caught ? 'CAUGHT' : '*** SURVIVED ***')."\n";
    foreach (array_slice(preg_split('/\R/', $out), -6) as $l) {
        if (trim($l) !== '') {
            echo '  | '.$l."\n";
        }
    }
    if (! $caught) {
        $missed[] = $m['name'];
    }

    file_put_contents($target, $baseline);
}

file_put_contents($target, $baseline);
if (file_get_contents($target) !== $baseline) {
    echo "\nTHE FILE WAS NOT RESTORED — fix ".$target." by hand before anything else.\n";
    exit(1);
}

echo "\n== SUMMARY ==\n";
echo $missed === []
    ? "  3/3 mutants caught: each new rule fails the moment its condition is removed.\n"
    : '  SURVIVORS: '.implode('; ', $missed)."\n";
exit($missed === [] ? 0 : 1);
