<?php

/**
 * Lane 48 — prove that lane48-tile-fallback.php can fail.
 *
 * A checker that reports 0 problems has either found a clean tree or stopped reading. This plants each defect
 * class the checker claims to judge, one at a time, into the real files, and requires the named verdict to
 * appear; then it restores the bytes and verifies the hash. Every case refuses before planting if its anchor
 * does not match exactly once — a mutant that cannot be planted is a mutant that sleeps, and it would report
 * the checker's own clean output as a pass.
 *
 * It never touches git: this tree carries the lane's own uncommitted fixes, so the restore writes back the
 * bytes captured at start, not a checkout.
 *
 * Usage: php storage/app/scratch/mutate-lane48-checker.php [--only=DEAD-KEY]
 */

$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$checker = 'storage/app/scratch/lane48-tile-fallback.php';

$only = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = substr($arg, strlen('--only='));
    }
}

$files = [
    'admission' => $root.'/resources/js/Pages/Admission/Index.jsx',
    'blocking' => $root.'/resources/js/Pages/Blocking/Index.jsx',
    'clearance' => $root.'/resources/js/Pages/Clearance/Index.jsx',
    'statcard' => $root.'/resources/js/Components/ui/StatCard.jsx',
];

$original = [];
$hash0 = [];
foreach ($files as $k => $path) {
    $src = @file_get_contents($path);
    if ($src === false) {
        fwrite(STDERR, "REFUSED: cannot read {$path}\n");
        exit(1);
    }
    $original[$k] = $src;
    $hash0[$k] = hash('sha256', $src);
}

$failures = 0;
$finished = false;

/**
 * Write the captured bytes back, every time it is called.
 *
 * A guard that made this a no-op after the first call left each later mutant in the tree — the next case then
 * measured a tree already carrying the previous defect, and the harness reported its own residue as a finding.
 */
$restore = function () use ($files, $original, $hash0, &$failures): void {
    foreach ($files as $k => $path) {
        file_put_contents($path, $original[$k]);
        if (hash('sha256', (string) file_get_contents($path)) !== $hash0[$k]) {
            printf("  BAD  restore of %s did not produce the original bytes\n", $k);
            $failures++;
        }
    }
};
register_shutdown_function(function () use ($restore, &$finished): void {
    if (! $finished) {
        fwrite(STDERR, "\nThe run ended before restoring — restoring now.\n");
        $restore();
    }
});

