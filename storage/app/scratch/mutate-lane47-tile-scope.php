<?php

/**
 * Lane 47 mutant harness — proves lane47-tile-scope.php can fail.
 *
 * Each case plants one defect in the real tree, runs the checker, and restores the file from a byte copy with
 * the hash verified — the pattern mutate-lane43/mutate-lane37b use. Two rules carried over from those harnesses
 * are enforced here, because both were paid for once already:
 *
 *   • a case whose mutation matches nothing is REFUSED, not reported as a caught defect. mutate-lane44's row
 *     cases anchored on a generated `ROW 1237:` prefix; the document grew a paragraph, every ordinal moved,
 *     both str_replace calls matched nothing, and the harness printed "ok" for a mutant that never existed.
 *   • case 0 runs the checker over the unmutated tree and requires the clean verdict, which is what makes the
 *     EMS_LANE47_PAGES cases below mean something: an ignored flag would still look like a filtered run.
 *
 * Usage: php storage/app/scratch/mutate-lane47-tile-scope.php
 */

$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$checker = $root.'/storage/app/scratch/lane47-tile-scope.php';
$pages = $root.'/resources/js/Pages';
$controllers = $root.'/app/Http/Controllers';

$run = function (?string $scope = null) use ($php, $root, $checker): array {
    $env = $scope === null ? '' : 'set "EMS_LANE47_PAGES='.str_replace('"', '', $scope).'" && ';
    $out = [];
    $code = 0;
    exec('cd /d '.escapeshellarg($root).' && '.$env.escapeshellarg($php).' '.escapeshellarg($checker).' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};

$failures = 0;

/**
 * Every file touched, with the bytes it arrived with, drained at shutdown.
 *
 * The per-case restore below is hash-verified, but a process killed between a plant and that restore leaves the
 * defect in the tree — and mutate-lane46 found exactly that residue on 2026-10-18, where the leftover made the
 * next run snapshot a planted defect as its own "original". Writing it back on any exit path costs nothing and
 * turns a crashed harness into a no-op instead of a corrupted working tree.
 */
$untouched = [];
register_shutdown_function(function () use (&$untouched): void {
    foreach ($untouched as $path => $bytes) {
        if (hash_file('sha256', $path) !== hash('sha256', $bytes)) {
            file_put_contents($path, $bytes);
            fwrite(STDERR, 'shutdown restore wrote back '.basename($path)."\n");
        }
    }
});

/** Copy → mutate → run → restore, refusing a mutation that cannot be planted. */
$case = function (string $name, string $file, string $from, string $to, string $needle) use ($run, &$failures, &$untouched): void {
    $original = @file_get_contents($file);
    if ($original === false) {
        printf("  BAD  %-34s cannot read %s\n", $name, basename($file));
        $failures++;

        return;
    }
    $untouched[$file] ??= $original;
    $hits = substr_count($original, $from);
    if ($hits !== 1) {
        printf("  BAD  %-34s the mutation matched %d times, expected 1 — this mutant would sleep: %s\n",
            $name, $hits, preg_replace('/\s+/', ' ', mb_substr($from, 0, 64)));
        $failures++;

        return;
    }

    file_put_contents($file, str_replace($from, $to, $original));
    [$code, $out] = $run();
    $restored = file_put_contents($file, $original);

    $caught = $code !== 0 && str_contains($out, $needle);
    printf("  %s %-34s exit=%d  %s\n", $caught ? 'ok  ' : 'BAD ', $name, $code,
        str_contains($out, $needle) ? 'needle found' : 'needle MISSING: '.$needle);

    if ($restored !== strlen($original) || hash_file('sha256', $file) !== hash('sha256', $original)) {
        printf("  BAD  %-34s could not restore %s byte-for-byte\n", $name, basename($file));
        $failures++;
    }
    if (! $caught) {
        printf("       %s\n", preg_replace('/\s+/', ' ', mb_substr($out, -400)));
    }
};

echo "=== case 0: the unmutated tree has to look clean, or the scoped cases prove nothing\n";
[$code, $out] = $run();
// The clean tree must ALSO show the new verdict reading the one site that guards itself: an instrument that
// stopped computing this line would report the same 0 problems as one that had fixed everything.
$clean = $code === 0 && str_contains($out, 'problem(s)') && str_contains($out, 'PINNED-SELECT 0, plus 1');
printf("  %s %-34s exit=%d  %s\n", $clean ? 'ok  ' : 'BAD ', 'clean tree', $code,
    $clean ? 'as expected' : 'the baseline is red — every case below would still "pass"');
if (! $clean) {
    printf("       %s\n", preg_replace('/\s+/', ' ', mb_substr($out, -500)));
    $failures++;
}

echo "\n=== planted defects ===\n";

// A summary taken after the page LIMIT, which is the shape three desks had. This needle was re-cut on 2026-10-31:
// the clearance desk's tile now sits BEFORE its order and its paginate call, so the old two-statement anchor
// matched nothing and the case reported itself asleep — a mutant that cannot be planted is the harness's age, not
// a rule that does not fire. The plant is now a true permutation of the handler's own lines: tiles after the page.
$clearTile = '        $statusCounts = (clone $query)
            ->reorder()
            ->selectRaw(\'overallStatus, count(*) as aggregate\')
            ->groupBy(\'overallStatus\')
            ->pluck(\'aggregate\', \'overallStatus\');

';
$clearTail = '        // The order is asked of the query, and only after the tiles were cloned: a name sort joins `students`, and
        // an aggregate must not learn about a join it never needed. The join is 1:1 through a NOT NULL foreign key,
        // so it could not have changed a count either way — but a tile\'s number should not depend on that fact.
        $sortable = [
            \'name\' => [\'column\' => \'lastName\', \'join\' => \'students\', \'foreign\' => \'studentId\', \'local\' => \'studentclearances.studentId\'],
            \'schoolId\' => [\'column\' => \'schoolIdNumber\', \'join\' => \'students\', \'foreign\' => \'studentId\', \'local\' => \'studentclearances.studentId\'],
            \'status\' => [\'column\' => \'overallStatus\'],
            \'received\' => [\'column\' => \'receivedDate\'],
            \'studentClearanceId\' => [\'column\' => \'studentClearanceId\'],
        ];
        $sort = ListSort::apply(
            $query,
            (string) $request->input(\'sort\', \'\'),
            (string) $request->input(\'direction\', \'\'),
            $sortable,
            \'studentClearanceId\',
            \'studentclearances.studentClearanceId\'
        );

        $clearances = $query->paginate(20)->withQueryString();';
$case('aggregate cloned after paginate()', $controllers.'/Clearance/ClearanceController.php',
    $clearTile.$clearTail, $clearTail."\n\n".$clearTile, 'SILENT-ZERO');

// The other half of the ordering question, and the one that reached a desk as a 500 rather than as an empty tile:
// an aggregate cloned after `ListSort::apply()` inherits the select the order pinned, and `selectRaw()` appends to
// it. The pre-fix bytes are restored here — the admission tile's own `select()` removed — so the NEW verdict must
// be the one that fires: the clone still precedes `paginate()`, which is why SILENT-ZERO staying silent matters.
$case('aggregate inherits the order\'s pinned select', $controllers.'/Admission/AdmissionController.php',
    "->select('admissionStatus')->selectRaw('count(*) as aggregate')",
    "->selectRaw('admissionStatus, count(*) as aggregate')",
    'PINNED-SELECT');

// A tile that counts the rows on the page under a label that promises the queue. The mutation replaces the
// handler's number with the page reduce this screen had before, in the local the tile reads.
$case('tile reduced over the page', $pages.'/Blocking/Index.jsx',
    'const totalCapacity = stats.capacity ?? \'—\';',
    'const totalCapacity = (blocks?.data || []).reduce((s, b) => s + (b.maxStudents || 0), 0);',
    'PAGE-TILE');

// The same kind of mutation on its neighbour is the disagreement between tiles on one screen.
$case('tiles that disagree with each other', $pages.'/Blocking/Index.jsx',
    'const totalEnrolled = stats.enrolled ?? \'—\';',
    'const totalEnrolled = (blocks?.data || []).length;',
    'MIXED-SET');

// A client predicate the handler's query cannot return.
$case('a tile that can never leave 0', $pages.'/Evaluation/Index.jsx',
    '    const columns = useMemo(() => [',
    '    const evaluatedAgain = (enrollments?.data || []).filter((r) => r.enrollmentStatus === \'evaluated\').length;

    const columns = useMemo(() => [',
    'IMPOSSIBLE');

// A pick-list assembled from the current page.
$case('filter options built from rows', $pages.'/Admin/UserManagement/AuditLogs.jsx',
    '    const entityTableOptions = useMemo(() => [
        { value: \'\', label: \'All Entities\' },
        ...(entityTables || []).map((t) => ({ value: t, label: t })),
    ], [entityTables]);',
    '    const entityTableOptions = useMemo(() => {
        const tables = [...new Set(logs?.data?.map((l) => l.entityTable).filter(Boolean))];

        return [
            { value: \'\', label: \'All Entities\' },
            ...tables.map((t) => ({ value: t, label: t })),
        ];
    }, [logs]);',
    'DEAD-OPTION');

// A tile the checker cannot trace must refuse the run rather than read as a checked claim.
// Re-anchored 2026-10-26: this leg pointed at `value={stats.pending ?? 0}` until lane 48 removed every
// numeric tile default, so the needle matched nothing and the leg reported BAD — a sleeping mutant, and
// the refusal that names it is the only reason it was not read as a clean run.
$case('an untraceable tile', $pages.'/ID/Index.jsx',
    'value={stats.pending}',
    'value={nobodyDefinesThis}',
    'UNBOUND');

echo "\n=== the scan is actually pointed at the pages named ===\n";
[$code, $scoped] = $run('Pages/ID');
$onlyId = str_contains($scoped, 'Pages/ID/Index.jsx') && ! str_contains($scoped, 'Pages/Blocking/Index.jsx');
printf("  %s scope=Pages/ID                  exit=%d  %s\n", $onlyId ? 'ok  ' : 'BAD ', $code,
    $onlyId ? 'judged ID, skipped Blocking' : 'the flag did not change what was scanned');
$failures += $onlyId ? 0 : 1;

[$code, $empty] = $run('Pages/NoSuchDeskAtAll');
$refused = $code !== 0 && (str_contains($empty, 'REFUSED') || str_contains($empty, 'no page file'));
printf("  %s scope=Pages/NoSuchDeskAtAll     exit=%d  %s\n", $refused ? 'ok  ' : 'BAD ', $code,
    $refused ? 'refused instead of reporting clean' : 'an empty scope reported no problems — that is a pass with nothing checked');
$failures += $refused ? 0 : 1;

printf("\n  %d case(s) failed.\n", $failures);

exit($failures === 0 ? 0 : 1);
