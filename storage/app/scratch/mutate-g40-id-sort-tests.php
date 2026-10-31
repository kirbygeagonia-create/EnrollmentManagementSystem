<?php

/**
 * Proves the ID desk's sorting guard can fail (G-40's eighth wired table).
 *
 * Thirteen legs. Each plants one way the promise could stop meaning anything and requires the NAMED case to report
 * a failure — not merely "not passed": a mutant that does not compile, and a --filter that selects no test, both
 * read as a non-pass and a lazy leg would call either a kill. `1 errored` is accepted beside `1 failed`, and the
 * two join-less legs are read for which one actually answered, because a surname the query cannot reach is not a
 * wrong sequence — it is a list the desk would never have rendered.
 *
 * Three legs are this screen's own shape rather than a repeat of the seven before it: the tab must be echoed (it is
 * the third key the desk sends, and a control that resets while the list stays narrowed is the defect §28.3 owns
 * for filters), the order must be applied to the base the TAB chose and not to the other one, and the derived
 * ID Status column must stay out of the offered set.
 *
 * Deliberately NOT planted: the two tile pairs read from the un-ordered bases. Both hops are many-to-one through
 * NOT NULL keys, so no arrangement of rows moves a count; and the clone-before-`paginate()` guard would only differ
 * on a queue longer than the page, which is lane 47's instrument with 25 to 60 rows, not this fixture's three.
 *
 * Usage: php storage/app/scratch/mutate-g40-id-sort-tests.php
 */
$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$testFile = 'tests/Feature/Sorting/IDQueueSortTest.php';

$sortFile = $root.'/app/Support/ListSort.php';
$ctrlFile = $root.'/app/Http/Controllers/ID/IDController.php';

$targets = [$sortFile, $ctrlFile];
$snapshots = [];
foreach ($targets as $path) {
    $bytes = (string) file_get_contents($path);
    $snapshots[$path] = ['bytes' => $bytes, 'sha1' => sha1($bytes)];
}

