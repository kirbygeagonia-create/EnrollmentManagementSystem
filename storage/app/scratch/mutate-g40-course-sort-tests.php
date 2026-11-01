<?php

/**
 * Mutant harness for the courses catalogue (§28.3 G-40's sixteenth table).
 *
 * Five legs, one per promise. Two of them exist because this screen is the first in the family where a boolean
 * column carries a header and where the hop crosses a NOT NULL key: the flag leg proves a board desk can group the
 * programs it flagged, and the join leg proves the Unit header is not a label painted over a column the query never
 * reached — without the join the ORDER asks `courses` for `unitName`, which is a 500 at the desk, not a wrong order.
 *
 * Every needle is anchored on a span unique to `courses()`, because `direction`, `sortable` and the apply call are
 * spelled identically in `subjects()` two methods above: a needle that matches two sites plants in one of them and
 * the named case then fails for a reason this leg never claimed.
 *
 * Usage: php storage/app/scratch/mutate-g40-course-sort-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
if (! is_file($php)) {
    fwrite(STDERR, "REFUSED: no PHP at {$php}\n");

    exit(1);
}

$controller = $root.'/app/Http/Controllers/Admin/ReferenceDataController.php';
$test = 'tests/Feature/Sorting/CourseListSortTest.php';
if (! is_file($controller) || ! is_file($root.'/'.$test)) {
    fwrite(STDERR, "REFUSED: the handler or its case file is missing.\n");

    exit(1);
}

$original = (string) file_get_contents($controller);
$hash0 = hash('sha256', $original);

$restore = function () use ($controller, $original, $hash0): void {
    $written = false;
    foreach ([0, 400_000, 1_200_000] as $wait) {
        if ($wait > 0) {
            usleep($wait);
        }
        $written = @file_put_contents($controller, $original);
        if ($written === strlen($original)) {
            break;
        }
    }
    if ($written !== strlen($original) || hash('sha256', (string) file_get_contents($controller)) !== $hash0) {
        fwrite(STDERR, "REFUSED: could not restore the handler, so the tree may still hold a planted defect.\n"
            ."  Repair it with: git checkout -- app/Http/Controllers/Admin/ReferenceDataController.php\n");

        exit(1);
    }
};
register_shutdown_function($restore);

$legs = [
    [
        'name' => 'tie-break-not-the-row-key',
        'from' => "            'courseCode',\n            'courses.courseId'",
        'to' => "            'courseCode',\n            'courses.courseName'",
        'case' => 'the_unit_header_orders_through_the_hop_and_the_row_keeps_one_shape',
        'promise' => 'two rows equal on the ordered value are settled by the row’s own key, so a page boundary cannot shuffle them',
    ],
    [
        'name' => 'catalogue-default-reverted-to-descending',
        'from' => "(string) \$request->input('direction', 'asc'),\n            \$sortable,\n            'courseCode',",
        'to' => "(string) \$request->input('direction', ''),\n            \$sortable,\n            'courseCode',",
        'case' => 'the_catalogue_opens_on_the_code_ascending_and_not_on_the_newest_record',
        'promise' => 'a catalogue states its own ascending default beside its fallback column',
    ],
    [
        'name' => 'flag-header-withheld',
        'from' => "            'requiresRetentionExam' => ['column' => 'requiresRetentionExam'],\n",
        'to' => '',
        'case' => 'every_flag_and_both_name_columns_carry_headers_and_nothing_else_does',
        'promise' => 'a flag the screen paints is a column the desk may order',
    ],
    [
        'name' => 'hop-labelled-without-the-join',
        'from' => "'unit' => ['column' => 'unitName', 'join' => 'academicunits', 'foreign' => 'unitId', 'local' => 'courses.unitId'],",
        'to' => "'unit' => ['column' => 'unitName'],",
        'case' => 'the_unit_header_orders_through_the_hop_and_the_row_keeps_one_shape',
        'promise' => 'a column one table away is reached through the hop that holds it',
    ],
    [
        'name' => 'order-never-echoed',
        'from' => "\$request->only(['search', 'unit']) + [\n                'sort' => \$sort['column'],",
        'to' => "\$request->only(['search', 'unit']) + [",
        'case' => 'a_unit_filter_a_search_and_an_order_are_all_three_echoed',
        'promise' => 'the order the handler applied is reported back inside filters',
    ],
];

function runCase(string $php, string $test, string $case): array
{
    $cmd = escapeshellarg($php).' artisan test '.escapeshellarg($test)
        .($case === '' ? '' : ' --filter='.escapeshellarg($case)).' 2>&1';
    $out = (string) preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec($cmd));

    return [preg_match('/Tests:\s+\d+ failed/', $out) === 1, preg_match('/Tests:\s/', $out) === 1, $out];
}

echo "control: the class must be green before anything is planted\n";
[$failed, $selected, $out] = runCase($php, $test, '');
if (! $selected || $failed) {
    fwrite(STDERR, "REFUSED: the control is not green, so no verdict below would mean anything.\n".substr($out, 0, 400)."\n");

    exit(1);
}
echo "  ok   control green\n";

$bad = 0;
foreach ($legs as $leg) {
    $n = substr_count($original, $leg['from']);
    if ($n !== 1) {
        printf("  BAD  %-42s needle occurs %d time(s), wanted 1 — the handler moved and this leg proves nothing\n", $leg['name'], $n);
        $bad++;

        continue;
    }
    $mutated = str_replace($leg['from'], $leg['to'], $original);
    if ($mutated === $original) {
        printf("  BAD  %-42s MUTANT DID NOT APPLY — the replacement changed no bytes\n", $leg['name']);
        $bad++;

        continue;
    }
    $written = @file_put_contents($controller, $mutated);
    if ($written !== strlen($mutated)) {
        printf("  BAD  %-42s PLANT FAILED — the write returned %s\n", $leg['name'], var_export($written, true));
        $bad++;
        $restore();

        continue;
    }
    exec(escapeshellarg($php).' -l '.escapeshellarg($controller).' 2>&1', $lint, $lintRc);
    if ($lintRc !== 0) {
        printf("  BAD  %-42s the planted bytes do not parse — that is not a killed defect\n", $leg['name']);
        $bad++;
        $restore();
        $lint = [];

        continue;
    }
    $lint = [];
    [$caseFailed, $caseSelected] = runCase($php, $test, $leg['case']);
    if (! $caseSelected) {
        printf("  BAD  %-42s --filter selected no test\n", $leg['name']);
        $bad++;
    } elseif ($caseFailed) {
        printf("  ok   %-42s killed by %s\n", $leg['name'], $leg['case']);
    } else {
        printf("  BAD  %-42s SURVIVED — %s stayed green, so nothing reads that promise\n", $leg['name'], $leg['case']);
        $bad++;
    }
    $restore();
}

if (hash('sha256', (string) file_get_contents($controller)) !== $hash0) {
    fwrite(STDERR, "REFUSED: the handler is not the snapshot's bytes after the run; restore it with git checkout and re-run.\n");

    exit(1);
}

[, $again, $after] = runCase($php, $test, '');
if ($again !== true || preg_match('/Tests:\s+\d+ failed/', $after) === 1) {
    fwrite(STDERR, "REFUSED: the trailing control is not green — this run left the handler or the tree wrong.\n");

    exit(1);
}
echo "  ok   trailing control green, handler restored by hash\n";

printf("%s ALL KILLED: %d leg(s), %d bad\n", $bad === 0 ? 'ok  ' : 'FAIL', count($legs), $bad);

exit($bad === 0 ? 0 : 1);
