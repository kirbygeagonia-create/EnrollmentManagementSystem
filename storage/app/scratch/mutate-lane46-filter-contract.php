<?php

/**
 * Lane 46 mutant harness — proves lane46-filter-contract.php can fail, and can refuse.
 *
 * Defects are planted in the REAL tree and restored byte for byte (the sha256 of every touched file is checked
 * afterwards), because the claims are about how a page, a route and a controller line up — three things that
 * only exist together. Case 6 is the one that keeps this honest about its own scope: EMS_LANE46_PAGES must
 * change what the checker scans, and an empty pages directory must make it REFUSE rather than report a clean
 * tree. A checker whose flag is accepted but not read looks exactly like a clean run, which is how lane 41's
 * first harness reported five missed classes, and how lane 44's row mutants slept.
 *
 * Usage: php storage/app/scratch/mutate-lane46-filter-contract.php
 */
$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$checker = 'storage/app/scratch/lane46-filter-contract.php';

$targets = [
    'student' => $root.'/app/Http/Controllers/StudentController.php',
    'students-jsx' => $root.'/resources/js/Pages/Students/Index.jsx',
    'exam-jsx' => $root.'/resources/js/Pages/Exam/Index.jsx',
    'clearance' => $root.'/app/Http/Controllers/Clearance/ClearanceController.php',
];

$original = [];
$hash0 = [];
foreach ($targets as $k => $path) {
    $original[$k] = (string) file_get_contents($path);
    $hash0[$k] = hash('sha256', $original[$k]);
}

// A harness that plants into the real tree can leave a defect behind when it dies between the write and the
// restore — and a leftover is worse than a red run, because the next invocation snapshots the mutated file as
// its "original", reports the planted defect as the clean baseline, and then cannot apply its own mutant.
// That is exactly what happened on 2026-10-18: `students.index-typo` was found sitting in Students/Index.jsx
// after a run, and every case after it went red for the wrong reason. So refuse to start on a tree that already
// carries one of the tokens this file plants, and restore on every exit path.
$plantedTokens = ['students.index-typo', 'lane46bogus', "label: 'Graduated'"];
$leftovers = [];
foreach ($targets as $k => $path) {
    foreach ($plantedTokens as $v) {
        if (str_contains($original[$k], $v)) {
            $leftovers[] = $k.' carries '.preg_replace('/\s+/', ' ', $v);
        }
    }
}
if ($leftovers !== []) {
    fwrite(STDERR, "REFUSED — the tree already holds a value this harness plants, so a previous run did not\n"
        ."          restore it and this run's idea of \"original\" would be a defect:\n");
    foreach ($leftovers as $l) {
        fwrite(STDERR, '          '.$l."\n");
    }
    fwrite(STDERR, "          restore those files (git checkout -- <path>) and run this again.\n");
    exit(1);
}

register_shutdown_function(function () use ($targets, $original, $hash0): void {
    foreach ($targets as $k => $path) {
        if (hash('sha256', (string) file_get_contents($path)) !== $hash0[$k]) {
            file_put_contents($path, $original[$k]);
            fwrite(STDERR, "shutdown restore wrote back {$k}\n");
        }
    }
});

