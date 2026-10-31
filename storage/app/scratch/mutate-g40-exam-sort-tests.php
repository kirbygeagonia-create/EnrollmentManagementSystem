<?php

/**
 * Proves the Examination queue's sorting guard can fail (G-40's tenth wired table).
 *
 * Seven legs, each planting one way the promise could stop meaning anything and requiring the NAMED case to report
 * exactly one failure. Two of them are the shapes a wrong-column-only harness never sees:
 *
 *   old-hard-order-restored   the `orderByDesc('examId')` this wiring replaced is restored beside the mechanism, so
 *                             every header becomes a no-op behind the newest-first order
 *   sort-never-echoed         the handler orders correctly but never tells the page
 *   filters-never-echoed      the order is applied and reported in `sort` but not in `filters` — lane 46's NO-RETURN
 *   surname-spec-loses-join   the name column is listed as a bare `examresults.lastName`, which is the shape that
 *                             answers a SQL error at the desk rather than a wrong sequence
 *   derived-key-listed        Student Type is added to the allow-list, so a header paints for a value the handler
 *                             reads from the enrollment AFTER the page is cut — the order would be a lie
 *   enum-key-withheld         the pre-ruling refusal to offer an ENUM header is restored for `examType`
 *   sort-never-applied        the column the desk asked for is dropped before the allow-list sees it
 *
 * Deliberately NOT planted, and said so: moving `ListSort::apply()` above the two tile clones. That is the
 * PINNED-SELECT shape `lane47-tile-scope.php` owns, and it cannot be falsified on this box — the students and
 * courses hops are each 1:1 through a NOT NULL key, and SQLite has no `ONLY_FULL_GROUP_BY`, so the four tiles would
 * answer the same numbers and the leg could never fail.
 *
 * Usage: php storage/app/scratch/mutate-g40-exam-sort-tests.php
 */
$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$testFile = 'tests/Feature/Sorting/ExamQueueSortTest.php';
$ctrlFile = $root.'/app/Http/Controllers/Exam/ExamController.php';

$snapshot = (string) file_get_contents($ctrlFile);
$sha1 = sha1($snapshot);

register_shutdown_function(static function () use ($ctrlFile, $snapshot, $sha1): void {
    if (sha1((string) file_get_contents($ctrlFile)) !== $sha1) {
        file_put_contents($ctrlFile, $snapshot);
    }
});

$legs = [
    [
        'name' => 'old-hard-order-restored',
        'from' => "            ->when(\$request->search, fn (\$q, \$search) => \$q->whereHas('student', fn (\$sq) => \$sq->where('lastName', 'like', \"%{\$search}%\")->orWhere('firstName', 'like', \"%{\$search}%\")->orWhere('schoolIdNumber', \$search)));\n\n        // Item 4 ownership",
        'to' => "            ->when(\$request->search, fn (\$q, \$search) => \$q->whereHas('student', fn (\$sq) => \$sq->where('lastName', 'like', \"%{\$search}%\")->orWhere('firstName', 'like', \"%{\$search}%\")->orWhere('schoolIdNumber', \$search)))\n            ->orderByDesc('examId'); // mutant: the retired single order, restored beside the mechanism\n\n        // Item 4 ownership",
        'case' => 'the_date_column_orders_the_day_the_paper_was_recorded',
        'promise' => 'a header the desk clicks actually arranges the list',
    ],
    [
        'name' => 'sort-never-echoed',
        'from' => "            'sort' => ListSort::present(\$sort, \$sortable),\n",
        'to' => '',
        'case' => 'the_two_derived_columns_are_not_offered_because_this_query_never_joined_them',
        'promise' => 'the page is told the applied order and the sortable set',
    ],
    [
        'name' => 'filters-never-echoed',
        'from' => "            'filters' => \$request->only(['type', 'search']) + [\n                'sort' => \$sort['column'],\n                'direction' => \$sort['direction'],\n            ],",
        'to' => "            'filters' => \$request->only(['type', 'search']),",
        'case' => 'a_filter_and_an_order_are_both_echoed_so_neither_control_resets',
        'promise' => 'a key the desk sent comes back, so no control resets while its list stays narrowed',
    ],
    [
        'name' => 'surname-spec-loses-its-join',
        'from' => "        \$sortable = [\n            'schoolId' => ['column' => 'schoolIdNumber', 'join' => 'students', 'foreign' => 'studentId', 'local' => 'examresults.studentId'],\n            'name' => ['column' => 'lastName', 'join' => 'students', 'foreign' => 'studentId', 'local' => 'examresults.studentId'],",
        'to' => "        \$sortable = [\n            'schoolId' => ['column' => 'schoolIdNumber', 'join' => 'students', 'foreign' => 'studentId', 'local' => 'examresults.studentId'],\n            'name' => ['column' => 'lastName'], // mutant: the hop is gone, so the order asks the result row for a surname",
        'case' => 'the_queue_can_be_ordered_by_the_students_surname_one_hop_away',
        'promise' => 'a column one table away is reached through the hop that holds it',
    ],
    [
        'name' => 'derived-key-listed',
        'from' => "            'examDate' => ['column' => 'examDate'],\n            // The queue's own key is listed",
        'to' => "            'examDate' => ['column' => 'examDate'],\n            'studentType' => ['column' => 'examType'], // mutant: a header for a value this query never holds\n            // The queue's own key is listed",        'to' => "            'examDate' => ['column' => 'examDate'],\n            'studentType' => ['column' => 'examType'], // mutant: offers a header for a value read after the page is cut",
        'case' => 'the_two_derived_columns_are_not_offered_because_this_query_never_joined_them',
        'promise' => 'a header appears only for a column this query can order in SQL',
    ],
    [
        'name' => 'enum-key-withheld',
        'from' => "            // paper reaches one end of the list rather than reading as a third verdict.\n            'examType' => ['column' => 'examType'],\n",
        'to' => "            // paper reaches one end of the list rather than reading as a third verdict.\n",
        'case' => 'the_two_derived_columns_are_not_offered_because_this_query_never_joined_them',
        'promise' => 'an ENUM column carries a header, because the school\'s server orders it in the declaration order the school wrote',
    ],
    [
        'name' => 'sort-never-applied',
        'from' => "            (string) \$request->input('sort', ''),\n            (string) \$request->input('direction', ''),\n            \$sortable,",
        'to' => "            '', // mutant: the asked column is dropped before the allow-list sees it\n            (string) \$request->input('direction', ''),\n            \$sortable,",
        'case' => 'the_school_number_column_orders_by_the_number_and_not_by_the_name',
        'promise' => 'the queue really orders by the column the desk asked for',
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

$after = $runCase('the_queue_opens_newest_result_first_when_nothing_is_asked');
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
