<?php

/**
 * Prove that the two G-13 cases can fail, and that every branch of the Forwarded scheme row is load-bearing.
 *
 * The row was written for one register item the software cannot close by itself, so the risk is a check that
 * always prints the same sentence and reads as an answer. G-13 became a setting on 2026-10-26, which added a second
 * risk of the same family: a file named `config/trustedproxy.php` that nothing reads, or that reads a different env
 * name from the one the documents tell an installer to type. So this harness mutates two targets — the row, and the
 * config file — and each leg requires the case that owns that behaviour to go RED, by its own name.
 *
 * The shapes planted here were measured first through the real middleware in
 * storage/app/scratch/lane73b-probe-proxy-shapes.php: a comma-separated string, a bare star, a star inside a list,
 * a hostname, an out-of-range CIDR mask, and a range keyword. A mutant aimed at a shape nobody has verified is a
 * mutant aimed at the harness's own guess.
 *
 * An exit code cannot be read back through cmd.exe, so every leg asserts on the printed tally.
 *
 *   php storage/app/scratch/mutate-lane73-preflight-proxy.php
 */
$root = dirname(__DIR__, 3);
$php = 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$rowCase = 'the_forwarded_scheme_row_decides';
$fileCase = 'the_trusted_proxy_config_file_answers';

$targets = [
    'row' => $root.'/app/Console/Commands/Preflight.php',
    'config' => $root.'/config/trustedproxy.php',
];

$original = [];
foreach ($targets as $key => $path) {
    $original[$key] = (string) file_get_contents($path);
}

$fails = 0;

// One case per run. An alternation in --filter cannot be quoted through cmd.exe without the shell eating the `|`
// and handing the second name to itself, which is the shape of a leg that measures nothing: this harness's first
// version did exactly that and reported "(no tally printed)" as a control failure.
$run = static function (string $case) use ($php, $root): string {
    // Colour codes are written into the captured output (`\e[39;1mTests:\e[39m    1 passed`), which breaks both the
    // tally regex and any needle that spans a styled word. Every harness that reads a suite through shell_exec has
    // to strip them first, or a green run reads as "(no tally printed)" — which is this harness's own first failure.
    $raw = (string) shell_exec('cd '.escapeshellarg($root).' && '.$php.' artisan test '
        .'tests/Feature/Console/PreflightTest.php --filter '.$case.' 2>&1');

    return (string) preg_replace('/\e\[[0-9;]*m/', '', $raw);
};

$tally = static function (string $out): string {
    return preg_match('/Tests:\s+(.*)/u', $out, $m) === 1 ? trim($m[1]) : '(no tally printed)';
};

$green = static function (string $out) use ($tally): bool {
    return str_contains($tally($out), '1 passed');
};

$check = static function (string $name, bool $ok, string $detail) use (&$fails): void {
    printf("  %s %s%s\n", $ok ? 'PASS' : 'BAD ', $name, $detail === '' ? '' : ' — '.$detail);
    if (! $ok) {
        $fails++;
    }
};

