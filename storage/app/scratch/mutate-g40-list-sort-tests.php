<?php

/**
 * Proves the G-40 sorting guard can fail.
 *
 * Four legs, each planting one way the promise could stop meaning anything, and each requiring the NAMED case to
 * report a failure — not merely "not passed", because a mutant that does not compile or a filter that selects no
 * test both read as a non-pass and would be reported as a kill by a lazy leg.
 *
 *   allow-list-bypassed      the request string used as the column, so a forged key reaches the query
 *   direction-passed-through the direction handed to orderBy() unchecked
 *   sort-never-echoed        the handler orders correctly but never tells the page, so the header resets
 *   sort-never-applied       the old single hard-coded order restored: the mechanism exists and nothing uses it
 *
 * Deliberately NOT planted: the stable tie-break column. On SQLite two rows tied on the primary key usually come
 * back in insertion order anyway, so a leg asserting a failure there would be reporting the database's mood, not a
 * defect — an unreachable branch in a measuring tool is the same liability as an unread surface.
 *
 * Usage: php storage/app/scratch/mutate-g40-list-sort-tests.php
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$testFile = 'tests/Feature/Registrar/RegistrarQueueSortTest.php';

$sortFile = $root.'/app/Support/ListSort.php';
$ctrlFile = $root.'/app/Http/Controllers/Registrar/RegistrarController.php';

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
        'from' => "'sort' => ListSort::present(\$sort, \$sortable),",
        'to' => '',
        'case' => 'the_page_is_told_which_order_was_applied_and_which_it_may_ask_for',
        'promise' => 'the page is told the applied order and the sortable set',
    ],
    [
        'name' => 'sort-never-applied',
        'file' => $ctrlFile,
        'from' => "        \$sort = ListSort::apply(\n            \$query,\n            (string) \$request->input('sort', ''),\n            (string) \$request->input('direction', ''),\n            \$sortable,\n            'enrollmentId',\n            'enrollments.enrollmentId'\n        );",
        'to' => "        \$query->orderByDesc('enrollments.enrollmentId');\n        \$sort = ['column' => 'enrollmentId', 'direction' => 'desc'];",
        'case' => 'the_queue_can_be_ordered_by_the_students_surname',
        'promise' => 'the queue really orders by the column the desk asked for',
    ],
];

$runCase = static function (string $case) use ($php, $root, $testFile): array {
    chdir($root);
    $cmd = escapeshellarg($php).' artisan test '.escapeshellarg($testFile)
        .' --filter='.escapeshellarg($case).' 2>&1';
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

    if ($verdict[0] === '1-failed') {
        echo 'ok   '.$leg['name'].': '.$leg['promise'].' — its case reports one failure'."\n";

        continue;
    }
    echo 'BAD  '.$leg['name'].': verdict '.$verdict[0]." (want 1-failed)\n".mb_substr($verdict[1], 0, 300)."\n";
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