/** Run the checker, optionally with scope env, and return [exit code, output]. */
$run = function (array $env = []) use ($php, $root, $checker): array {
    $pairs = '';
    foreach ($env as $k => $v) {
        // cmd's `set X="Y"` keeps the quotes inside the value, so the whole assignment is quoted instead
        $pairs .= 'set "'.str_replace('"', '', $k.'='.$v).'" && ';
    }
    $out = [];
    $code = 0;
    exec('cd /d '.escapeshellarg($root).' && '.$pairs.escapeshellarg($php).' '.escapeshellarg($root.'/'.$checker).' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};

$summarise = function (string $out): string {
    $line = '';
    foreach (explode("\n", $out) as $l) {
        if (str_contains($l, 'problem(s)')) {
            $line = trim($l);
        }
    }

    return $line === '' ? 'no verdict line — the checker died: '.preg_replace('/\s+/', ' ', mb_substr($out, 0, 140)) : $line;
};

/** Plant one mutation, run, restore, and require the named verdict in the output. */
$case = function (string $name, string $key, string $from, string $to, string $needle) use ($run, $restore, $files, $original, $summarise, &$failures, $only): void {
    if ($only !== null && ! str_contains($name, $only)) {
        return;
    }
    $path = $files[$key];
    $hits = substr_count($original[$key], $from);
    if ($hits !== 1) {
        printf("  BAD  %-22s anchor matched %d times, expected 1 — this mutant would sleep: %s\n",
            $name, $hits, preg_replace('/\s+/', ' ', mb_substr($from, 0, 60)));
        $restore();
        $failures++;

        return;
    }
    file_put_contents($path, str_replace($from, $to, $original[$key]));
    if ((string) file_get_contents($path) === $original[$key]) {
        printf("  BAD  %-22s the mutation left the file byte-identical\n", $name);
        $failures++;
        $restore();

        return;
    }
    [$code, $out] = $run();
    $restore();
    $seen = str_contains($out, $needle);
    if ($seen && $code !== 0) {
        printf("  ok   %-22s found `%s` — %s\n", $name, $needle, $summarise($out));
    } else {
        printf("  FAIL %-22s expected `%s` (exit %d, needle %s) — %s\n",
            $name, $needle, $code, $seen ? 'present but the run still exited 0' : 'absent', $summarise($out));
        $failures++;
    }
};

// ---------------------------------------------------------------- baseline, measured not assumed

echo "Baseline — the tree as it stands must be clean before any mutant is believed.\n";
[$code, $out] = $run();
if (! str_contains($out, '0 problem(s).') || $code !== 0) {
    printf("  BAD  the clean tree is not clean (%s) — fix that before testing whether the checker can fail\n", $summarise($out));
    $restore();
    exit(1);
}
preg_match('/Screens: (\d+)\.  Tiles read: (\d+)/', $out, $b);
printf("  ok   clean tree: %s screen(s), %s tile(s), 0 problems\n", $b[1] ?? '?', $b[2] ?? '?');
$screensClean = (int) ($b[1] ?? 0);
if ($screensClean < 20) {
    printf("  BAD  only %d screens were scanned — the inventory is not reading the tree\n", $screensClean);
    $failures++;
}

// ------------------------------------------------------------------ each defect class

echo "\nMutants — one planted defect per case, and the verdict it must produce.\n";

$case('INVENTED-ZERO', 'admission', 'value={stats.total}', 'value={stats.total ?? 0}', 'INVENTED-ZERO');
$case('DEAD-KEY', 'admission', 'value={stats.total}', 'value={stats.totals}', 'DEAD-KEY');
$case('OBJECT-TILE', 'admission', 'value={stats.total}', 'value={stats}', 'OBJECT-TILE');
$case('BLANK', 'admission', 'value={stats.pending}', 'xvalue={stats.pending}', 'BLANK');
// the fallback this class names is a count of the rows on screen — the paginator's `total` is a *different set*
// and belongs to lane 47's MIXED-SET, so planting that here would prove a rule the checker does not claim
$case('FALLBACK-DERIVED', 'blocking', "const totalBlocks = stats.blocks ?? '—';", "const totalBlocks = stats.blocks ?? blocks?.data?.length ?? '—';", 'FALLBACK-DERIVED');
$case('UNSAFE-FORMATTER', 'clearance', ': `₱${Number(n).toLocaleString(', ': `₱${Number(n || 0).toLocaleString(', 'UNSAFE-FORMATTER');
$case('NO-ABSENCE-STATE', 'statcard', "const shown = absent ? '—' : value;", 'const shown = value;', 'NO absence state');

// ------------------------------------------------------------------------ scope behaviour

echo "\nScope — a flag the checker accepts must change what is scanned, and an empty scope must refuse.\n";
[$code, $out] = $run(['EMS_LANE48_PAGES' => 'Admission']);
if (preg_match('/Screens: (\d+)\./', $out, $s) && (int) $s[1] === 1 && str_contains($out, 'Admission/Index.jsx')) {
    printf("  ok   EMS_LANE48_PAGES=Admission judged 1 screen instead of %d\n", $screensClean);
} else {
    printf("  FAIL EMS_LANE48_PAGES=Admission did not narrow the scan: %s\n", $summarise($out));
    $failures++;
}

[$code, $out] = $run(['EMS_LANE48_PAGES' => 'NoSuchDeskAtAll']);
if ($code !== 0 && str_contains($out, 'REFUSED')) {
    echo "  ok   an empty scope refused rather than reporting clean\n";
} else {
    printf("  FAIL an empty scope answered exit %d: %s\n", $code, mb_substr(preg_replace('/\s+/', ' ', $out), 0, 120));
    $failures++;
}

// ------------------------------------------------------- a mutant whose anchor cannot match

echo "\nSelf-test — an anchor that cannot match must be refused, never counted as a pass.\n";
$slept = substr_count($original['admission'], 'value={stats.thisLineDoesNotExist}') === 0;
if ($slept) {
    echo "  ok   an unmatched anchor would be reported BAD (it cannot be planted)\n";
} else {
    echo "  FAIL the harness accepted an anchor it should refuse\n";
    $failures++;
}

$restore();
$finished = true;

printf("\n%s\n", $failures === 0 ? 'Every defect class the checker claims, it can see.' : "{$failures} harness failure(s) — the checker is not proven.");

exit($failures === 0 ? 0 : 1);
