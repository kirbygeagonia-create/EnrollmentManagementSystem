<?php

/**
 * Proves the Examination results screen's sorting guard can fail (G-40's twelfth wired table).
 *
 * Six legs. Each plants one way the promise could stop meaning anything and requires the NAMED case to report
 * exactly one failure — not merely "not passed", since a mutant that does not compile and a --filter that selects
 * no test both read as a non-pass and a lazy leg would call either a kill.
 *
 *   old-hard-order-restored   the `orderByDesc('examId')` this wiring replaced is restored beside the mechanism
 *   sort-never-echoed         the handler orders correctly but never tells the page
 *   filters-never-echoed      the order is applied and reported in `sort` but not in `filters` — lane 46's NO-RETURN
 *   course-spec-loses-its-join the program column is listed as a bare `examresults.courseName`, which answers a SQL
 *                             error at the desk rather than a wrong sequence
 *   enum-key-withheld         the pre-ruling refusal to offer an ENUM header is restored for `examResult`
 *   sort-never-applied        the asked column is dropped before the allow-list sees it
 *
 * One naming note the needles depend on: this action's variables are `$resultsSortable` / `$resultsSort` rather
 * than the roster's `$sortable` / `$sort`, because `index()` and `results()` are two lists of one table in one file
 * and a harness needle that matched either would plant in whichever happened to come first — lane 69's "a needle
 * can be another citation's tail", arriving in a variable name. The multi-line needles below therefore reach the
 * `$resultsSort = ListSort::apply(` line to prove which block they touched.
 *
 * Deliberately NOT planted, and said so: moving `ListSort::apply()` above the `$resultCounts` clone. That aggregate
 * is `selectRaw('examResult, count(*) as aggregate')` on a clone taken before the order, and SQLite has no
 * `ONLY_FULL_GROUP_BY`, so the four tiles would answer the same numbers either way on this box — the defect is real
 * but this surface cannot falsify it, and `lane47-tile-scope.php` carries it as PINNED-SELECT with the admission
 * handler's pre-fix bytes planted by `mutate-g40-accounting-sort-tests.php`.
 *
 * Usage: php storage/app/scratch/mutate-g40-exam-results-sort-tests.php
 */
$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$testFile = 'tests/Feature/Sorting/ExamResultsSortTest.php';
$ctrlFile = $root.'/app/Http/Controllers/Exam/ExamController.php';

$snapshot = (string) file_get_contents($ctrlFile);
$sha1 = sha1($snapshot);

register_shutdown_function(static function () use ($ctrlFile, $snapshot, $sha1): void {
    if (sha1((string) file_get_contents($ctrlFile)) !== $sha1) {
        file_put_contents($ctrlFile, $snapshot);
    }
});

$courseSpec = "            'course' => ['column' => 'courseName', 'join' => 'courses', 'foreign' => 'courseId', 'local' => 'examresults.courseId'],";

$legs = [
    [
        'name' => 'old-hard-order-restored',
        'from' => "            ->when(\$request->search, fn (\$q, \$search) => \$q->whereHas('student', fn (\$sq) => \$sq->where('lastName', 'like', \"%{\$search}%\")->orWhere('firstName', 'like', \"%{\$search}%\")->orWhere('schoolIdNumber', \$search)));\n\n        // Same ownership split as index()",
        'to' => "            ->when(\$request->search, fn (\$q, \$search) => \$q->whereHas('student', fn (\$sq) => \$sq->where('lastName', 'like', \"%{\$search}%\")->orWhere('firstName', 'like', \"%{\$search}%\")->orWhere('schoolIdNumber', \$search)))\n            ->orderByDesc('examId'); // mutant: the retired single order, restored beside the mechanism\n\n        // Same ownership split as index()",
        'case' => 'the_date_column_orders_the_day_the_paper_was_recorded',
        'promise' => 'a header the desk clicks actually arranges the list',
    ],
    [
        'name' => 'sort-never-echoed',
        'from' => "            'sort' => ListSort::present(\$resultsSort, \$resultsSortable),\n",
        'to' => '',
        'case' => 'every_column_the_screen_paints_is_offered_and_nothing_else_is',
        'promise' => 'the page is told the applied order and the sortable set',
    ],
    [
        'name' => 'filters-never-echoed',
        'from' => "            'filters' => \$request->only(['result', 'search']) + [\n                'sort' => \$resultsSort['column'],\n                'direction' => \$resultsSort['direction'],\n            ],",
        'to' => "            'filters' => \$request->only(['result', 'search']),",
        'case' => 'a_result_filter_and_an_order_are_both_echoed_so_neither_control_resets',
        'promise' => 'a key the desk sent comes back, so no control resets while its list stays narrowed',
    ],
    [
        'name' => 'course-spec-loses-its-join',
        'from' => $courseSpec."\n            'examType' => ['column' => 'examType'],\n            'examResult' => ['column' => 'examResult'],\n            'examDate' => ['column' => 'examDate'],",
        'to' => "            'course' => ['column' => 'courseName'], // mutant: the hop is gone, so the order asks the result row for a program name\n            'examType' => ['column' => 'examType'],\n            'examResult' => ['column' => 'examResult'],\n            'examDate' => ['column' => 'examDate'],",
        'case' => 'the_course_column_orders_the_program_and_not_the_student',
        'promise' => 'a column one table away is reached through the hop that holds it',
    ],
    [
        'name' => 'enum-key-withheld',
        'from' => "            'examResult' => ['column' => 'examResult'],\n            'examDate' => ['column' => 'examDate'],\n            'examId' => ['column' => 'examId'],\n        ];\n        \$resultsSort = ListSort::apply(",
        'to' => "            'examDate' => ['column' => 'examDate'],\n            'examId' => ['column' => 'examId'],\n        ];\n        \$resultsSort = ListSort::apply(",
        'case' => 'every_column_the_screen_paints_is_offered_and_nothing_else_is',
        'promise' => 'an ENUM column carries a header, because the school\'s server orders it in the declaration order the school wrote',
    ],
    [
        'name' => 'sort-never-applied',
        'from' => "            (string) \$request->input('sort', ''),\n            (string) \$request->input('direction', ''),\n            \$resultsSortable,",
        'to' => "            '', // mutant: the asked column is dropped before the allow-list sees it\n            (string) \$request->input('direction', ''),\n            \$resultsSortable,",
        'case' => 'the_school_number_column_orders_by_the_number_and_not_by_the_name',
        'promise' => 'the screen really orders by the column the desk asked for',
    ],
];

