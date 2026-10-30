<?php

/**
 * Proves the student directory's sorting guard can fail (§28.3 G-40, the second screen wired).
 *
 * Each leg plants one way the promise could stop meaning anything and requires the NAMED case to report exactly one
 * failure — not merely "not passed": a mutant that does not compile, and a --filter that selects no test, both read
 * as a non-pass and a lazy leg reports either as a kill.
 *
 *   default-direction-lost   the handler stops asking for its own ascending default, and the directory opens at Z
 *   sort-never-applied       the old two-key hard-coded order restored: the mechanism exists and nothing uses it
 *   sort-never-echoed        the handler orders correctly but never tells the page, so the header resets
 *   filters-never-echoed     the order is applied and reported in `sort` but not in `filters`, which is the shape
 *                            lane 46 charges as NO-RETURN: the control blanks while the list stays narrowed
 *   sortable-key-removed     one key leaves the handler's list while the screen still offers its header
 *   column-misbound          the ID header is wired to the surname column, so the list is ordered by something the
 *                            desk did not ask for and the echo still claims success
 *
 * Deliberately NOT planted: the stable tie-break. On SQLite rows tied on the sort column usually come back in
 * insertion order anyway, so a leg asserting a failure there would report the database's mood, not a defect.
 *
 * Usage: php storage/app/scratch/mutate-g40-directory-sort-tests.php
 */
$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$testFile = 'tests/Feature/Sorting/StudentsDirectorySortTest.php';
$ctrlFile = $root.'/app/Http/Controllers/StudentController.php';

$targets = [$ctrlFile];
$snapshots = [];
foreach ($targets as $path) {
    $bytes = (string) file_get_contents($path);
    $snapshots[$path] = ['bytes' => $bytes, 'sha1' => sha1($bytes)];
}

// Captured by reference: a shutdown handler holding a copy of the array as it was when the closure was created
// sees nothing of what the run wrote, and the tree keeps the last mutant.
register_shutdown_function(static function () use (&$snapshots): void {
    foreach ($snapshots as $path => $s) {
        if (sha1((string) file_get_contents($path)) !== $s['sha1']) {
            file_put_contents($path, $s['bytes']);
        }
    }
});

$legs = [
    [
        'name' => 'default-direction-lost',
        'from' => "(string) \$request->input('direction', 'asc'),",
        'to' => "(string) \$request->input('direction', ''), // mutant: the helper's descending answer becomes the list's default",
        'case' => 'the_directory_opens_in_surname_order_ascending',
        'promise' => 'a directory reads top to bottom unless the desk says otherwise',
    ],
    [
        'name' => 'sort-never-applied',
        'from' => "        \$sort = ListSort::apply(\n            \$query,\n            (string) \$request->input('sort', ''),\n            // A directory reads top to bottom unless the desk says otherwise, so this list's own default is the\n            // ascending one — the helper's absence-of-direction answer is descending, which is what a queue of\n            // newest-first records wants and is not what a surname list wants.\n            (string) \$request->input('direction', 'asc'),\n            \$sortable,\n            'name',\n            'studentId'\n        );",
        'to' => "        \$query->orderBy('lastName')->orderBy('firstName');\n        \$sort = ['column' => 'name', 'direction' => 'asc'];",
        'case' => 'a_different_column_orders_the_rows_differently_from_the_surname_order',
        'promise' => 'the list really orders by the column the desk asked for',
    ],
    [
        'name' => 'sort-never-echoed',
        'from' => "            'sort' => ListSort::present(\$sort, \$sortable),\n",
        'to' => '',
        'case' => 'the_page_is_told_which_order_was_applied_and_which_it_may_ask_for',
        'promise' => 'the page is told the applied order and the sortable set',
    ],
    [
        'name' => 'filters-never-echoed',
        'from' => "                'sort' => \$sort['column'],\n                'direction' => \$sort['direction'],\n",
        'to' => '',
        'case' => 'a_search_and_an_order_are_both_echoed_so_neither_control_resets',
        'promise' => 'a key the desk sent comes back, so no control resets while its list stays narrowed',
    ],
    [
        'name' => 'sortable-key-removed',
        'from' => "            'gender' => ['column' => 'gender'],\n",
        'to' => '',
        'case' => 'the_page_is_told_which_order_was_applied_and_which_it_may_ask_for',
        'promise' => 'the header offers only what the handler obeys',
    ],
    [
        'name' => 'column-misbound',
        'from' => "            'schoolId' => ['column' => 'schoolIdNumber'],",
        'to' => "            'schoolId' => ['column' => 'lastName'], // mutant: the header lies about what it orders",
        'case' => 'a_different_column_orders_the_rows_differently_from_the_surname_order',
        'promise' => 'the School ID header orders by the school number, not by the surname',
    ],
];

$runCase = static function (string $case) use ($php, $root, $testFile): array {
    chdir($root);
    $cmd = escapeshellarg($php).' artisan test '.escapeshellarg($testFile)
        .' --filter='.$case.' 2>&1';
    $out = (string) preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec($cmd));
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
    $c = $runCase($leg['case']);
    if ($c[0] !== 'green') {
        echo 'BAD control for '.$leg['name'].' — '.$c[0]."\n";
        fwrite(STDERR, "REFUSED: the control is not green, so no later verdict can be read.\n");

        exit(1);
    }
}
echo "ok   control\n";

foreach ($legs as $leg) {
    $src = $snapshots[$ctrlFile]['bytes'];
    $hits = substr_count($src, $leg['from']);
    if ($hits !== 1) {
        echo 'BAD  '.$leg['name'].": the needle occurs {$hits} time(s) — the tree is not the batch this proves.\n";
        $bad++;

        continue;
    }
    $mutant = str_replace($leg['from'], $leg['to'], $src);
    if ($mutant === $src) {
        echo 'BAD  '.$leg['name'].": the plant changed no byte.\n";
        $bad++;

        continue;
    }
    file_put_contents($ctrlFile, $mutant);
    $verdict = $runCase($leg['case']);
    file_put_contents($ctrlFile, $snapshots[$ctrlFile]['bytes']);

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
foreach ($targets as $path) {
    if (sha1((string) file_get_contents($path)) !== $snapshots[$path]['sha1']) {
        echo 'BAD  restore: '.basename($path)." is not the bytes it was handed.\n";
        $bad++;
    }
}

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL KILLED' : 'REFUSE', count($legs), $bad);

exit($bad === 0 ? 0 : 1);
