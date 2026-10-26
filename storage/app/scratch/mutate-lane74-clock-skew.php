<?php

/**
 * Prove `lane74-probe-clock-skew.php` can fail, and can say the two things it exists to say.
 *
 * The probe was written against a box whose clock is nineteen hours fast, which means its "the clocks agree" branch
 * cannot be reached on this machine at all — and an unreachable branch in a measurement tool is the same liability as
 * an unread surface: the next person to run it on a correctly-set clock would be reading code that had never
 * executed. One leg therefore plants the agreement (a copy whose box epoch is set to the host's) and requires the
 * copy to print the agreement sentence and exit 0.
 *
 * The other legs each demand a REFUSAL with its own cause and a non-zero status, because `exit("text")` returns
 * status 0 and that is how a broken instrument reports success: no route, an HTTP status over 400, a header block
 * without a readable date, and an unknown flag refused by name. Status is read from exec()'s third argument, never
 * guessed from the text.
 *
 * Copies live in storage/app/scratch because the probe finds the project root by dirname(__DIR__, 3): a copy in a
 * temp directory resolves a root with no .env and quietly answers with its default zone, which a leg that greps for
 * REFUSED would read as a mutation. Every copy is unlinked in a finally, and the run says out loud how many are left.
 */
$root = dirname(__DIR__, 3);
$tool = $root.'/storage/app/scratch/lane74-probe-clock-skew.php';
$php = escapeshellarg(PHP_BINARY);

if (! is_readable($tool)) {
    fwrite(STDERR, "REFUSED: the probe is not readable at $tool.\n");
    exit(1);
}
$src = (string) file_get_contents($tool);

$copies = [];

$run = static function (string $script, string $args = '') use ($php, $root): array {
    exec('cd '.escapeshellarg($root).' && '.$php.' '.escapeshellarg($script).' '.$args.' 2>&1', $lines, $code);

    return ['out' => implode("\n", $lines), 'code' => $code];
};

$mutate = static function (string $name, string $body) use (&$copies, $root): string {
    $path = $root.'/storage/app/scratch/'.$name;
    file_put_contents($path, $body);
    $copies[] = $path;

    return $path;
};

$fails = 0;
$report = static function (string $leg, bool $ok, string $detail = '') use (&$fails): void {
    if (! $ok) {
        $fails++;
    }
    printf("%-8s %-26s %s\n", $ok ? 'PASS' : 'BAD', $leg, $detail);
};

try {
    // 1 — control: the real probe, on the real clock, must measure and exit 0.
    $r = $run($tool);
    $verdict = str_contains($r['out'], 'AHEAD of the host') || str_contains($r['out'], 'BEHIND')
        || str_contains($r['out'], 'the same minute');
    $report('control', $verdict && $r['code'] === 0, sprintf('exit %d, verdict line %s', $r['code'],
        $verdict ? 'present' : 'ABSENT — the probe measured nothing'));
    if (! $verdict) {
        echo preg_replace('/\n+/', "\n", trim($r['out']))."\n";
    }

    // 2 — the agreement branch, reachable on this box only by planting a clock equal to the host's.
    $from = '$skew = $before - $external;';
    if (substr_count($src, $from) !== 1) {
        $report('agree-branch', false, 'the anchor line is gone or doubled, so the agreement cannot be planted');
    } else {
        $p = $mutate('lane74-clock-skew-AGREE.php', str_replace($from, '$before = $external; '.$from, $src));
        $r = $run($p);
        $report('agree-branch', str_contains($r['out'], 'the same minute') && $r['code'] === 0,
            sprintf('exit %d, expected the agreement sentence and 0', $r['code']));
    }

    // 3 — a host that answers nothing must refuse by cause, with a non-zero status.
    $r = $run($tool, '--timeout=0.05');
    $report('refuses-no-route', str_contains($r['out'], 'REFUSED') && str_contains($r['out'], 'curl error')
        && $r['code'] === 1, sprintf('exit %d, message %s', $r['code'],
            str_contains($r['out'], 'REFUSED') ? 'names the curl error' : 'did not refuse'));

    // 4 — an HTTP error page is not an answer about time.
    $r = $run($tool, '--path=/this-path-404s');
    $report('refuses-http-status', str_contains($r['out'], 'REFUSED') && (bool) preg_match('/HTTP 4\d\d/', $r['out'])
        && $r['code'] === 1, sprintf('exit %d, names the status: %s', $r['code'],
            preg_match('/HTTP 4\d\d/', $r['out'], $m) ? $m[0] : 'no status printed'));

    // 5 — a header block with no readable date must refuse rather than report agreement.
    $needle = '/^date:\\s*(.+)$/i';
    if (substr_count($src, $needle) !== 1) {
        $report('refuses-no-date', false, 'the Date regex could not be located to mute it');
    } else {
        $p = $mutate('lane74-clock-skew-NODATE.php', str_replace($needle, '/^no-such-header: x$/i', $src));
        $r = $run($p);
        $report('refuses-no-date', str_contains($r['out'], 'no readable date') && $r['code'] === 1,
            sprintf('exit %d', $r['code']));
    }

    // 6 — an unknown flag is refused by name, and the refusal carries a status.
    $r = $run($tool, '--ci');
    $report('refuses-unknown-flag', str_contains($r['out'], 'not a flag this tool reads') && $r['code'] === 1,
        sprintf('exit %d — exit("text") would have answered 0', $r['code']));

    // 7 — a byte-identical copy must answer like the control, which is what proves the copies are read at all.
    $p = $mutate('lane74-clock-skew-SAME.php', $src);
    $r = $run($p);
    $same = str_contains($r['out'], 'AHEAD of the host') && $r['code'] === 0;
    $report('byte-identical', $same, sprintf('exit %d, unchanged verdict: %s', $r['code'],
        $same ? 'yes' : 'no'));
} finally {
    foreach ($copies as $c) {
        if (is_file($c)) {
            unlink($c);
        }
    }
    $left = array_values(array_filter(glob($root.'/storage/app/scratch/lane74-clock-skew-*.php') ?: [],
        static fn (string $p): bool => basename($p) !== 'lane74-probe-clock-skew.php'));
    printf("\n%s copy(ies) left in scratch%s\n", $left === [] ? 'no' : 'ORPHAN ',
        $left === [] ? '' : ': '.implode(', ', array_map('basename', $left)));
    if ($left !== []) {
        $fails++;
    }
}

printf("%d leg(s) failed.\n", $fails);
echo $fails === 0
    ? "VERDICT: both verdicts and all four refusals fire on command, so a date this probe reads is a measured one.\n"
    : "VERDICT: the clock probe is not proven — fix it before quoting any date it printed.\n";
exit($fails === 0 ? 0 : 1);
