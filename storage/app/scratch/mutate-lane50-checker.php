<?php

// Proves lane50-date-day-shift.php can fail. Each case puts one defect back into the real tree, runs the
// checker, and requires it to name that defect — then restores the file byte-for-byte, unconditionally.
//
// Every expectation is compared against a baseline this run measures, never a number carried over from the
// last one: a mutation legitimately converts a compliant cell into a defect, so the coverage count must move.
//
//   php storage/app/scratch/mutate-lane50-checker.php

$root = dirname(__DIR__, 3);
$php = PHP_BINARY;
$checker = $root.'/storage/app/scratch/lane50-date-day-shift.php';

$targets = [
    'resources/js/Pages/Students/Show.jsx',
    'resources/js/Pages/Clearance/Periods.jsx',
    'resources/js/Pages/Admin/ReferenceData/Curriculums.jsx',
    'resources/js/Pages/Exam/Index.jsx',
];

// Refuse to start on a tree that does not carry the shape each case is about to break — a leftover mutant
// would be snapshotted as the original and every later case would measure the residue.
$mustContain = [
    'resources/js/Pages/Students/Show.jsx' => 'formatCalendarDay(student.birthdate)',
    'resources/js/Pages/Clearance/Periods.jsx' => 'const fmtDate = (d) => formatCalendarDay(',
    'resources/js/Pages/Admin/ReferenceData/Curriculums.jsx' => 'calendarYear(row.effectiveYear)',
    'resources/js/Pages/Exam/Index.jsx' => 'formatCalendarDay(row.examDate)',
];

foreach ($mustContain as $rel => $token) {
    if (! str_contains(file_get_contents($root.'/'.$rel), $token)) {
        fwrite(STDERR, "REFUSE: $rel does not hold \"$token\" — the tree already carries a mutant, or the fix is not in place.\n");
        exit(1);
    }
}

$originals = [];

foreach ($targets as $rel) {
    $originals[$rel] = file_get_contents($root.'/'.$rel);
}

$restore = function () use ($root, $originals): void {
    foreach ($originals as $rel => $bytes) {
        if (file_get_contents($root.'/'.$rel) !== $bytes) {
            file_put_contents($root.'/'.$rel, $bytes);
        }
    }
};

register_shutdown_function(function () use ($restore, $targets, $originals): void {
    $restore();
    $bad = [];

    foreach ($targets as $rel) {
        if (file_get_contents(dirname(__DIR__, 3).'/'.$rel) !== $originals[$rel]) {
            $bad[] = $rel;
        }
    }

    if ($bad !== []) {
        fwrite(STDERR, 'FINAL RESTORE FAILED — these files are still mutated: '.implode(', ', $bad)."\n");
        exit(1);
    }

    echo "\ntree restored byte-for-byte (".count($targets)." files verified).\n";
});

$run = function (?string $scope = null) use ($php, $checker): array {
    if ($scope === null) {
        putenv('EMS_LANE50_PAGES');
    } else {
        putenv('EMS_LANE50_PAGES='.$scope);
    }

    $cmd = implode(' ', [escapeshellarg($php), escapeshellarg($checker)]).' 2>&1';
    $lines = [];
    $code = 0;
    exec($cmd, $lines, $code);
    $out = implode("\n", $lines);
    preg_match('/Files read: (\d+)/', $out, $f);
    preg_match('/(\d+) problem\(s\)/', $out, $p);
    preg_match('/DAY-FORMATTER: (\d+)/', $out, $c);
    preg_match('/DAY-VIA-ZONE: (\d+)/', $out, $d);
    preg_match('/YEAR-VIA-ZONE: (\d+)/', $out, $y);
    preg_match('/UNBOUND: (\d+)/', $out, $u);

    return [
        'exit' => $code,
        'files' => isset($f[1]) ? (int) $f[1] : null,
        'problems' => isset($p[1]) ? (int) $p[1] : null,
        'formatter' => isset($c[1]) ? (int) $c[1] : null,
        'day' => isset($d[1]) ? (int) $d[1] : null,
        'year' => isset($y[1]) ? (int) $y[1] : null,
        'unbound' => isset($u[1]) ? (int) $u[1] : null,
        'out' => $out,
        'refused' => str_contains($out, 'REFUSE'),
    ];
};

