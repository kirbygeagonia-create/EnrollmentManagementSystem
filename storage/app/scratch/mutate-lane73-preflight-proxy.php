<?php

/**
 * Prove that PreflightTest's forwarded-scheme case can fail, and that each branch of the row is load-bearing.
 *
 * The row was written for one register item (G-13) that the software cannot close by itself, so the risk is a
 * check that always prints the same sentence and reads as an answer. Each leg therefore plants a real shape of
 * that failure in the working file, runs the case that names it, and requires it to go RED — then restores the
 * bytes unconditionally, because a half-swapped command file makes every later verdict in this session a lie
 * (lane 57's `finally`, lane 55's restore-by-hash).
 *
 * An exit code cannot be read back through cmd.exe, so every leg asserts on the printed tally.
 *
 *   php storage/app/scratch/mutate-lane73-preflight-proxy.php
 */
$root = dirname(__DIR__, 3);
$php = 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$target = $root.'/app/Console/Commands/Preflight.php';
$filter = 'the_forwarded_scheme_row_decides_the_proxy_question_instead_of_asking_the_installer_to_remember_it';
$fails = 0;

$original = (string) file_get_contents($target);
$hash = sha1($original);

$run = static function () use ($php, $root, $filter): string {
    return (string) shell_exec('cd '.escapeshellarg($root).' && '.$php.' artisan test tests/Feature/Console/PreflightTest.php --filter '.$filter.' 2>&1');
};
$red = static function (string $out): bool {
    return str_contains($out, 'FAILED') || str_contains($out, 'failed') || ! str_contains($out, '1 passed');
};

$check = static function (string $name, bool $ok, string $detail) use (&$fails): void {
    printf("  %s %s%s\n", $ok ? 'PASS' : 'BAD ', $name, $detail === '' ? '' : ' — '.$detail);
    if (! $ok) {
        $fails++;
    }
};

/** @var array<string, array{0: string, 1: string}> label => [needle, replacement] */
$plants = [
    'https-without-proxy-stops-warning' => [
        "if (\$expectsHttps) {\n            return ['Forwarded scheme', 'WARN',",
        "if (false) {\n            return ['Forwarded scheme', 'WARN',",
    ],
    'wildcard-reads-as-named' => [
        "if (in_array('*', \$proxies, true) || in_array('**', \$proxies, true)) {",
        'if (false) {',
    ],
    'row-renamed' => [
        "return ['Forwarded scheme', 'WARN', \$url.' is https",
        "return ['Proxy note', 'WARN', \$url.' is https",
    ],
    'instruction-pointer-dropped' => [
        ".'if a TLS terminator is ever put in front, name its address (DEPLOYMENT.md §2, G-13)'",
        ".'if a TLS terminator is ever put in front, name its address'",
    ],
    'row-never-printed' => [
        '$this->forwardedSchemeIsDecided(),',
        '',
    ],
];

try {
    // 1 — the control must be green, or nothing below means anything.
    $out = $run();
    $check('control', ! $red($out), (string) (preg_match('/Tests:\s+(.*)/u', $out, $m) ? trim($m[1]) : substr($out, 0, 60)));
    if ($fails > 0) {
        printf("\nREFUSED: the control is not green, so a red leg would prove nothing about its mutant.\n");
        exit(1);
    }

    foreach ($plants as $name => [$needle, $replacement]) {
        if (! str_contains($original, $needle)) {
            $check($name, false, 'the planted needle matches nothing in the current file — this leg is asleep');

            continue;
        }
        file_put_contents($target, str_replace($needle, $replacement, $original));
        $out = $run();
        $check($name.' makes the named case red', $red($out),
            (string) (preg_match('/Tests:\s+(.*)/u', $out, $m) ? trim($m[1]) : substr($out, 0, 60)));
        file_put_contents($target, $original);
    }

    // 6 — a byte-identical restore must answer exactly like the control.
    $out = $run();
    $check('restore is byte-identical and green again', ! $red($out) && sha1((string) file_get_contents($target)) === $hash,
        'sha1 '.substr($hash, 0, 8));
} finally {
    if (file_get_contents($target) !== $original) {
        file_put_contents($target, $original);
        echo "  (the tree was not clean when this harness stopped; the bytes were restored in the finally)\n";
    }
}

printf("\n%s\n", $fails === 0 ? 'every branch of the row is load-bearing, and the file is back as it was'
    : "$fails leg(s) BAD");
exit($fails === 0 ? 0 : 1);
