<?php

/**
 * Lane 47 test mutant harness — proves tests/Feature/DeskSummaryScopeTest.php can fail.
 *
 * A test that passes on the fixed code proves nothing until the defect it names is put back and the test
 * complains. Each case reverts one desk to the shape it had before this lane, runs only the case that should
 * notice, then restores the file and verifies the restore by hash — the pattern mutate-lane43 and
 * mutate-lane47-tile-scope follow.
 *
 * Two habits are enforced, both paid for elsewhere in this repository:
 *   • every needle must match exactly once, or the case is a harness failure. mutate-lane44 reported five
 *     defect classes it had never planted because its anchors had moved and its mutants slept.
 *   • the statement text is what is matched, never a comment: the comments in these controllers were
 *     rewritten by the fix itself, so a needle built on prose would silently stop matching.
 *
 * Usage: php storage/app/scratch/mutate-lane47-tests.php [--only=clearance]
 */

$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$test = 'tests/Feature/DeskSummaryScopeTest.php';

$only = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = substr($arg, strlen('--only='));
    }
}

/**
 * Revert a desk to its pre-lane shape. The paginate-before-aggregate cases are two steps: lift the paginate
 * call out of its place, then put it back immediately above the aggregate it must not be allowed to touch.
 */
$reorder = function (string $listStatement, string $aggregateStart, string $indent = '        ') {
    return [
        ['from' => $listStatement."\n", 'to' => ''],
        ['from' => $indent.$aggregateStart, 'to' => $listStatement."\n\n".$indent.$aggregateStart],
    ];
};

$cases = [
    [
        'name' => 'clearance aggregate taken after the page LIMIT',
        'file' => $root.'/app/Http/Controllers/Clearance/ClearanceController.php',
        'steps' => $reorder('        $clearances = $query->paginate(20)->withQueryString();', '$statusCounts = (clone $query)'),
        'filter' => 'the_clearance_tiles_still_describe_the_queue_on_the_second_page',
    ],
    [
        'name' => 'admission aggregate taken after the page LIMIT',
        'file' => $root.'/app/Http/Controllers/Admission/AdmissionController.php',
        'steps' => $reorder('        $admissions = $query->paginate(20)->withQueryString();', '$statusCounts = (clone $query)'),
        'filter' => 'the_admission_tiles_still_describe_the_queue_on_the_second_page',
    ],
    [
        'name' => 'assessment aggregate taken after the page LIMIT',
        'file' => $root.'/app/Http/Controllers/Assessment/AssessmentController.php',
        'steps' => $reorder('        $assessments = $query->paginate(20)->withQueryString();', '$summary = (clone $query)'),
        'filter' => 'the_assessment_desk_keeps_its_money_totals_when_the_desk_pages',
    ],
    [
        'name' => 'blocking capacity summed over the page',
        'file' => $root.'/app/Http/Controllers/Blocking/BlockingController.php',
        'steps' => [[
            'from' => "'capacity' => (int) \$setRows->sum('maxStudents'),",
            'to' => "'capacity' => (int) \$blocks->getCollection()->sum('maxStudents'),",
        ]],
        'filter' => 'the_blocking_tiles_count_the_filtered_set_not_the_rows_on_the_page',
    ],
    [
        'name' => 'pass rate divided by every row, scored or not',
        'file' => $root.'/app/Http/Controllers/Exam/ExamController.php',
        'steps' => [[
            'from' => '$scoredCount = $passCount + $failCount;',
            'to' => '$scoredCount = (int) (clone $query)->reorder()->count();',
        ]],
        'filter' => 'the_pass_rate_excludes_unscored_rows_and_does_not_move_with_the_page',
    ],
    [
        'name' => 'evaluated answered with a queue number',
        'file' => $root.'/app/Http/Controllers/Evaluation/EvaluationController.php',
        'steps' => [[
            'from' => "'evaluated' => \$handled,",
            'to' => "'evaluated' => (int) \$queue->returned,",
        ]],
        'filter' => 'the_evaluation_desk_reports_the_work_that_left_it_not_only_the_rows_on_screen',
    ],
    [
        'name' => 'audit action counted from the page',
        'file' => $root.'/app/Http/Controllers/Admin/UserManagementController.php',
        'steps' => [[
            'from' => "'created' => (int) (\$actionCounts['created'] ?? 0),",
            'to' => "'created' => (int) \$logs->count(),",
        ]],
        'filter' => 'the_audit_log_can_be_filtered_for_an_entity_that_only_appears_past_page_one',
    ],
    [
        'name' => 'audit entity list emptied',
        'file' => $root.'/app/Http/Controllers/Admin/UserManagementController.php',
        'steps' => [[
            'from' => "'entityTables' => \$entityTables,",
            'to' => "'entityTables' => [],",
        ]],
        'filter' => 'the_audit_log_can_be_filtered_for_an_entity_that_only_appears_past_page_one',
    ],
];

