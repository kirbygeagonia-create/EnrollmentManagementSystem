<?php

/**
 * Mutant harness for the majors list (§28.3 G-40's seventeenth table).
 *
 * Five legs, one per promise. The order-applied-to-another-builder leg is the one this file keeps having to write:
 * three actions in this controller now carry the same two opening lines of the `ListSort::apply` call, so a needle
 * that stops at `$query,` plants in whichever method the engine reaches first and the named case then fails for a
 * reason this leg never claimed — the span below is anchored all the way to the action's own fallback key.
 *
 * Usage: php storage/app/scratch/mutate-g40-major-sort-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
if (! is_file($php)) {
    fwrite(STDERR, "REFUSED: no PHP at {$php}\n");

    exit(1);
}

$controller = $root.'/app/Http/Controllers/Admin/ReferenceDataController.php';
$test = 'tests/Feature/Sorting/MajorListSortTest.php';
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

$applyHead = "        \$sort = ListSort::apply(\n            \$query,\n            (string) \$request->input('sort', ''),\n"
    ."            (string) \$request->input('direction', 'asc'),\n            \$sortable,\n            'majorName',";

$legs = [
    [
        'name' => 'order-applied-to-another-builder',
        'from' => $applyHead,
        'to' => str_replace('            $query,', '            Majors::query(),', $applyHead),
        'case' => 'the_course_header_orders_through_the_hop_and_the_row_keeps_one_shape',
        'promise' => 'the order lands on the builder the desk is paged from, filters and all',
    ],
    [
        'name' => 'title-default-reverted-to-descending',
        'from' => "(string) \$request->input('direction', 'asc'),\n            \$sortable,\n            'majorName',",
        'to' => "(string) \$request->input('direction', ''),\n            \$sortable,\n            'majorName',",
        'case' => 'the_list_opens_on_the_title_ascending_and_not_on_the_newest_record',
        'promise' => 'a reference list states its own ascending default beside its fallback column',
    ],
    [
        'name' => 'hop-labelled-without-the-join',
        'from' => "'course' => ['column' => 'courseName', 'join' => 'courses', 'foreign' => 'courseId', 'local' => 'majors.courseId'],",
        'to' => "'course' => ['column' => 'courseName'],",
        'case' => 'the_course_header_orders_through_the_hop_and_the_row_keeps_one_shape',
        'promise' => 'a column one table away is reached through the hop that holds it',
    ],
    [
        'name' => 'course-header-withheld',
        'from' => "            'course' => ['column' => 'courseName', 'join' => 'courses', 'foreign' => 'courseId', 'local' => 'majors.courseId'],\n",
        'to' => '',
        'case' => 'the_three_painted_columns_are_the_only_ones_offered',
        'promise' => 'every column the screen paints is one the handler can order',
    ],
    [
        'name' => 'order-never-echoed',
        'from' => "\$request->only(['search', 'courseId']) + [\n                'sort' => \$sort['column'],",
        'to' => "\$request->only(['search', 'courseId']) + [",
        'case' => 'a_course_filter_a_search_and_an_order_are_all_echoed_and_still_narrow',
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
    $case = $leg['case'];
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
    [$caseFailed, $caseSelected, $caseOut] = runCase($php, $test, $case);
    if (! $caseSelected) {
        printf("  BAD  %-42s --filter selected no test [%s]\n", $leg['name'], $case);
        $bad++;
    } elseif ($caseFailed) {
        printf("  ok   %-42s killed by %s\n", $leg['name'], $case);
    } else {
        printf("  BAD  %-42s SURVIVED — %s stayed green, so nothing reads that promise\n", $leg['name'], $case);
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
