<?php

/**
 * Proves the Department Evaluation queue's sorting guard can fail (G-40's ninth wired table).
 *
 * Seven legs. Each plants one way the promise could stop meaning anything and requires the NAMED case to report
 * exactly one failure — not merely "not passed": a mutant that does not compile, and a --filter that selects no
 * test, both read as a non-pass, and a lazy leg would call either a kill.
 *
 *   old-hard-order-restored   the single `orderByDesc('enrollmentId')` the wiring replaced is put back beside the
 *                             mechanism, so the newest-first order dominates every header the desk clicks
 *   sort-never-echoed         the handler orders correctly but never tells the page, so the header resets
 *   filters-never-echoed      the order is applied and reported in `sort` but not in `filters` — lane 46's NO-RETURN
 *   surname-spec-loses-join   the name column is listed as a bare `enrollments.lastName`, which is the shape that
 *                             answers a SQL error at the desk rather than a wrong sequence
 *   enum-key-withheld         the pre-ruling refusal to offer an ENUM header is restored for this desk's own Status
 *   direction-passed-through  the request string reaches the order clause unchecked
 *   sort-never-applied        the column the desk asked for is dropped before the allow-list sees it
 *
 * Deliberately NOT planted, and said so rather than left to be rediscovered: moving `ListSort::apply()` above the
 * tile clone. That is the PINNED-SELECT shape §28.3 G-51's sibling rule owns, and it cannot be falsified here —
 * the students hop is 1:1 through a NOT NULL `enrollments.studentId`, and SQLite has no `ONLY_FULL_GROUP_BY`, so
 * the five tiles would answer the same five numbers on this box and the leg could never fail. `lane47-tile-scope.php`
 * owns that verdict as its PINNED-SELECT rule and `mutate-g40-accounting-sort-tests.php` carries the admission
 * handler's pre-fix bytes for it; `lane108-tile-replaces-its-columns.php` reproduces the MySQL 1055 on a scratch
 * schema. The select pinning inside the helper is proved by `mutate-g40-enum-offer-and-select-pin.php`.
 *
 * Usage: php storage/app/scratch/mutate-g40-evaluation-sort-tests.php
 */
$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$testFile = 'tests/Feature/Sorting/EvaluationQueueSortTest.php';
$ctrlFile = $root.'/app/Http/Controllers/Evaluation/EvaluationController.php';

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
        'from' => "            ->with(['student:studentId,schoolIdNumber,lastName,firstName,middleName', 'course', 'major', 'term', 'evaluatedByUser'])\n            ->when(\$request->search, \$search);",
        'to' => "            ->with(['student:studentId,schoolIdNumber,lastName,firstName,middleName', 'course', 'major', 'term', 'evaluatedByUser'])\n            ->when(\$request->search, \$search)\n            ->orderByDesc('enrollmentId'); // mutant: the retired single order, restored beside the mechanism",
        'case' => 'the_queue_can_be_ordered_by_the_students_surname_one_hop_away',
        'promise' => 'the list takes its order from the header the desk clicked, not from the queue\'s oldest habit',
    ],
    [
        'name' => 'sort-never-echoed',
        'from' => "            'sort' => ListSort::present(\$sort, \$sortable),\n",
        'to' => '',
        'case' => 'every_visible_column_is_offered_and_nothing_else_is',
        'promise' => 'the page is told the applied order and the sortable set',
    ],
    [
        'name' => 'filters-never-echoed',
        'from' => "            'filters' => \$request->only(['search']) + [\n                'sort' => \$sort['column'],\n                'direction' => \$sort['direction'],\n            ],",
        'to' => "            'filters' => \$request->only(['search']),",
        'case' => 'a_search_and_an_order_are_both_echoed_so_neither_control_resets',
        'promise' => 'a key the desk sent comes back, so no control resets while its list stays narrowed',
    ],
    [
        'name' => 'surname-spec-loses-its-join',
        'from' => "            'name' => ['column' => 'lastName', 'join' => 'students', 'foreign' => 'studentId', 'local' => 'enrollments.studentId'],",
        'to' => "            'name' => ['column' => 'lastName'], // mutant: the hop is gone, so the order asks the enrollment for a surname",
        'case' => 'the_queue_can_be_ordered_by_the_students_surname_one_hop_away',
        'promise' => 'a column one table away is reached through the hop that holds it',
    ],
    [
        'name' => 'enum-key-withheld',
        'from' => "            'enrollmentStatus' => ['column' => 'enrollmentStatus'],\n",
        'to' => '',
        'case' => 'every_visible_column_is_offered_and_nothing_else_is',
        'promise' => 'an ENUM column carries a header, because the school\'s server orders it in the declaration order the school wrote',
    ],
    [
        'name' => 'direction-passed-through',
        'from' => "        \$sort = ListSort::apply(\n            \$query,\n            (string) \$request->input('sort', ''),",
        'to' => "        \$query->orderBy('enrollmentId', (string) \$request->input('direction', 'desc')); // mutant: unchecked\n        \$sort = ListSort::apply(\n            \$query,\n            (string) \$request->input('sort', ''),",
        'case' => 'the_direction_is_reduced_to_ascending_or_descending_and_not_passed_through',
        'promise' => 'the direction is reduced to asc or desc before it reaches the order clause',
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
    // Restore between legs, not only at shutdown: a later leg must measure the clean handler, and the first
    // harness in this family planted a defect and ran its sibling against it.
    file_put_contents($ctrlFile, $snapshot);
    clearstatcache();

    if ($verdict[0] === 'green') {
        echo 'SURVIVED '.$leg['name'].' — '.$leg['promise']."\n";
        echo '         the named case stayed green, so nothing pins the promise.\n';
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

$after = $runCase('the_queue_opens_newest_enrollment_first_when_nothing_is_asked');
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
