<?php

/**
 * Lane 42 mutant harness — proves `lane42-assessment-claims.php` can actually fail.
 *
 * The checker reads the two assessment files, live `ems`, and the code on disk, so the mutants are planted
 * in the real tree and restored immediately. Every restore is verified against the hash taken before the
 * plant, and a mismatch aborts the run rather than leaving a mutant behind.
 *
 * Each case names the claim class it must break, and one case is deliberately the opposite: an unreadable
 * claim must refuse the run (UNBOUND) rather than report clean.
 *
 * Usage: php storage/app/scratch/mutate-lane42-assessment-claims.php
 */

$root = dirname(__DIR__, 3);
$checker = $root.'/storage/app/scratch/lane42-assessment-claims.php';
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';

if (! is_file($checker)) {
    fwrite(STDERR, "no checker at {$checker}\n");
    exit(1);
}

$report = $root.'/SEAIT-EMS-Round3-Current-State.md';
$authRoutes = $root.'/routes/auth.php';

/** Run the checker and return [exitCode, output]. */
$run = function () use ($php, $checker, $root): array {
    $out = [];
    $code = 0;
    exec('cd /d '.escapeshellarg($root).' && '.escapeshellarg($php).' '.escapeshellarg($checker).' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};

$failures = 0;

$case = function (string $name, string $expectNeedle, callable $plant, callable $unplant) use ($run, $report, $authRoutes, &$failures): void {
    $targets = [$report, $authRoutes];
    $hashes = [];
    foreach ($targets as $t) {
        if (is_file($t)) {
            $hashes[$t] = hash_file('sha256', $t);
        }
    }

    try {
        $plant();
        [$code, $out] = $run();
    } finally {
        $unplant();
    }

    $restored = [];
    foreach ($hashes as $t => $before) {
        $after = is_file($t) ? hash_file('sha256', $t) : '(absent)';
        if ($after !== $before) {
            $restored[] = basename($t).' left '.$after.' expected '.$before;
        }
    }

    $caught = $code !== 0 && str_contains($out, $expectNeedle);

    printf(
        "  %s %-42s exit=%d  needle %-28s %s%s\n",
        $caught && $restored === [] ? 'ok  ' : 'BAD ',
        $name,
        $code,
        $caught ? 'found' : 'MISSING',
        $caught ? '' : ' — checker stayed silent on a planted defect',
        $restored === [] ? '' : '  NOT RESTORED: '.implode('; ', $restored)
    );

    if (! $caught || $restored !== []) {
        $failures++;
    }
};

echo "=== baseline: the unmutated document must be unflagged ===\n";
[$code, $out] = $run();
printf("  %s clean run exit=%d\n", $code === 0 ? 'ok  ' : 'BAD ', $code);
if ($code !== 0) {
    echo "\n".$out."\n";
    fwrite(STDERR, "\nbaseline is not green — fix the checker or the documents before planting mutants\n");
    exit(1);
}

echo "\n=== planted defects, each in the real tree, each restored after ===\n";

// 1. An un-dated present-tense count that no longer matches the build.
$case(
    'stale undated counts',
    'permissions count',
    function () use ($report): void {
        file_put_contents($report, "\nA later build exposes 41 permissions, 99 tables and 7 controllers.\n", FILE_APPEND);
    },
    function () use ($report): void {
        $t = (string) file_get_contents($report);
        $t = str_replace("\nA later build exposes 41 permissions, 99 tables and 7 controllers.\n", '', $t);
        file_put_contents($report, $t);
    }
);

// 2. A row that says the shipped CSP is still open, with no date on it.
$case(
    'shipped CSP re-opened, undated',
    'CSP row',
    function () use ($report): void {
        file_put_contents($report, "\n| Content-Security-Policy header | Original, Medium | 📋 Still open | planted |\n", FILE_APPEND);
    },
    function () use ($report): void {
        $t = (string) file_get_contents($report);
        $t = str_replace("\n| Content-Security-Policy header | Original, Medium | 📋 Still open | planted |\n", '', $t);
        file_put_contents($report, $t);
    }
);

// 3. The very row the report corrected, restored verbatim — the wording the checker was written for.
$case(
    'original CSP row pasted back',
    'calls it open',
    function () use ($report): void {
        file_put_contents($report, "\n| Content-Security-Policy header | Original, Medium | 📋 Still open | Reasonable to keep deferring — CSP is easy to get wrong |\n", FILE_APPEND);
    },
    function () use ($report): void {
        $t = (string) file_get_contents($report);
        $t = str_replace("\n| Content-Security-Policy header | Original, Medium | 📋 Still open | Reasonable to keep deferring — CSP is easy to get wrong |\n", '', $t);
        file_put_contents($report, $t);
    }
);

// 4. Open self-registration coming back on the route file the report says is clean.
$case(
    '/register returns to routes/auth.php',
    '/register absent',
    function () use ($authRoutes): void {
        file_put_contents($authRoutes, "\n// planted: Route::get('register') came back\n", FILE_APPEND);
    },
    function () use ($authRoutes): void {
        $t = (string) file_get_contents($authRoutes);
        file_put_contents($authRoutes, str_replace("\n// planted: Route::get('register') came back\n", '', $t));
    }
);

// 5. A bare office-id integer appearing in app/ — the magic-number class the report says is gone.
$case(
    'raw office-id integer in app/',
    'zero raw office-id integers',
    function () use ($root): void {
        file_put_contents($root.'/app/Support/Lane42Mutant.php', "<?php\n\nreturn ['officeId' => 7];\n");
    },
    function () use ($root): void {
        @unlink($root.'/app/Support/Lane42Mutant.php');
    }
);

// 6. A file the audit says was deleted coming back.
$case(
    'deleted User model restored',
    'User stays deleted',
    function () use ($root): void {
        file_put_contents($root.'/app/Models/User.php', "<?php\n\nnamespace App\\Models;\n\nclass User {}\n");
    },
    function () use ($root): void {
        @unlink($root.'/app/Models/User.php');
    }
);

// 7. The refusal case: a claim the checker can no longer read must not look like a clean run.
$case(
    'tables claims made unreadable',
    'UNBOUND',
    function () use ($report): void {
        $t = (string) file_get_contents($report);
        file_put_contents($report, str_replace(' tables', ' table rows', $t));
    },
    function () use ($report): void {
        $t = (string) file_get_contents($report);
        file_put_contents($report, str_replace(' table rows', ' tables', $t));
    }
);

echo "\n=== final: unmutated again, and nothing left behind ===\n";
[$code, $out] = $run();
printf("  %s clean run exit=%d\n", $code === 0 ? 'ok  ' : 'BAD ', $code);
if ($code !== 0) {
    echo $out."\n";
    $failures++;
}

foreach ([$root.'/app/Support/Lane42Mutant.php', $root.'/app/Models/User.php'] as $leftover) {
    if (file_exists($leftover)) {
        printf("  BAD  mutant left behind: %s\n", $leftover);
        $failures++;
    }
}

printf("\n  %d case(s) failed.\n", $failures);

exit($failures === 0 ? 0 : 1);
