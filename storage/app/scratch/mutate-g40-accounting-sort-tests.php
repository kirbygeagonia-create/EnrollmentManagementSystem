<?php

/**
 * Proves the accounting queue's sorting guard can fail (G-40's sixth wired table).
 *
 * Ten legs. Each plants one way the promise could stop meaning anything and requires the NAMED case to report a
 * failure — not merely "not passed": a mutant that does not compile, and a --filter that selects no test, both read
 * as a non-pass and a lazy leg would call either a kill. `1 errored` is accepted beside `1 failed` and printed as
 * what it is, because the three mechanism legs make the engine answer "no such column", which is the defect working.
 *
 * Six legs sit in the handler (the order never applied, the tab-and-order echo lost, the sortable set never handed
 * to the page, a derived column listed anyway, and two keys pointed at the wrong column — amount against balance is
 * the pair this fixture was built to tell apart); four sit in `ListSort` itself (the wrong hop's alias, an unchecked
 * direction, a bypassed allow-list, and the fallback).
 *
 * Deliberately NOT planted: the summary and the tab counts cloned before the join exists. Both hops are many-to-one
 * through NOT NULL keys, so no arrangement of rows moves a peso figure or a count — a leg asserting a failure there
 * would measure a state that cannot be built. The surname case reads the five tile figures anyway, so the ordering
 * is at least observed.
 *
 * Usage: php storage/app/scratch/mutate-g40-accounting-sort-tests.php
 */
$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$testFile = 'tests/Feature/Sorting/AccountingQueueSortTest.php';

$sortFile = $root.'/app/Support/ListSort.php';
$ctrlFile = $root.'/app/Http/Controllers/Accounting/AccountingController.php';

$targets = [$sortFile, $ctrlFile];
$snapshots = [];
foreach ($targets as $path) {
    $bytes = (string) file_get_contents($path);
    $snapshots[$path] = ['bytes' => $bytes, 'sha1' => sha1($bytes)];
}

// A second copy of this harness overlapping the first would snapshot a file that already carries a planted defect
// and write it back as the "original" — lane 80's permanent mutant in an untracked file. Refuse at the door.
$plantedShapes = [
    "// mutant: the hops are applied backwards",
    "// mutant: qualifies with the first hop, not the table that holds the column",
    "// mutant: trust the request",
    "// mutant: unchecked",
    "// mutant: the asked column is dropped before the allow-list sees it",
    "// mutant: a derived sum offered as if it were a column",
    "// mutant: the balance header orders the billed amount",
    "// mutant: the date header orders the row key",
];
foreach ($snapshots as $path => $s) {
    foreach ($plantedShapes as $shape) {
        if (str_contains($s['bytes'], $shape)) {
            fwrite(STDERR, "REFUSED: ".basename($path)." already carries {$shape} — a previous run did not restore.\n");

            exit(1);
        }
    }
}

register_shutdown_function(static function () use ($snapshots): void {
    foreach ($snapshots as $path => $s) {
        if (sha1((string) file_get_contents($path)) !== $s['sha1']) {
            file_put_contents($path, $s['bytes']);
        }
    }
});