$failures = 0;
$run = function (array $env = []) use ($php, $root, $checker): array {
    $pairs = '';
    foreach ($env as $k => $v) {
        // cmd's `set X="Y"` keeps the quotes inside the value, so the whole assignment is quoted instead.
        $pairs .= 'set "'.str_replace('"', '', $k.'='.$v).'" && ';
    }
    $out = [];
    $code = 0;
    exec('cd /d '.escapeshellarg($root).' && '.$pairs.escapeshellarg($php).' '.escapeshellarg($root.'/'.$checker).' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};

$restore = function () use ($targets, $original, $hash0, &$failures): void {
    foreach ($targets as $k => $path) {
        file_put_contents($path, $original[$k]);
        if (hash('sha256', (string) file_get_contents($path)) !== $hash0[$k]) {
            printf("  BAD  restore of %s did not produce the original bytes\n", $k);
            $failures++;
        }
    }
};

$case = function (string $name, string $needle, array $plants, bool $expectFail = true) use ($run, $restore, $original, &$failures): void {
    foreach ($plants as $k => [$from, $to]) {
        $src = $GLOBALS['__current'][$k] ?? null;
        if ($src === null) {
            printf("  BAD  %-42s no such target %s\n", $name, $k);
            $failures++;
            $restore();

            return;
        }
        if (! str_contains($src, $from)) {
            printf("  BAD  %-42s MUTANT DID NOT APPLY — the anchor is not in %s any more\n", $name, $k);
            $failures++;
            $restore();

            return;
        }
        $mutated = str_replace($from, $to, $src);
        if ($mutated === $src) {
            printf("  BAD  %-42s MUTANT DID NOT APPLY — the replacement changed no bytes\n", $name);
            $failures++;
            $restore();

            return;
        }
        $GLOBALS['__current'][$k] = $mutated;
        file_put_contents($GLOBALS['__paths'][$k], $mutated);
    }
    [$code, $out] = $run();
    $ok = $expectFail ? ($code !== 0 && str_contains($out, $needle)) : ($code === 0);
    printf("  %s %-42s exit=%d  %s\n", $ok ? 'ok  ' : 'BAD ', $name, $code,
        $expectFail ? (str_contains($out, $needle) ? 'needle found' : 'needle MISSING: '.$needle)
            : ($code === 0 ? 'clean as expected' : 'unexpectedly red'));
    if (! $ok) {
        printf("       %s\n", trim(preg_replace('/\s+/', ' ', mb_substr($out, 0, 260))));
        $failures++;
    }
    $restore();
    $GLOBALS['__current'] = $original;
};

$GLOBALS['__paths'] = $targets;
$GLOBALS['__current'] = $original;

echo "=== case 0: the fixed tree is clean, and the run says so ===\n";
$case('clean tree', '', [], false);

echo "\n=== planted defects ===\n";

// A. The original lane 46 defect, in full: the page sends `status` and the handler never reads the key at all.
//    Removing only the ->when() line would NOT be a faithful mutant — the normalisation statement still reads
//    $request->query('status'), so the key is consulted, and DEAD-SEND is a claim about the key never being
//    read. A key read into a variable and then unused is outside this checker, and the case is written to say
//    so rather than to flatter the parser.
$case('the status filter never read at all',
    'DEAD-SEND',
    ['student' => ["        \$status = in_array(\$request->query('status'), array_column(StudentStatus::cases(), 'value'), true)\n"
            ."            ? \$request->query('status')\n"
            ."            : null;\n\n"
            ."        \$query = CollegeScope::ofStudents(Students::query(), \$request->user())\n"
            ."            ->when(\$status, fn (\$q) => \$q->where('status', \$status))\n",
        "        \$query = CollegeScope::ofStudents(Students::query(), \$request->user())\n"]],
);

// B. The handler still narrows, but the prop no longer says so — the control resets while the list is filtered.
//    The anchor was re-taken on 2026-10-30: G-40's wiring put `sort` and `direction` beside `status`, so the echo
//    is a multi-line array and the one-line needle this leg carried matched nothing. A leg whose anchor is gone is
//    the harness's age, not a surviving defect — it reports BAD, and the defect it names was never planted.
//    The planted state is the real one: `status` is still applied to the query, only the echo loses the key.
$case('a filter applied but never echoed',
    'NO-RETURN',
    ['student' => ["                'status' => \$status,\n", '']],
);

// B2. The other echo shape the reader used to miss: `$request->only([…]) + ['sort' => …]`, where the merged array
//     is part of the same echo. Cutting the value at the first depth-zero `]` reported three keys and called the
//     two sort keys unechoed — a false complaint about a page that echoes them; cutting at the first depth-zero
//     comma reads the merge. Deleting one merged key here must therefore raise NO-RETURN, which is what proves the
//     comma stop is honoured rather than tolerated.
$case('a key echoed only inside a merged array is still read',
    'NO-RETURN',
    ['clearance' => ["                'direction' => \$sort['direction'],\n", '']],
);

// C. The dropdown regains a value the column cannot hold.
$case('a dropdown option the column cannot hold',
    'FICTION',
    ['students-jsx' => ["    { value: 'inactive', label: 'Inactive' },",
        "    { value: 'inactive', label: 'Inactive' },"."\n    { value: 'graduated', label: 'Graduated' },"]],
);

// D. The link-object shape (route('x', { … })) must be read as well as the router.get object.
$case('a key added to a row link is judged too',
    'lane46bogus',
    ['exam-jsx' => ["route('exam.results', { search: row.student?.schoolIdNumber })",
        "route('exam.results', { search: row.student?.schoolIdNumber, lane46bogus: 1 })"]],
);

// E. A query sent to a route the router does not name is a claim the checker cannot bind — it must refuse.
$case('a route name the router does not know',
    'NO-ROUTE',
    ['students-jsx' => ["router.get(route('students.index'), {", "router.get(route('students.index-typo'), {"]],
);

echo "\n=== the scope flag must be read, not accepted ===\n";

$oneDir = $root.'/storage/app/scratch/lane46-one-page';
$emptyDir = $root.'/storage/app/scratch/lane46-empty';
@mkdir($oneDir, 0777, true);
@mkdir($emptyDir, 0777, true);
copy($targets['students-jsx'], $oneDir.'/Index.jsx');

// The whole-tree count is measured here rather than remembered: a hard-coded 55 goes stale the moment a page
// is added, and a stale constant makes the scope case fail for a reason that has nothing to do with the flag.
[, $wholeOut] = $run();
$baseline = preg_match('/(\d+) page\/route binding\(s\) judged/', $wholeOut, $wm) ? (int) $wm[1] : 0;
printf("  %s %-42s bindings=%d\n", $baseline > 0 ? 'ok  ' : 'BAD ', 'the whole tree is counted before narrowing', $baseline);
$failures += $baseline > 0 ? 0 : 1;

// (i) scoped to one page: the run must say it scanned that directory and judge fewer bindings than the tree.
[$code, $out] = $run(['EMS_LANE46_PAGES' => $oneDir]);
$scoped = str_contains($out, $oneDir) && str_contains($out, 'binding(s) judged');
$count = preg_match('/(\d+) page\/route binding\(s\) judged/', $out, $m) ? (int) $m[1] : -1;
$ok = $code === 0 && $scoped && $count >= 1 && $count < $baseline;
printf("  %s %-42s exit=%d  scanned=%s bindings=%d\n", $ok ? 'ok  ' : 'BAD ', 'the flag narrows what is scanned', $code,
    $scoped ? 'yes' : 'NO — the override was ignored', $count);
$failures += $ok ? 0 : 1;

// (ii) scoped to nothing: an empty scope must REFUSE, not report a clean tree.
[$code2, $out2] = $run(['EMS_LANE46_PAGES' => $emptyDir]);
$refused = $code2 !== 0 && str_contains($out2, 'nothing was judged');
printf("  %s %-42s exit=%d  %s\n", $refused ? 'ok  ' : 'BAD ', 'an empty scope refuses the run', $code2,
    $refused ? 'refused as required' : 'reported clean on a tree it never read — '.mb_substr(preg_replace('/\s+/', ' ', $out2), 0, 120));
$failures += $refused ? 0 : 1;

@unlink($oneDir.'/Index.jsx');
@rmdir($oneDir);
@rmdir($emptyDir);

// F. The checker must still see the fiction class when the handler is right — proved by (C); and every case
// above leaves the tree byte-identical, checked here once more.
foreach ($targets as $k => $path) {
    if (hash('sha256', (string) file_get_contents($path)) !== $hash0[$k]) {
        printf("  BAD  tree not restored for %s\n", $k);
        $failures++;
    }
}

printf("\n  %d case(s) failed.\n", $failures);
exit($failures === 0 ? 0 : 1);