/** @var array<string, array{0: string, 1: string, 2: string, 3: string}> label => [target, needle, replacement, case] */
$plants = [
    'a-comma-string-stops-being-read' => [
        'row',
        "is_string(\$configured) && trim(\$configured) !== '' => array_map('trim', explode(',', \$configured)),",
        "is_string(\$configured) && false => array_map('trim', explode(',', \$configured)),",
        $rowCase,
    ],
    'a-bare-star-reads-as-an-address' => [
        'row',
        "if (\$configured === '*' || \$configured === '**') {",
        'if (false) {',
        $rowCase,
    ],
    'a-value-that-matches-nothing-is-counted' => [
        'row',
        'if ($unmatchable !== []) {',
        'if (false) {',
        $rowCase,
    ],
    'a-range-keyword-reads-as-one-machine' => [
        'row',
        'if (in_array($loose, $entries, true)) {',
        'if (false) {',
        $rowCase,
    ],
    'any-cidr-mask-is-accepted' => [
        'row',
        'return ($isV4 && (int) $parts[1] <= 32) || ($isV6 && (int) $parts[1] <= 128);',
        'return $isV4 || $isV6;',
        $rowCase,
    ],
    'the-cached-config-step-is-dropped' => [
        'row',
        ".'then php artisan config:cache if §3 cached the config; DEPLOYMENT.md §2, G-13'];",
        ".'DEPLOYMENT.md §2, G-13'];",
        $rowCase,
    ],
    'the-setting-is-named-nowhere-in-the-advice' => [
        'row',
        ".'if a TLS terminator is ever put in front, name its address in TRUSTED_PROXIES '",
        ".'if a TLS terminator is ever put in front, name its address '",
        $rowCase,
    ],
    'the-row-is-renamed' => [
        'row',
        "return ['Forwarded scheme', 'WARN', \$url.' is https",
        "return ['Proxy note', 'WARN', \$url.' is https",
        $rowCase,
    ],
    'the-row-is-not-printed-at-all' => [
        'row',
        '$this->forwardedSchemeIsDecided(),',
        '',
        $rowCase,
    ],
    'the-config-file-reads-a-different-env-name' => [
        'config',
        "env('TRUSTED_PROXIES') ?: null",
        "env('TRUSTED_PROXY') ?: null",
        $fileCase,
    ],
    'a-blank-setting-becomes-the-empty-pattern' => [
        'config',
        "env('TRUSTED_PROXIES') ?: null",
        "env('TRUSTED_PROXIES')",
        $fileCase,
    ],
];

$restore = static function () use ($targets, $original): void {
    foreach ($targets as $key => $path) {
        if ((string) file_get_contents($path) !== $original[$key]) {
            file_put_contents($path, $original[$key]);
        }
    }
};

try {
    // The control must be green in both cases, or nothing below means anything.
    foreach ([$rowCase, $fileCase] as $case) {
        $out = $run($case);
        $check("control: {$case}", $green($out), $tally($out));
    }

    if ($fails > 0) {
        printf("\nREFUSED: the control is not green (or no run was measured), so a red leg would prove nothing about its mutant.\n");
        exit(1);
    }

    foreach ($plants as $name => [$target, $needle, $replacement, $owningCase]) {
        if (! str_contains($original[$target], $needle)) {
            $check($name, false, 'the planted needle matches nothing in '.$target.' — this leg is asleep');

            continue;
        }

        $mutated = str_replace($needle, $replacement, $original[$target]);

        if ($mutated === $original[$target]) {
            $check($name, false, 'the mutation left the file byte-identical — it would measure the unmutated build');

            continue;
        }

        file_put_contents($targets[$target], $mutated);
        $out = $run($owningCase);
        // Pest prints the case with its underscores turned into spaces, so that is the form to look for. Requiring
        // "1 failed" rather than merely "not passed" is what keeps a mutant that does not compile from being
        // credited as a killed defect: the case has to have run and answered.
        $red = str_contains($tally($out), '1 failed');
        $named = str_contains($out, str_replace('_', ' ', $owningCase));
        $check($name.' makes '.$owningCase.' red', $red && $named,
            $tally($out).($red ? '' : ' (the run was not red)').($named ? '' : ' (and it does not name the case it was supposed to kill)'));

        $restore();
    }

    // A byte-identical restore must answer exactly like the control.
    $same = true;
    $backToGreen = true;
    foreach ($targets as $key => $path) {
        $same = $same && (string) file_get_contents($path) === $original[$key];
    }
    foreach ([$rowCase, $fileCase] as $case) {
        $backToGreen = $backToGreen && $green($run($case));
    }
    $check('both files restore byte-identical and green again', $same && $backToGreen,
        'sha1 '.substr(sha1($original['row'].$original['config']), 0, 8));
} finally {
    $restore();
}

printf("\n%s\n", $fails === 0
    ? 'every branch of the row and both lines of the config file are load-bearing, and the tree is back as it was'
    : $fails.' leg(s) BAD');
exit($fails === 0 ? 0 : 1);