$run = function (string $filter) use ($php, $root, $test): array {
    $out = [];
    $code = 0;
    exec('cd /d '.escapeshellarg($root).' && '.escapeshellarg($php).' artisan test '.escapeshellarg($test)
        .' --filter='.escapeshellarg($filter).' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};

/**
 * Bytes each file arrived with, drained at shutdown.
 *
 * Each case below restores and verifies, but a run killed mid-case — and a Pest run is minutes long — would
 * otherwise leave a reverted controller in the working tree, where the next invocation would snapshot it as
 * "original". mutate-lane46 learned that the hard way on 2026-10-18.
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

$failures = 0;
$planned = 0;

foreach ($cases as $c) {
    if ($only !== null && ! str_contains($c['name'], $only)) {
        continue;
    }
    $planned++;

    $original = @file_get_contents($c['file']);
    if ($original === false) {
        printf("  BAD  %-46s unreadable %s\n", $c['name'], basename($c['file']));
        $failures++;

        continue;
    }

    $refused = false;
    foreach ($c['steps'] as $i => $step) {
        $hits = substr_count($original, $step['from']);
        if ($hits === 1) {
            continue;
        }
        printf("  BAD  %-46s step %d matched %d times, expected 1 — this mutant would sleep: %s\n",
            $c['name'], $i + 1, $hits, preg_replace('/\s+/', ' ', mb_substr($step['from'], 0, 60)));
        $failures++;
        $refused = true;
    }
    if ($refused) {
        continue;
    }

    $mutated = $original;
    foreach ($c['steps'] as $step) {
        $mutated = str_replace($step['from'], $step['to'], $mutated);
    }
    if ($mutated === $original) {
        printf("  BAD  %-46s the planted file is byte-identical to the real one\n", $c['name']);
        $failures++;

        continue;
    }

    $untouched[$c['file']] ??= $original;
    file_put_contents($c['file'], $mutated);
    [$code, $out] = $run($c['filter']);
    file_put_contents($c['file'], $original);
    $restored = hash_file('sha256', $c['file']) === hash('sha256', $original);

    $complained = $code !== 0;
    printf("  %s %-46s exit=%d  %s\n", $complained && $restored ? 'ok  ' : 'BAD ', $c['name'], $code,
        $complained ? 'the test noticed' : 'the test stayed green — it does not pin this behaviour');
    if (! $restored) {
        printf("  BAD  could not restore %s byte-for-byte\n", basename($c['file']));
        $failures++;
    }
    if (! $complained) {
        printf("       %s\n", preg_replace('/\s+/', ' ', mb_substr($out, -340)));
        $failures++;
    }
}

if ($planned === 0) {
    echo "REFUSED — --only matched no case, so nothing was falsified.\n";
    exit(1);
}

printf("\n  %d case(s) failed.\n", $failures);
exit($failures === 0 ? 0 : 1);
