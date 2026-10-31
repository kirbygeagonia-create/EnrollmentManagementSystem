<?php

/**
 * Proves the Blocking roster's sorting guard can fail (G-40's eleventh wired table).
 *
 * Six legs, each planting one way the promise could stop meaning anything and requiring the NAMED case to report
 * exactly one failure — not merely "not passed", because a mutant that does not compile and a --filter that selects
 * no test both read as a non-pass and a lazy leg would call either a kill.
 *
 *   old-hard-order-restored   the `orderByDesc('blockId')` this wiring replaced is restored beside the mechanism;
 *                             `ListSort::apply()` appends, so a first key on the row's own id makes every header a
 *                             no-op while the echo still claims the clicked column
 *   sort-never-echoed         the handler orders correctly but never tells the page
 *   filters-never-echoed      the order is applied and reported in `sort` but not in `filters` — lane 46's NO-RETURN
 *   course-spec-loses-its-join the program column is listed as a bare `blocks.courseName`, which answers a SQL
 *                             error at the desk rather than a wrong sequence
 *   computed-key-listed       Available is added to the allow-list on top of a real column, so a header paints for
 *                             a number the query cannot order (it is `maxStudents` less a per-row count)
 *   sort-never-applied        the column the desk asked for is dropped before the allow-list sees it
 *
 * Deliberately NOT planted, and said so rather than left to be rediscovered:
 *   (a) moving `ListSort::apply()` above the tile read. The tiles here sum a collection, not an aggregate over a
 *       joined builder, so the seat count and the place total answer the same numbers either way — a leg asserting
 *       a failure there would measure a fixture that cannot be built. `lane47-tile-scope.php` owns the clone-order
 *       verdict for the screens where it IS decisive (PINNED-SELECT 0 today).
 *   (b) widening the row through the hop. That promise is pinned by `the_join_that_orders_a_column_never_reaches_the_payload`,
 *       and the mechanism behind it is `ListSort`'s own select pin, which `mutate-g40-enum-offer-and-select-pin.php`
 *       kills on this screen's helper. On THIS screen the pin never even fires — `withCount` leaves the builder
 *       holding a qualified `blocks.*` — which is exactly what that case proves and why it compares the two key
 *       sets rather than enumerating them.
 *
 * Usage: php storage/app/scratch/mutate-g40-blocking-sort-tests.php
 */
$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$testFile = 'tests/Feature/Sorting/BlockingQueueSortTest.php';
$ctrlFile = $root.'/app/Http/Controllers/Blocking/BlockingController.php';

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
        'from' => "            ->when(\$request->yearLevel, fn (\$q, \$level) => \$q->where('yearLevel', \$level));",
        'to' => "            ->when(\$request->yearLevel, fn (\$q, \$level) => \$q->where('yearLevel', \$level))\n            ->orderByDesc('blockId'); // mutant: the retired single order, restored beside the mechanism",
        'case' => 'the_block_name_column_orders_by_the_name_and_not_by_the_record_key',
        'promise' => 'the list takes its order from the header the desk clicked',
    ],
    [
        'name' => 'sort-never-echoed',
        'from' => "            'sort' => ListSort::present(\$sort, \$sortable),\n",
        'to' => '',
        'case' => 'the_two_labels_that_are_no_column_offer_no_header',
        'promise' => 'the page is told the applied order and the sortable set',
    ],
    [
        'name' => 'filters-never-echoed',
        'from' => "            'filters' => \$request->only(['search', 'courseId', 'termId', 'yearLevel']) + [\n                'sort' => \$sort['column'],\n                'direction' => \$sort['direction'],\n            ],",
        'to' => "            'filters' => \$request->only(['search', 'courseId', 'termId', 'yearLevel']),",
        'case' => 'a_filter_and_an_order_are_both_echoed_so_neither_control_resets',
        'promise' => 'a key the desk sent comes back, so no control resets while its list stays narrowed',
    ],
    [
        'name' => 'course-spec-loses-its-join',
        'from' => "            'course' => ['column' => 'courseName', 'join' => 'courses', 'foreign' => 'courseId', 'local' => 'blocks.courseId'],",
        'to' => "            'course' => ['column' => 'courseName'], // mutant: the hop is gone, so the order asks the block for a program name",
        'case' => 'the_course_column_orders_the_program_through_a_join_the_row_never_shows',
        'promise' => 'a column one table away is reached through the hop that holds it',
    ],
    [
        'name' => 'computed-key-listed',
        'from' => "            'capacity' => ['column' => 'maxStudents'],",
        'to' => "            'capacity' => ['column' => 'maxStudents'],\n            'available' => ['column' => 'maxStudents'], // mutant: offers a header for a number the query cannot order",
        'case' => 'the_two_labels_that_are_no_column_offer_no_header',
        'promise' => 'a header appears only for a value the handler can order in SQL',
    ],
    [
        'name' => 'sort-never-applied',
        'from' => "            (string) \$request->input('sort', ''),\n            (string) \$request->input('direction', ''),\n            \$sortable,",
        'to' => "            '', // mutant: the asked column is dropped before the allow-list sees it\n            (string) \$request->input('direction', ''),\n            \$sortable,",
        'case' => 'the_capacity_column_orders_the_places_the_block_holds',
        'promise' => 'the roster really orders by the column the desk asked for',
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

$after = $runCase('the_roster_opens_newest_block_first_when_nothing_is_asked');
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
