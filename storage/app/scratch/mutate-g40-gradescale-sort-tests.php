<?php

/**
 * Mutant harness for the grade-scale bands (§28.3 G-40's eighteenth table).
 *
 * Four legs. There is deliberately no leg that removes the stable tie-break: on SQLite two rows sharing a floor come
 * back in insertion order anyway, so such a leg could not fail, and a leg that cannot fail is decoration — the
 * promise is instead stated in the register row and read in the browser by whoever re-measures it.
 *
 * Two legs share one case on purpose (withhold a painted column, add one that is not painted alone): the
 * `sort.available` list is the single place the screen's offer is recorded, so it must move in both directions.
 *
 * Usage: php storage/app/scratch/mutate-g40-gradescale-sort-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
if (! is_file($php)) {
    fwrite(STDERR, "REFUSED: no PHP at {$php}\n");

    exit(1);
}

$controller = $root.'/app/Http/Controllers/Admin/ReferenceDataController.php';
$test = 'tests/Feature/Sorting/GradeScaleSortTest.php';
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
        'name' => 'scale-default-reverted-to-descending',
        'from' => "(string) \$request->input('direction', 'asc'),\n            \$sortable,\n            'range',",
        'to' => "(string) \$request->input('direction', ''),\n            \$sortable,\n            'range',",
        'case' => 'the_bands_open_on_the_floor_ascending_which_is_how_a_scale_is_read',
        'promise' => 'a grade scale is read from the floor up, stated by the handler',
    ],
    [
        'name' => 'result-header-withheld',
        'from' => "            'isPassing' => ['column' => 'isPassing'],\n",
        'to' => '',
        'case' => 'only_the_three_painted_columns_are_offered_and_the_ceiling_still_arrives',
        'promise' => 'a column the desk paints carries a header',
    ],
    [
        'name' => 'unpainted-column-offered',
        'from' => "            'description' => ['column' => 'description'],\n",
        'to' => "            'description' => ['column' => 'description'],\n            'maxGrade' => ['column' => 'maxGrade'],\n",
        'case' => 'only_the_three_painted_columns_are_offered_and_the_ceiling_still_arrives',
        'promise' => 'no header is offered for a value the list never paints on its own',
    ],
    [
        'name' => 'order-never-echoed',
        'from' => "\$request->only(['search', 'isPassing']) + [\n                'sort' => \$sort['column'],",
        'to' => "\$request->only(['search', 'isPassing']) + [",
        'case' => 'a_search_a_passing_filter_and_an_order_are_all_echoed_and_still_narrow',
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
        printf("  BAD  %-40s needle occurs %d time(s), wanted 1 — the handler moved and this leg proves nothing\n", $leg['name'], $n);
        $bad++;

        continue;
    }
    $mutated = str_replace($leg['from'], $leg['to'], $original);
    if ($mutated === $original) {
        printf("  BAD  %-40s MUTANT DID NOT APPLY — the replacement changed no bytes\n", $leg['name']);
        $bad++;

        continue;
    }
    $written = @file_put_contents($controller, $mutated);
    if ($written !== strlen($mutated)) {
        printf("  BAD  %-40s PLANT FAILED — the write returned %s\n", $leg['name'], var_export($written, true));
        $bad++;
        $restore();

        continue;
    }
    exec(escapeshellarg($php).' -l '.escapeshellarg($controller).' 2>&1', $lint, $lintRc);
    if ($lintRc !== 0) {
        printf("  BAD  %-40s the planted bytes do not parse — that is not a killed defect\n", $leg['name']);
        $bad++;
        $restore();
        $lint = [];

        continue;
    }
    $lint = [];
    [$caseFailed, $caseSelected] = runCase($php, $test, $leg['case']);
    if (! $caseSelected) {
        printf("  BAD  %-40s --filter selected no test\n", $leg['name']);
        $bad++;
    } elseif ($caseFailed) {
        printf("  ok   %-40s killed by %s\n", $leg['name'], $leg['case']);
    } else {
        printf("  BAD  %-40s SURVIVED — %s stayed green, so nothing reads that promise\n", $leg['name'], $leg['case']);
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
