<?php

/**
 * Mutant harness for the audit log as the thirteenth sortable table (§28.3 G-40).
 *
 * Each leg plants one defect, runs the ONE case that owns it, and requires that case to print `1 failed`. A
 * mutant that does not compile, a needle that matches nothing, a filter that selects no test, and a control that
 * is not green are all refused — the four ways this repository has been burned by a harness that reported a pass
 * about a thing it never measured.
 *
 * Two legs are here because the screen is the first one where they could be: `user-hop-became-inner` is the desk
 * level half of the LEFT join (the helper's own class proves the mechanism; this proves a real handler asks for
 * it, and that 93% of the log's rows are not silently deleted by a header click), and `tile-cloned-after-paging`
 * is lane 47's SILENT-ZERO shape on the screen whose tile counts the whole filtered set.
 *
 * Usage: php storage/app/scratch/mutate-g40-auditlog-sort-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
if (! is_file($php)) {
    fwrite(STDERR, "REFUSED: no PHP at {$php}\n");

    exit(1);
}

$controller = $root.'/app/Http/Controllers/Admin/UserManagementController.php';
$test = 'tests/Feature/Sorting/AuditLogSortTest.php';
if (! is_file($controller) || ! is_file($root.'/'.$test)) {
    fwrite(STDERR, "REFUSED: the handler or its case file is missing.\n");

    exit(1);
}

$original = (string) file_get_contents($controller);
$hash0 = hash('sha256', $original);

$restore = function () use ($controller, $original, $hash0): void {
    $w = @file_put_contents($controller, $original);
    if ($w !== strlen($original) || hash('sha256', (string) file_get_contents($controller)) !== $hash0) {
        fwrite(STDERR, "REFUSED: could not restore the controller — git checkout it and re-run.\n");

        exit(1);
    }
};
register_shutdown_function($restore);

$legs = [
    [
        'name' => 'user-hop-became-inner',
        // The needle is the bytes as they are, without the trailing comma: the handler's line ends
        // `'type' => 'left'],` so a needle asking for `'left',` occurs zero times. Asserted against the line
        // before writing it, which is the only reason this leg was caught rather than reported as a pass.
        'from' => "'local' => 'auditlogs.userId', 'type' => 'left'",
        'to' => "'local' => 'auditlogs.userId'",
        'case' => 'ordering_by_the_user_keeps_the_rows_that_have_no_account_behind_them',
        'promise' => 'a hop whose key is NULL must not delete 93% of the desk list',
    ],
    [
        'name' => 'order-never-applied',
        'from' => "        \$sort = ListSort::apply(\n            \$query,",
        'to' => "        \$sort = ['column' => 'createdAt', 'direction' => 'desc'];\n        ListSort::apply(\n            Auditlogs::query(),",
        'case' => 'each_offered_column_orders_the_list_by_that_column_and_not_by_the_record_key',
        'promise' => 'the requested column is what orders the rows the desk is shown',
    ],
    [
        'name' => 'sort-never-echoed',
        'from' => "                'sort' => \$sort['column'],\n",
        'to' => '',
        'case' => 'a_key_that_is_not_listed_falls_back_without_reaching_the_query',
        'promise' => 'the order the handler applied is reported back in filters',
    ],
    [
        'name' => 'direction-never-echoed',
        'from' => "                'direction' => \$sort['direction'],\n",
        'to' => '',
        'case' => 'a_filter_submit_and_an_order_survive_each_other',
        'promise' => 'a control the desk set must come back or it resets while the list stays narrowed',
    ],
    [
        'name' => 'derived-column-offered',
        'from' => "            'auditId' => ['column' => 'auditId'],",
        'to' => "            'auditId' => ['column' => 'auditId'],\n            'description' => ['column' => 'newValues'],",
        'case' => 'a_column_the_desk_paints_from_json_offers_no_order',
        'promise' => 'a header is painted only for a column the query can answer in SQL',
    ],
    [
        'name' => 'tile-cloned-after-paging',
        'from' => "        \$actionCounts = (clone \$query)",
        'to' => "        \$pageForTest = \$query->paginate(50)->withQueryString();\n        \$actionCounts = (clone \$pageForTest->getCollection())",
        'case' => 'the_tile_counts_the_whole_filtered_set_and_is_untouched_by_the_order',
        'promise' => 'paginate() mutates the builder, so a tile cloned after it is a page total',
    ],
    [
        'name' => 'hard-order-restored-beside-the-sort',
        'from' => "        \$logs = \$query->paginate(50)->withQueryString();",
        'to' => "        \$logs = \$query->reorder('auditId', 'desc')->paginate(50)->withQueryString();",
        'case' => 'each_offered_column_orders_the_list_by_that_column_and_not_by_the_record_key',
        'promise' => 'the applied order survives to the page, it is not overridden by an old id order',
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
[$f, $s, $o] = runCase($php, $test, '');
if (! $s || $f) {
    fwrite(STDERR, "REFUSED: the control is not green, so no verdict below would mean anything.\n".substr($o, 0, 400)."\n");
    exit(1);
}
echo "  ok   control green\n";

$bad = 0;
foreach ($legs as $leg) {
    $n = substr_count($original, $leg['from']);
    if ($n !== 1) {
        printf("  BAD  %-34s needle occurs %d time(s), wanted 1 — the handler moved and this leg proves nothing\n", $leg['name'], $n);
        $bad++;

        continue;
    }
    $mutated = str_replace($leg['from'], $leg['to'], $original);
    if ($mutated === $original) {
        printf("  BAD  %-34s MUTANT DID NOT APPLY — the replacement changed no bytes\n", $leg['name']);
        $bad++;

        continue;
    }
    $w = @file_put_contents($controller, $mutated);
    if ($w !== strlen($mutated)) {
        printf("  BAD  %-34s PLANT FAILED — the write returned %s, so this leg measured an untouched handler\n", $leg['name'], var_export($w, true));
        $bad++;
        $restore();

        continue;
    }
    $lint = [];
    exec(escapeshellarg($php).' -l '.escapeshellarg($controller).' 2>&1', $lint, $lintRc);
    if ($lintRc !== 0) {
        printf("  BAD  %-34s the planted bytes do not parse — that is not a killed defect\n", $leg['name']);
        $bad++;
        $restore();

        continue;
    }
    [$failed, $selected] = runCase($php, $test, $leg['case']);
    if (! $selected) {
        printf("  BAD  %-34s --filter selected no test\n", $leg['name']);
        $bad++;
    } elseif ($failed) {
        printf("  ok   %-34s killed by %s\n", $leg['name'], $leg['case']);
    } else {
        printf("  BAD  %-34s SURVIVED — %s stayed green, so nothing reads that promise\n", $leg['name'], $leg['case']);
        $bad++;
    }
    $restore();
}

[$f2, $s2, $o2] = runCase($php, $test, '');
if (! $s2 || $f2) {
    fwrite(STDERR, "REFUSED: the trailing control is not green — this run left the handler or the tree wrong.\n".substr($o2, 0, 400)."\n");
    exit(1);
}
echo "  ok   trailing control green, handler restored by hash\n";

printf("%s ALL KILLED: %d leg(s), %d bad\n", $bad === 0 ? 'ok  ' : 'FAIL', count($legs), $bad);
exit($bad === 0 ? 0 : 1);
