<?php

/**
 * Mutant harness for the staff directory (§28.3 G-40's fifteenth table) and the search that had to be grouped
 * before the order meant anything.
 *
 * Six legs, one per promise, each requiring the case that owns it to print `1 failed`. The leg that matters most is
 * `search-alternatives-ungrouped`: it puts back the bytes the screen shipped with, where the search's own `orWhere`
 * calls sat at the builder's top level and ORed away the office filter — measured on live `ems` as 11 of 21 accounts
 * returned for a request that filtered one office holding 3. A sort harness that only tests the order would leave
 * that defect in place while every case stayed green.
 *
 * Usage: php storage/app/scratch/mutate-g40-staff-directory-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
if (! is_file($php)) {
    fwrite(STDERR, "REFUSED: no PHP at {$php}\n");

    exit(1);
}

$controller = $root.'/app/Http/Controllers/Admin/UserManagementController.php';
$test = 'tests/Feature/Admin/StaffDirectorySortTest.php';
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
            ."  Repair it with: git checkout -- app/Http/Controllers/Admin/UserManagementController.php\n");

        exit(1);
    }
};
register_shutdown_function($restore);

// The pre-fix search: every alternative chained at the top level of the same builder the office filter sits on.
$groupedSearch = <<<'PHP'
            ->when($request->search, fn ($q, $search) => $q->where(function ($sq) use ($search) {
                $sq->where('firstName', 'like', "%{$search}%")
                    ->orWhere('lastName', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('employeeNo', $search);
            }));
PHP;

$ungroupedSearch = <<<'PHP'
            ->when($request->search, fn ($q, $search) => $q->where('firstName', 'like', "%{$search}%")->orWhere('lastName', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%")->orWhere('employeeNo', $search));
PHP;

$legs = [
    [
        'name' => 'directory-default-reverted-to-descending',
        'from' => "(string) \$request->input('direction', 'asc'),",
        'to' => "(string) \$request->input('direction', ''),",
        'case' => 'the_directory_opens_on_the_surname_ascending_and_not_on_the_newest_record',
        'promise' => 'a directory states its own ascending default instead of inheriting the queue answer',
    ],
    [
        'name' => 'fallback-named-the-column-not-the-key',
        'from' => "            \$sortable,\n            'name',\n            'staffusers.userId'",
        'to' => "            \$sortable,\n            'lastName',\n            'staffusers.userId'",
        'case' => 'the_username_header_orders_and_an_unlisted_key_falls_back_to_the_directory_default',
        'promise' => 'the echoed fallback is a key the header can recognise as its own',
    ],
    [
        'name' => 'left-hop-put-back-to-inner',
        'from' => "'local' => 'staffusers.officeId', 'type' => 'left'],",
        'to' => "'local' => 'staffusers.officeId'],",
        'case' => 'ordering_by_office_keeps_the_account_that_has_none_on_the_list',
        'promise' => 'a hop over a nullable key may not delete the row it cannot reach',
    ],
    [
        'name' => 'office-header-withheld',
        'from' => "            'office' => ['column' => 'officeName', 'join' => 'offices', 'foreign' => 'officeId', 'local' => 'staffusers.officeId', 'type' => 'left'],\n",
        'to' => '',
        'case' => 'a_column_built_from_a_to_many_relation_offers_no_header',
        'promise' => 'the office the screen paints is one the query can order',
    ],
    [
        'name' => 'search-alternatives-ungrouped',
        'from' => $groupedSearch,
        'to' => $ungroupedSearch,
        'case' => 'the_office_filter_survives_the_search_because_the_alternatives_are_grouped',
        'promise' => 'a search narrows the list the office filter chose instead of widening it',
    ],
    [
        'name' => 'order-never-echoed',
        'from' => "\$request->only(['officeId', 'status', 'search']) + [\n                'sort' => \$sort['column'],",
        'to' => "\$request->only(['officeId', 'status', 'search']) + [",
        'case' => 'filter_order_and_direction_are_all_echoed_so_no_control_resets',
        'promise' => 'a key the desk sent and the handler obeyed comes back in filters',
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
        printf("  BAD  %-42s PLANT FAILED — the write returned %s, so this leg measured an untouched handler\n", $leg['name'], var_export($written, true));
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