$plant = function (string $rel, string $find, string $replace) use ($root, $originals): void {
    $path = $root.'/'.$rel;

    if (substr_count($originals[$rel], $find) !== 1) {
        fwrite(STDERR, sprintf("NOT PLANTED: %s — anchor matched %d times in the pristine file\n", $rel, substr_count($originals[$rel], $find)));
        exit(1);
    }

    $mutated = str_replace($find, $replace, $originals[$rel]);

    if ($mutated === $originals[$rel]) {
        fwrite(STDERR, "NOT PLANTED: $rel is byte-identical after the mutation\n");
        exit(1);
    }

    file_put_contents($path, $mutated);
};

$fail = 0;
$check = function (string $name, bool $ok, string $detail) use (&$fail): void {
    printf("  %-6s %-46s %s\n", $ok ? 'ok' : 'FAIL', $name, $detail);

    if (! $ok) {
        $fail++;
    }
};

echo "Baseline — the fixed tree must read clean, and its coverage is the number every case is compared to.\n";
$base = $run();
$check('clean tree', $base['problems'] === 0 && $base['exit'] === 0,
    sprintf('%d problems, %d cells on the formatter, %d files', (int) $base['problems'], (int) $base['formatter'], (int) $base['files']));

if ($base['formatter'] < 20) {
    $check('baseline coverage is real', false, "only {$base['formatter']} cells counted — the checker may be reading nothing");
    exit(1);
}

echo "\nMutants — each defect class, put back one at a time.\n";

$plant('resources/js/Pages/Students/Show.jsx',
    'formatCalendarDay(student.birthdate)',
    "new Date(student.birthdate).toLocaleDateString('en-PH')");
$r = $run();
$check('a day cell put back through a Date', $r['day'] >= 1 && str_contains($r['out'], 'Students/Show.jsx')
    && $r['formatter'] === $base['formatter'] - 1,
    sprintf("saw %d DAY-VIA-ZONE, formatter %d of %d", (int) $r['day'], (int) $r['formatter'], (int) $base['formatter']));
$restore();

$plant('resources/js/Pages/Clearance/Periods.jsx',
    "const fmtDate = (d) => formatCalendarDay(d, { year: 'numeric', month: 'short', day: '2-digit' });",
    "const fmtDate = (d) => (d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: '2-digit' }) : '—');");
$r = $run();
$check('a local helper that stops using the formatter', $r['day'] >= 4 && str_contains($r['out'], 'Periods.jsx'),
    sprintf('saw %d DAY-VIA-ZONE at the helper call sites', (int) $r['day']));
$restore();

$plant('resources/js/Pages/Admin/ReferenceData/Curriculums.jsx',
    'calendarYear(row.effectiveYear) ?? ',
    "new Date(row.effectiveYear).getFullYear() ?? ");
$r = $run();
$check('a year read through getFullYear', $r['year'] >= 1 && str_contains($r['out'], 'YEAR-VIA-ZONE')
    && str_contains($r['out'], 'Curriculums.jsx'),
    sprintf('saw %d YEAR-VIA-ZONE', (int) $r['year']));
$restore();

$plant('resources/js/Pages/Exam/Index.jsx',
    'formatCalendarDay(row.examDate)',
    'formatCalendarDay(row.examDaemon)');
$r = $run();
$check('a name the schema does not have', $r['unbound'] >= 1 && $r['refused'] && $r['exit'] === 1,
    sprintf('UNBOUND %d, refused: %s, exit %d', (int) $r['unbound'], $r['refused'] ? 'yes' : 'NO', $r['exit']));
$restore();

echo "\nIts limits stay visible, and an empty scan refuses.\n";
$r = $run('Students');
$check('EMS_LANE50_PAGES=Students narrows the scan', $r['files'] !== null && $r['files'] < $base['files'],
    sprintf('%d files read, of %d', (int) $r['files'], (int) $base['files']));
$r = $run('NoSuchDesk');
$check('an empty scope refuses', $r['exit'] === 1 && str_contains($r['out'], 'NO CONCLUSION'), trim(substr($r['out'], 0, 70)));

$final = $run();
$check('clean again after every restore', $final['problems'] === 0 && $final['formatter'] === $base['formatter'],
    sprintf('%d problems, %d cells on the formatter', (int) $final['problems'], (int) $final['formatter']));

printf("\n%s\n", $fail === 0 ? 'Every defect this lane fixed, the checker can see again.' : "$fail case(s) failed.");
exit($fail === 0 ? 0 : 1);
