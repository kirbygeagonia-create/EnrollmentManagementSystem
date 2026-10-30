<?php

/**
 * Proves the clearance queue's sorting guard can fail (G-40's fourth wired table).
 *
 * Five legs. Each plants one way the promise could stop meaning anything and requires the NAMED case to report
 * exactly one failure — not merely "not passed": a mutant that does not compile, and a --filter that selects no
 * test, both read as a non-pass and a lazy leg would call either a kill.
 *
 *   sort-never-echoed     the handler orders correctly but never tells the page, so the header resets
 *   filters-never-echoed  the order is applied and reported in `sort` but not in `filters` — lane 46's NO-RETURN
 *   derived-key-listed    a column the query cannot answer is added to the allow-list, so a header paints for an
 *                         order that is a lie (Student Type and Standing are derived per page, not stored)
 *   direction-passed-through  the request string reaches the order clause unchecked
 *   sort-never-applied    the old single hard-coded order restored: the mechanism exists and nothing uses it
 *
 * Deliberately NOT planted, and said so rather than left to be rediscovered: the fact that the tiles are cloned
 * before the name join exists. The join is 1:1 through a NOT NULL foreign key, so no arrangement of rows can make
 * a tile count differ — a leg asserting a failure there would be measuring a fixture that cannot be built. The
 * ordering is defensive and the handler says so in its own comment.
 *
 * Usage: php storage/app/scratch/mutate-g40-clearance-sort-tests.php
 */
$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$testFile = 'tests/Feature/Sorting/ClearanceQueueSortTest.php';
$ctrlFile = $root.'/app/Http/Controllers/Clearance/ClearanceController.php';

$snapshot = (string) file_get_contents($ctrlFile);
$sha1 = sha1($snapshot);

register_shutdown_function(static function () use ($ctrlFile, $snapshot, $sha1): void {
    if (sha1((string) file_get_contents($ctrlFile)) !== $sha1) {
        file_put_contents($ctrlFile, $snapshot);
    }
});

$legs = [
    [
        'name' => 'sort-never-echoed',
        'from' => "            'sort' => ListSort::present(\$sort, \$sortable),\n",
        'to' => '',
        'case' => 'a_derived_column_is_not_offered_because_the_query_cannot_answer_it',
        'promise' => 'the page is told the applied order and the sortable set',
    ],
    [
        'name' => 'filters-never-echoed',
        'from' => "                'sort' => \$sort['column'],\n                'direction' => \$sort['direction'],\n",
        'to' => '',
        'case' => 'a_period_filter_and_an_order_are_both_echoed_so_neither_control_resets',
        'promise' => 'a key the desk sent comes back, so no control resets while its list stays narrowed',
    ],
    [
        'name' => 'derived-key-listed',
        'from' => "            'received' => ['column' => 'receivedDate'],",
        'to' => "            'received' => ['column' => 'receivedDate'],\n            'studentType' => ['column' => 'overallStatus'], // mutant: offers an order the page derives",
        'case' => 'a_derived_column_is_not_offered_because_the_query_cannot_answer_it',
        'promise' => 'a header appears only for a column the handler can order in SQL',
    ],
    [
        'name' => 'direction-passed-through',
        'from' => "        \$sort = ListSort::apply(\n            \$query,\n            (string) \$request->input('sort', ''),\n            (string) \$request->input('direction', ''),",
        'to' => "        \$query->orderBy('studentClearanceId', (string) \$request->input('direction', 'desc')); // mutant: unchecked\n        \$sort = ListSort::apply(\n            \$query,\n            (string) \$request->input('sort', ''),\n            (string) \$request->input('direction', ''),",
        'case' => 'the_direction_is_reduced_to_ascending_or_descending_and_not_passed_through',
        'promise' => 'the direction is reduced to asc or desc before it reaches the order clause',
    ],
    [
        'name' => 'sort-never-applied',
        // The defect, not the absence of the fix: the column the desk asked for is never considered, so every
        // request gets the queue's own newest-first order while the echo still claims what was applied.
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
    if (isset($leg['from2'])) {
        if (substr_count($mutant, $leg['from2']) !== 1) {
            echo 'BAD  '.$leg['name'].': the second needle occurs '.substr_count($mutant, $leg['from2'])." time(s).\n";
            $bad++;

            continue;
        }
        $mutant = str_replace($leg['from2'], $leg['to2'] ?? '', $mutant);
    }
    if ($mutant === $snapshot) {
        echo 'BAD  '.$leg['name'].": the plant changed no byte.\n";
        $bad++;

        continue;
    }
    file_put_contents($ctrlFile, $mutant);
    $verdict = $runCase($leg['case']);
    file_put_contents($ctrlFile, $snapshot);

    if ($verdict[0] === '1-failed') {
        echo 'ok   '.$leg['name'].': '.$leg['promise'].' — its case reports one failure'."\n";

        continue;
    }
    echo 'BAD  '.$leg['name'].': verdict '.$verdict[0]." (want 1-failed)\n".mb_substr($verdict[1], 0, 400)."\n";
    $bad++;
}

echo "control again: every case green after every restore\n";
foreach ($legs as $leg) {
    if ($runCase($leg['case'])[0] !== 'green') {
        echo 'BAD  final control for '.$leg['name']."\n";
        $bad++;
    }
}
if (sha1((string) file_get_contents($ctrlFile)) !== $sha1) {
    echo "BAD  restore: the controller is not the bytes it was handed.\n";
    $bad++;
}

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL KILLED' : 'REFUSE', count($legs), $bad);

exit($bad === 0 ? 0 : 1);