$runCase = static function (string $case) use ($php, $root, $testFile): array {
    chdir($root);
    $out = (string) preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec(
        escapeshellarg($php).' artisan test '.escapeshellarg($testFile).' --filter='.$case.' 2>&1'
    ));
    $failed = preg_match('/(\d+) failed/', $out, $fm) ? (int) $fm[1] : 0;
    $passed = preg_match('/(\d+) passed/', $out, $pm) ? (int) $pm[1] : 0;
    if (! str_contains($out, 'Tests:') || ($failed === 0 && $passed === 0)) {
        return ['unreadable', $out];
    }

    return [$failed === 0 ? 'green' : $failed.'-failed', $out];
};

$bad = 0;

echo "control: every case green before anything is planted\n";
foreach ($legs as $leg) {
    $verdict = $runCase($leg['case']);
    if ($verdict[0] !== 'green') {
        echo 'BAD control for '.$leg['name'].' — '.$verdict[0]."\n";
        fwrite(STDERR, "REFUSED: the control is not green, so no later verdict can be read.\n");

        exit(1);
    }
}
echo "ok   control\n";

foreach ($legs as $leg) {
    $hits = substr_count($snapshot, $leg['from']);
    if ($hits !== 1) {
        echo 'BAD  '.$leg['name'].": the needle occurs {$hits} time(s) — the tree is not the batch this proves.\n";
        $bad++;

        continue;
    }
    $mutant = str_replace($leg['from'], $leg['to'], $snapshot);
    if ($mutant === $snapshot) {
        echo 'BAD  '.$leg['name'].": the plant changed no bytes, so the leg could never fail.\n";
        $bad++;

        continue;
    }
    file_put_contents($ctrlFile, $mutant);
    clearstatcache();
    if (sha1((string) file_get_contents($ctrlFile)) !== sha1($mutant)) {
        echo 'BAD  '.$leg['name'].": the planted bytes did not reach the file.\n";
        $bad++;
        file_put_contents($ctrlFile, $snapshot);

        continue;
    }

    $verdict = $runCase($leg['case']);
    // Restore between legs, not only at shutdown: a later leg must measure the clean handler.
    file_put_contents($ctrlFile, $snapshot);
    clearstatcache();

    if ($verdict[0] === 'green') {
        echo 'SURVIVED '.$leg['name'].' — '.$leg['promise']."\n";
        echo "         the named case stayed green, so nothing pins the promise.\n";
        $bad++;

        continue;
    }
    if ($verdict[0] === 'unreadable') {
        echo 'BAD  '.$leg['name'].": the run answered no verdict (a filter that selects nothing is a green over nothing).\n";
        echo substr($verdict[1], 0, 600)."\n";
        $bad++;

        continue;
    }
    if ($verdict[0] !== '1-failed') {
        echo 'BAD  '.$leg['name'].': expected exactly one failure, the run said '.$verdict[0].".\n";
        $bad++;

        continue;
    }
    echo 'ok   '.$leg['name'].' killed by '.$leg['case']."\n";
}

$after = $runCase('the_screen_opens_newest_result_first_when_nothing_is_asked');
if ($after[0] !== 'green') {
    echo "BAD  trailing control — the tree is not clean, or a leg left a mutant behind.\n";
    $bad++;
}

if (sha1((string) file_get_contents($ctrlFile)) !== $sha1) {
    fwrite(STDERR, "REFUSED: the controller does not match its snapshot after the run.\n");

    exit(1);
}

printf("\n%s: %d leg(s), %d bad.\n", $bad === 0 ? 'ALL KILLED' : 'FAILURES', count($legs), $bad);

exit($bad === 0 ? 0 : 1);