$legs = [
    [
        'name' => 'hop-qualified-from-first',
        'file' => $sortFile,
        'from' => "\$orderColumn = 'srt'.(count(\$joins) - 1).'.'.\$orderColumn;",
        'to' => "\$orderColumn = 'srt0.'.\$orderColumn; // mutant: qualifies with the first hop, not the table that holds the column",
        'case' => 'the_queue_can_be_ordered_by_the_students_surname_across_two_hops',
        'promise' => 'a column two hops away is ordered on the hop that actually holds it',
    ],
    [
        'name' => 'allow-list-bypassed',
        'file' => $sortFile,
        'from' => "\$column = array_key_exists((string) \$asked, \$allowed) ? (string) \$asked : \$fallback;",
        'to' => '$column = $asked === "" ? $fallback : (string) $asked; // mutant: trust the request',
        'spec' => "\$spec = \$allowed[\$column];",
        'toSpec' => "\$spec = \$allowed[\$column] ?? ['column' => \$column]; // mutant: unknown column goes through",
        'case' => 'a_column_the_handler_never_listed_falls_back_and_never_reaches_the_query',
        'promise' => 'a key outside the handler’s list never reaches the query',
    ],
    [
        'name' => 'direction-passed-through',
        'file' => $sortFile,
        'from' => "\$direction = strtolower((string) \$askedDirection) === 'asc' ? 'asc' : 'desc';",
        'to' => '$direction = (string) $askedDirection; // mutant: unchecked',
        'case' => 'the_direction_is_reduced_to_ascending_or_descending_and_not_passed_through',
        'promise' => 'the direction is reduced to asc or desc, not echoed into the order clause',
    ],
    [
        'name' => 'sort-never-applied',
        'file' => $ctrlFile,
        'from' => "            (string) \$request->input('sort', ''),\n            (string) \$request->input('direction', ''),\n            \$sortable,",
        'to' => "            '', // mutant: the asked column is dropped before the allow-list sees it\n            (string) \$request->input('direction', ''),\n            \$sortable,",
        'case' => 'the_assessment_date_column_orders_the_day_the_sheet_was_raised',
        'promise' => 'the queue really orders by the column the desk asked for',
    ],
    [
        'name' => 'sort-never-echoed',
        'file' => $ctrlFile,
        'from' => "            'sort' => ListSort::present(\$sort, \$sortable),\n",
        'to' => '',
        'case' => 'a_derived_column_is_not_offered_because_the_query_cannot_answer_it',
        'promise' => 'the page is told the applied order and the sortable set',
    ],
    [
        'name' => 'filters-never-echoed',
        'file' => $ctrlFile,
        'from' => "                'sort' => \$sort['column'],\n                'direction' => \$sort['direction'],\n",
        'to' => '',
        'case' => 'a_tab_a_search_and_an_order_are_all_echoed_so_no_control_resets',
        'promise' => 'every key the desk sent comes back, so no control resets while its list stays narrowed',
    ],
    [
        'name' => 'derived-key-listed',
        'file' => $ctrlFile,
        'from' => "            'date' => ['column' => 'studentassessments.assessmentDate'],",
        'to' => "            'date' => ['column' => 'studentassessments.assessmentDate'],\n            'coveredBy' => ['column' => 'studentassessments.assessmentId'], // mutant: a derived sum offered as if it were a column",
        'case' => 'a_derived_column_is_not_offered_because_the_query_cannot_answer_it',
        'promise' => 'a header appears only for a column the handler can order in SQL',
    ],
    [
        'name' => 'balance-misbound',
        'file' => $ctrlFile,
        'from' => "            'balance' => ['column' => 'studentassessments.remainingBalance'],",
        'to' => "            'balance' => ['column' => 'studentassessments.totalAssessedAmount'], // mutant: the balance header orders the billed amount",
        'case' => 'the_money_columns_order_the_amounts_and_the_two_of_them_are_different_sequences',
        'promise' => 'the Balance header orders what is owed, not what was billed',
    ],
    [
        'name' => 'date-misbound',
        'file' => $ctrlFile,
        'from' => "            'date' => ['column' => 'studentassessments.assessmentDate'],",
        'to' => "            'date' => ['column' => 'studentassessments.assessmentId'], // mutant: the date header orders the row key",
        'case' => 'the_assessment_date_column_orders_the_day_the_sheet_was_raised',
        'promise' => 'the Assessed Date header orders the day the sheet was raised',
    ],
    [
        'name' => 'name-column-not-joined',
        'file' => $ctrlFile,
        'from' => "                    ['join' => 'students', 'foreign' => 'studentId', 'local' => 'srt0.studentId'],\n                ],\n            ],\n            'schoolId' => [",
        'to' => "                ],\n            ],\n            'schoolId' => [",
        'case' => 'a_second_click_on_the_same_header_reverses_it',
        'promise' => 'a two-hop column is reached by both hops, not ordered on the wrong table',
    ],
];

$runCase = static function (string $case) use ($php, $root, $testFile): array {
    chdir($root);
    $out = (string) preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec(
        escapeshellarg($php).' artisan test '.escapeshellarg($testFile).' --filter='.$case.' 2>&1'
    ));
    $failed = preg_match('/(\d+) failed/', $out, $fm) ? (int) $fm[1] : 0;
    $errored = preg_match('/(\d+) errored/', $out, $em) ? (int) $em[1] : 0;
    $passed = preg_match('/(\d+) passed/', $out, $pm) ? (int) $pm[1] : 0;
    if (! str_contains($out, 'Tests:') || ($failed === 0 && $passed === 0 && $errored === 0)) {
        return ['unreadable', $out];
    }

    if ($failed > 0) {
        return [$failed.'-failed', $out];
    }

    return [$errored > 0 ? $errored.'-errored' : 'green', $out];
};

$killed = ['1-failed', '1-errored'];
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
    $path = $leg['file'];
    $src = $snapshots[$path]['bytes'];
    $hits = substr_count($src, $leg['from']);
    if ($hits !== 1) {
        echo 'BAD  '.$leg['name'].": the needle occurs {$hits} time(s) — the tree is not the batch this proves.\n";
        $bad++;

        continue;
    }
    $mutant = str_replace($leg['from'], $leg['to'], $src);
    if (isset($leg['spec'])) {
        if (substr_count($mutant, $leg['spec']) !== 1) {
            echo 'BAD  '.$leg['name'].": the second needle occurs ".substr_count($mutant, $leg['spec'])." time(s).\n";
            $bad++;

            continue;
        }
        $mutant = str_replace($leg['spec'], $leg['toSpec'], $mutant);
    }
    if ($mutant === $src) {
        echo 'BAD  '.$leg['name'].": the plant changed no byte.\n";
        $bad++;

        continue;
    }
    file_put_contents($path, $mutant);
    $verdict = $runCase($leg['case']);
    file_put_contents($path, $snapshots[$path]['bytes']);

    if (in_array($verdict[0], $killed, true)) {
        echo 'ok   '.$leg['name'].': '.$leg['promise'].' — its case reports one '.$verdict[0]."\n";

        continue;
    }
    echo 'BAD  '.$leg['name'].': verdict '.$verdict[0]." (want a single failure or a single error)\n".mb_substr($verdict[1], 0, 400)."\n";
    $bad++;
}

echo "control again: every case green after every restore\n";
foreach ($legs as $leg) {
    if ($runCase($leg['case'])[0] !== 'green') {
        echo 'BAD  final control for '.$leg['name']."\n";
        $bad++;
    }
}
foreach ($targets as $path) {
    if (sha1((string) file_get_contents($path)) !== $snapshots[$path]['sha1']) {
        echo 'BAD  restore: '.basename($path)." is not the bytes it was handed.\n";
        $bad++;
    }
}

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL KILLED' : 'REFUSE', count($legs), $bad);

exit($bad === 0 ? 0 : 1);
