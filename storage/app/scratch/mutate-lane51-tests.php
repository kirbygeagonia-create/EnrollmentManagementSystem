<?php

// Lane 51: proves the tests fail for the reason they exist. Each case puts one pre-fix rule back into the real
// source, runs the named test, and requires *that* test to go red — then restores the file unconditionally.
// A mutant that does not apply prints a pass, so every planting is checked against the pristine bytes first.
//
//   php storage/app/scratch/mutate-lane51-tests.php

$root = dirname(__DIR__, 3);
$php = PHP_BINARY;

$targets = [
    'app/Http/Controllers/Admin/UserManagementController.php',
    'app/Http/Controllers/Accounting/AccountingController.php',
    'app/Http/Controllers/DashboardController.php',
    'app/Models/Academicterms.php',
    'app/Support/SchoolTime.php',
    'config/school.php',
    'resources/js/Components/ui/calendarDay.js',
];

$originals = [];

foreach ($targets as $rel) {
    if (! is_file($root.'/'.$rel)) {
        fwrite(STDERR, "REFUSE: missing $rel\n");
        exit(1);
    }

    $originals[$rel] = file_get_contents($root.'/'.$rel);
}

$restore = function () use ($root, $originals): void {
    foreach ($originals as $rel => $bytes) {
        if (file_get_contents($root.'/'.$rel) !== $bytes) {
            file_put_contents($root.'/'.$rel, $bytes);
        }
    }
};

register_shutdown_function(function () use ($restore, $root, $originals): void {
    $restore();

    foreach ($originals as $rel => $bytes) {
        if (file_get_contents($root.'/'.$rel) !== $bytes) {
            fwrite(STDERR, "FINAL RESTORE FAILED: $rel is still mutated\n");
            exit(1);
        }
    }

    echo "\ntree restored byte-for-byte (".count($originals)." files verified).\n";
});

$runFilter = function (string $filter) use ($root, $php): array {
    chdir($root);
    // An empty filter means "the whole file" — `--filter ''` is not how you ask for that.
    $cmd = escapeshellarg($php).' artisan test tests/Feature/SchoolTimeTest.php'
        .($filter === '' ? '' : ' --filter '.escapeshellarg($filter)).' 2>&1';
    $out = shell_exec($cmd) ?? '';
    $summary = preg_replace('/\e\[[0-9;]*m/', '', $out);

    $sawFailed = preg_match('/Tests:\s*([0-9,]+)\s+failed.*?\(([0-9,]+) assertions?\)/', $summary, $f);
    $sawPassed = preg_match('/Tests:\s*([0-9,]+)\s+passed/', $summary, $p);

    // No summary line means the command never ran. An unrun suite is not a green one, and a mutant that
    // "survives" because nothing executed is how a harness sleeps through its own cases.
    if (! $sawFailed && ! $sawPassed) {
        fwrite(STDERR, "REFUSE: the suite printed no \"Tests:\" line — the command did not run.\n");
        fwrite(STDERR, mb_substr($summary, 0, 500)."\n");
        exit(1);
    }

    return [
        'failed' => isset($f[1]) ? (int) str_replace(',', '', $f[1]) : 0,
        'passed' => isset($p[1]) ? (int) str_replace(',', '', $p[1]) : 0,
        'out' => $summary,
    ];
};

$plant = function (string $rel, string $from, string $to) use ($root, $originals): void {
    if (substr_count($originals[$rel], $from) !== 1) {
        fwrite(STDERR, sprintf("NOT PLANTED: %s — anchor matched %d times in the pristine file\n", $rel, substr_count($originals[$rel], $from)));
        exit(1);
    }

    $mutated = str_replace($from, $to, $originals[$rel]);

    if ($mutated === $originals[$rel]) {
        fwrite(STDERR, "NOT PLANTED: $rel is byte-identical after the mutation\n");
        exit(1);
    }

    file_put_contents($root.'/'.$rel, $mutated);
};

$fails = 0;

$case = function (string $name, string $rel, string $from, string $to, string $filter) use (&$fails, $plant, $restore, $runFilter): void {
    $restore();
    $plant($rel, $from, $to);
    $r = $runFilter($filter);
    $ok = $r['failed'] >= 1;

    printf("  %-6s %-52s %s\n", $ok ? 'ok' : 'FAIL', $name, $ok
        ? "the named test went red ({$r['failed']} failed)"
        : 'SURVIVED — the suite stayed green ('.$r['passed'].' passed)');

    if (! $ok) {
        $fails++;
    }

    $restore();
};

echo "Baseline — every case must be green before any mutant is believed.\n";
$all = $runFilter('');
printf("  %-6s %-52s %s\n", $all['failed'] === 0 ? 'ok' : 'FAIL', 'clean tree', "failed={$all['failed']} passed={$all['passed']}");

if ($all['failed'] !== 0) {
    fwrite(STDERR, "REFUSE: the tree is not green before mutating — nothing below would mean anything\n");
    exit(1);
}

echo "\nMutants — one pre-fix rule at a time.\n";

$case('the audit filter cut at UTC midnight again',
    'app/Http/Controllers/Admin/UserManagementController.php',
    "->when(SchoolTime::dayOrNull(\$request->dateFrom), fn (\$q, \$date) => \$q->where('createdAt', '>=', SchoolTime::startOfSchoolDay(\$date)))",
    "->when(\$request->dateFrom, fn (\$q, \$date) => \$q->whereDate('createdAt', '>=', \$date))",
    'the_audit_logs_date_filter_returns_the_days_the_officer_asked_for_in_their_own_calendar');

$case('the term window asked UTC what day it was',
    'app/Models/Academicterms.php',
    'SchoolTime::today() : SchoolTime::civilDateOf($on)',
    'now()->toDateString() : SchoolTime::civilDateOf($on)',
    'a_term_that_opens_on_the_schools_today_is_open_even_though_utc_still_calls_it_yesterday');

$case('a school day opened at UTC midnight',
    'app/Support/SchoolTime.php',
    "new DateTimeZone(self::zone())\n        )->setTimezone('UTC');",
    "new DateTimeZone('UTC')\n        )->setTimezone('UTC');",
    'a_school_day_opens_at_sixteen_hundred_utc_the_day_before');

$case('the calendar guard went back to shape-only',
    'app/Support/SchoolTime.php',
    '$value, $m) === 1'."\n".'            && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);',
    '$value, $m) === 1;',
    'an_impossible_date_is_refused_rather_than_rolled_into_a_real_one');

$case('the school zone was never declared',
    'config/school.php',
    "env('SCHOOL_TIMEZONE', 'Asia/Manila')",
    "env('SCHOOL_TIMEZONE', 'UTC')",
    'the_zone_is_declared_once_and_the_screen_reads_the_same_one_the_server_uses');

$case("the browser's formatter drifted from the server's",
    'resources/js/Components/ui/calendarDay.js',
    "export const SCHOOL_TIMEZONE = 'Asia/Manila';",
    "export const SCHOOL_TIMEZONE = 'UTC';",
    'the_zone_is_declared_once_and_the_screen_reads_the_same_one_the_server_uses');

// Not a case on purpose: the refund sheet and the dashboard tiles use the same two bounds this file proves
// through the audit filter, but neither has a screen test of its own, so claiming a mutant for them would
// assert coverage that does not exist.

printf("\n%s\n", $fails === 0 ? 'Every rule lane 51 installed, a test can see again.' : "$fails mutant(s) survived.");
exit($fails === 0 ? 0 : 1);
