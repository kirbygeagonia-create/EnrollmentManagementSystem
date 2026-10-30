<?php

/**
 * Proves the assessment queue's sorting guard can fail (G-40's fifth wired table, and the first column two hops
 * from the row the list shows).
 *
 * Eight legs. Each plants one way the promise could stop meaning anything and requires the NAMED case to report a
 * failure — not merely "not passed": a mutant that does not compile, and a --filter that selects no test, both read
 * as a non-pass and a lazy leg would call either a kill.
 *
 * A leg also accepts `1 errored`, and says which it saw. The three mechanism legs (wrong hop, reversed chain,
 * allow-list bypassed) make MySQL or SQLite answer "no such column", which PHPUnit counts as an error rather than a
 * failure; that IS the defect working — a forged or mis-aliased identifier reaching the order clause — so the
 * verdict is read for both spellings and refused for neither.
 *
 *   hop-qualified-from-first   the surname is ordered on the first hop's alias, not the table that holds it
 *   chain-reversed             the two joins are applied in the wrong order
 *   allow-list-bypassed        the request string used as the column, so a forged key reaches the query
 *   direction-passed-through   the direction handed to orderBy() unchecked
 *   sort-never-echoed          the handler orders correctly but never tells the page which set it obeys
 *   filters-never-echoed       the order is applied and reported in `sort` but not in `filters` — lane 46's NO-RETURN
 *   sort-never-applied         the old single hard-coded order restored: the mechanism exists and nothing uses it
 *   derived-key-listed         Payment Status is offered although the screen derives it from two columns
 *
 * Deliberately NOT planted: the tiles-clone-before-the-join ordering. Both joins are many-to-one through NOT NULL
 * keys, so no arrangement of rows can move a summary number — a leg asserting a failure there would measure a state
 * that cannot be built. The handler comments the ordering and the surname case reads the four tile figures anyway.
 *
 * Usage: php storage/app/scratch/mutate-g40-assessment-sort-tests.php
 */
$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$testFile = 'tests/Feature/Sorting/AssessmentQueueSortTest.php';

$sortFile = $root.'/app/Support/ListSort.php';
$ctrlFile = $root.'/app/Http/Controllers/Assessment/AssessmentController.php';

$targets = [$sortFile, $ctrlFile];
$snapshots = [];
foreach ($targets as $path) {
    $bytes = (string) file_get_contents($path);
    $snapshots[$path] = ['bytes' => $bytes, 'sha1' => sha1($bytes)];
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
        'name' => 'chain-reversed',
        'file' => $sortFile,
        'from' => 'foreach ($joins as $n => $join) {',
        'to' => 'foreach (array_reverse($joins) as $n => $join) { // mutant: the hops are applied backwards',
        'case' => 'a_second_click_on_the_same_header_reverses_it',
        'promise' => 'each hop names a local that an earlier hop or the base table owns',
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
        'name' => 'sort-never-echoed',
        'file' => $ctrlFile,
        'from' => "            'sort' => ListSort::present(\$sort, \$sortable),\n",
        'to' => '',
        'case' => 'a_column_the_query_cannot_answer_is_not_offered',
        'promise' => 'the page is told the applied order and the sortable set',
    ],
    [
        'name' => 'filters-never-echoed',
        'file' => $ctrlFile,
        'from' => "                'sort' => \$sort['column'],\n                'direction' => \$sort['direction'],\n",
        'to' => '',
        'case' => 'a_search_filter_and_an_order_are_both_echoed_so_neither_control_resets',
        'promise' => 'a key the desk sent comes back, so no control resets while its list stays narrowed',
    ],
    [
        'name' => 'sort-never-applied',
        'file' => $ctrlFile,
        'from' => "            (string) \$request->input('sort', ''),\n            (string) \$request->input('direction', ''),\n            \$sortable,",
        'to' => "            '', // mutant: the asked column is dropped before the allow-list sees it\n            (string) \$request->input('direction', ''),\n            \$sortable,",
        'case' => 'the_amount_column_orders_the_money_and_not_the_row_key',
        'promise' => 'the queue really orders by the column the desk asked for',
    ],
    [
        'name' => 'derived-key-listed',
        'file' => $ctrlFile,
        'from' => "            'balance' => ['column' => 'studentassessments.remainingBalance'],",
        'to' => "            'balance' => ['column' => 'studentassessments.remainingBalance'],\n            'paymentStatus' => ['column' => 'studentassessments.assessmentId'], // mutant: offers an order the page derives",
        'case' => 'a_column_the_query_cannot_answer_is_not_offered',
        'promise' => 'a header appears only for a column the handler can order in SQL',
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