// A second copy of this harness overlapping the first would snapshot a file that already carries a planted defect
// and write it back as the "original" — lane 80's permanent mutant in an untracked file. Refuse at the door.
$plantedShapes = [
    '// mutant: the one-hop spelling is read as no join at all',
    '// mutant: available answered from the applied pair',
    '// mutant: trust the request',
    '// mutant: unknown column goes through',
    '// mutant: unchecked',
    '// mutant: the asked column is dropped before the allow-list sees it',
    '// mutant: the tab the desk is standing on is not echoed',
    '// mutant: the order is applied to the queue base whatever the tab says',
    '// mutant: a header invented for a column this screen never had',
    '// mutant: the course header orders the surname',
    '// mutant: the school number header orders the surname',
    '// mutant: the surname ordered with no join',
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
        'name' => 'one-hop-spec-read-as-no-join',
        'file' => $sortFile,
        'from' => "            ? [['join' => \$spec['join'], 'foreign' => \$spec['foreign'], 'local' => \$spec['local']]]\n",
        'to' => "            ? [] // mutant: the one-hop spelling is read as no join at all\n",
        'case' => 'the_queue_can_be_ordered_by_the_students_surname_one_hop_away',
        'promise' => 'a column one table away is reached by the join written join/foreign/local',
    ],
    [
        'name' => 'available-read-from-applied',
        'file' => $sortFile,
        'from' => "        return \$applied + ['available' => array_keys(\$allowed)];",
        'to' => "        return \$applied + ['available' => array_keys(\$applied)]; // mutant: available answered from the applied pair",
        'case' => 'the_enum_columns_are_offered_and_the_derived_request_status_is_not',
        'promise' => "the screen is offered the handler's own set, never a set it guessed",
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
        'case' => 'the_queue_can_be_ordered_by_the_students_surname_one_hop_away',
        'promise' => 'the desk really orders by the column it asked for',
    ],
    [
        'name' => 'tab-never-echoed',
        'file' => $ctrlFile,
        'from' => "'filters' => \$request->only(['search', 'tab']) + [",
        'to' => "'filters' => \$request->only(['search']) + [ // mutant: the tab the desk is standing on is not echoed",
        'case' => 'a_tab_a_search_and_an_order_are_all_echoed_so_no_control_resets',
        'promise' => 'every key the desk sent comes back, the tab included — this screen sends three',
    ],
    [
        'name' => 'order-applied-to-the-other-base',
        'file' => $ctrlFile,
        'from' => "        \$list = (clone (\$tab === 'issued' ? \$issuedBase : \$queueBase))\n",
        'to' => "        \$list = (clone \$queueBase) // mutant: the order is applied to the queue base whatever the tab says\n",
        'case' => 'the_issued_tab_orders_its_own_set_and_keeps_its_own_rows',
        'promise' => 'the tab chooses which set the order arranges',
    ],
    [
        'name' => 'sort-never-echoed',
        'file' => $ctrlFile,
        'from' => "            'sort' => ListSort::present(\$sort, \$sortable),\n",
        'to' => '',
        'case' => 'the_enum_columns_are_offered_and_the_derived_request_status_is_not',
        'promise' => 'the page is told the applied order and the sortable set',
    ],
    [
        'name' => 'filters-never-echoed',
        'file' => $ctrlFile,
        'from' => "                'sort' => \$sort['column'],\n                'direction' => \$sort['direction'],\n",
        'to' => '',
        'case' => 'a_tab_a_search_and_an_order_are_all_echoed_so_no_control_resets',
        'promise' => 'the order comes back beside the tab and the search, so no control resets',
    ],
    [
        'name' => 'invented-key-listed',
        'file' => $ctrlFile,
        'from' => "            'enrollmentId' => ['column' => 'enrollments.enrollmentId'],",
        'to' => "            'enrollmentId' => ['column' => 'enrollments.enrollmentId'],\n            'cohort' => ['column' => 'enrollments.cohortId'], // mutant: a header invented for a column this screen never had",
        'case' => 'the_enum_columns_are_offered_and_the_derived_request_status_is_not',
        'promise' => 'a header exists only for a column the handler really holds — an invented key is the same offered-and-ignored defect',
    ],
    [
        'name' => 'course-misbound-to-surname',
        'file' => $ctrlFile,
        'from' => "            'course' => ['column' => 'courseName', 'join' => 'courses', 'foreign' => 'courseId', 'local' => 'enrollments.courseId'],",
        'to' => "            'course' => ['column' => 'lastName', 'join' => 'students', 'foreign' => 'studentId', 'local' => 'enrollments.studentId'], // mutant: the course header orders the surname",
        'case' => 'the_course_column_orders_the_program_and_not_the_student',
        'promise' => 'the Course header orders the program, not the student',
    ],
    [
        'name' => 'schoolId-misbound-to-surname',
        'file' => $ctrlFile,
        'from' => "            'schoolId' => ['column' => 'schoolIdNumber', 'join' => 'students', 'foreign' => 'studentId', 'local' => 'enrollments.studentId'],",
        'to' => "            'schoolId' => ['column' => 'lastName', 'join' => 'students', 'foreign' => 'studentId', 'local' => 'enrollments.studentId'], // mutant: the school number header orders the surname",
        'case' => 'the_school_number_column_orders_by_the_number_and_not_by_the_name',
        'promise' => 'the School ID header orders the number, not the name it sits beside',
    ],
    [
        'name' => 'surname-spec-loses-its-join',
        'file' => $ctrlFile,
        'from' => "            'name' => ['column' => 'lastName', 'join' => 'students', 'foreign' => 'studentId', 'local' => 'enrollments.studentId'],",
        'to' => "            'name' => ['column' => 'lastName'], // mutant: the surname ordered with no join",
        'case' => 'a_second_click_on_the_same_header_reverses_it',
        'promise' => 'a relation column is named with the hop that reaches it, or the query cannot be built at all',
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
