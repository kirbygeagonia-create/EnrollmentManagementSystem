<?php

/**
 * Falsifier for the office directory's order (§28.3 G-40, the twenty-second wired table).
 *
 * One planted defect per promise the wiring makes, each of which must make the case that OWNS that promise print
 * `1 failed` naming its own assertion — a plant that does not compile also prints `1 failed` and proves nothing.
 * Controls run before and after, and both touched files are restored by hash in a shutdown hook.
 *
 * This screen's own contribution to the family is a defect planted in the OFFERED SET rather than in a column: seven
 * of the earlier harnesses can break a column mapping, and only a leg that hands the query a derived label proves the
 * membership claim that two of the four painted columns may not be ordered. Its pair plants the same inversion on the
 * screen side, where the page offers a header the handler would ignore.
 *
 * A landing check is taken from the plant's shape, not from a single rule: a deletion must leave its needle gone, an
 * insertion necessarily keeps the line it followed. One expectation for both kinds reports a correct plant as a
 * failure, which is what the scholarship harness's first run met.
 *
 * Usage: php storage/app/scratch/mutate-g40-office-directory-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

$controller = 'app/Http/Controllers/Admin/ReferenceDataController.php';
$jsx = 'resources/js/Pages/Admin/ReferenceData/Offices.jsx';
$testFile = 'tests/Feature/Admin/OfficeDirectorySortTest.php';

$targets = [$controller, $jsx];
$originals = [];
foreach ($targets as $f) {
    if (! is_file($f)) {
        fwrite(STDERR, "REFUSED: {$f} is not in the tree.\n");
        exit(1);
    }
    $originals[$f] = (string) file_get_contents($f);
}
$hashes = array_map('md5', $originals);

$restore = static function () use ($originals): void {
    foreach ($originals as $f => $body) {
        file_put_contents($f, $body);
    }
};
register_shutdown_function($restore);

$bad = 0;
$legs = 0;

$runFile = static function (string $file, string $filter) use ($root, $php): array {
    $out = [];
    exec('cd '.$root.' && "'.$php.'" artisan test '.$file.' --filter '.$filter.' 2>&1', $out, $code);

    return [(int) $code, preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $out))];
};

$assertRestored = static function () use ($targets, $hashes): bool {
    foreach ($targets as $f) {
        if (md5((string) file_get_contents($f)) !== $hashes[$f]) {
            echo '  TREE NOT RESTORED after '.basename($f)."\n";

            return false;
        }
    }

    return true;
};

$leg = static function (string $name, string $file, string $from, string $to, string $filter, string $needle, string $why) use (
    &$bad, &$legs, $runFile, $restore, $assertRestored, $testFile
): void {
    $legs++;
    $body = (string) file_get_contents($file);
    $hits = substr_count($body, $from);
    if ($hits !== 1) {
        echo str_pad($name, 38)."BAD   needle occurs {$hits} time(s), wanted exactly 1 — the leg's age, not a defect\n";
        $bad++;

        return;
    }
    if ($from === $to) {
        echo str_pad($name, 38)."BAD   the plant is a no-op by construction\n";
        $bad++;

        return;
    }
    file_put_contents($file, str_replace($from, $to, $body));
    $after = (string) file_get_contents($file);
    $landed = $after !== $body && str_contains($after, $to) && ($to === '' ? ! str_contains($after, $from) : true);
    if (! $landed) {
        echo str_pad($name, 38)."BAD   the plant did not land on disk\n";
        $bad++;
        $restore();

        return;
    }

    [$code, $out] = $runFile($testFile, $filter);
    $restore();

    if (str_contains($out, 'No tests found')) {
        echo str_pad($name, 38)."BAD   the filter selected no test\n";
        $bad++;
    } elseif ($code === 0 || ! str_contains($out, '1 failed')) {
        echo str_pad($name, 38).'SURVIVED — the promise is real but the case stayed green'."\n";
        $bad++;
    } elseif (! str_contains($out, $needle)) {
        echo str_pad($name, 38)."BAD   failed, but not naming its own assertion (wanted [{$needle}])\n";
        $bad++;
    } elseif (! $assertRestored()) {
        $bad++;
    } else {
        echo str_pad($name, 38)."ok    killed by the case that owns it — {$why}\n";
    }
};

$control = static function (string $name, string $filter) use (&$bad, &$legs, $runFile, $testFile): void {
    $legs++;
    [$code, $out] = $runFile($testFile, $filter);
    if ($code !== 0 || ! str_contains($out, '1 passed')) {
        echo str_pad($name, 38).'BAD   control is not green: '.str_replace("\n", ' / ', mb_substr($out, -220))."\n";
        $bad++;

        return;
    }
    echo str_pad($name, 38)."ok    control green\n";
};

$control('control-default-order', 'the_directory_opens_with_the_names_reading_top_to_bottom');
$control('control-offered-set', 'only_the_two_columns_the_query_holds_are_offered_as_orders');

$leg(
    'name-column-pointed-at-the-key',
    $controller,
    "            'officeName' => ['column' => 'officeName'],",
    "            'officeName' => ['column' => 'offices.officeId'],",
    'the_directory_opens_with_the_names_reading_top_to_bottom',
    'the directory did not open in name order',
    'the header a board clicks would order by the record id it never paints'
);

$leg(
    'ascending-default-flipped',
    $controller,
    "            (string) \$request->input('direction', 'asc'),
            \$sortable,
            'officeName',",
    "            (string) \$request->input('direction', 'desc'),
            \$sortable,
            'officeName',",
    'the_directory_opens_with_the_names_reading_top_to_bottom',
    'a directory with no direction asked for opens ascending',
    "the helper's descending answer is a queue's need, not a directory's"
);

$leg(
    'derived-label-offered-as-an-order',
    $controller,
    "            'officeId' => ['column' => 'offices.officeId'],",
    "            'officeId' => ['column' => 'offices.officeId'],
            'workflowName' => ['column' => 'officeName'],",
    'only_the_two_columns_the_query_holds_are_offered_as_orders',
    'an order was offered for something the query does not hold',
    'the desk name is answered from the enum after the query runs, so a header for it orders a column that is not there'
);

$leg(
    'usage-counts-offered-as-an-order',
    $jsx,
    "            key: 'usage',",
    "            key: 'usage', sortKey: 'staffCount',",
    'every_header_the_page_paints_is_a_key_the_action_obeys',
    'the page offers a header for [staffCount]',
    'the grouped counts live in a PHP array built after the page, and the screen would paint a control nothing answers'
);

$leg(
    'applied-order-never-echoed',
    $controller,
    "            'filters' => \$request->only(['search']) + [
                'sort' => \$sort['column'],
                'direction' => \$sort['direction'],
            ],",
    "            'filters' => \$request->only(['search']),",
    'a_filter_and_an_order_are_both_echoed_so_neither_control_resets',
    'the order the server obeyed never came back',
    "lane 46's NO-RETURN from the sort side: the header resets while the list stays ordered"
);

$leg(
    'fallback-moved-onto-the-record-key',
    $controller,
    "            'officeName',
            'offices.officeId'",
    "            'officeId',
            'offices.officeId'",
    'a_column_the_board_never_lists_falls_back_to_the_directory_order',
    'an unlisted key did not fall back to the column the directory is ordered by anyway',
    'a forged key answering a different order than the page opens with is a second, invisible order'
);

$leg(
    'hard-coded-order-returns',
    $controller,
    "        \$offices = \$query->paginate(20)->withQueryString();",
    "        \$query->orderBy('officeId');
        \$offices = \$query->paginate(20)->withQueryString();",
    'the_action_reads_its_own_request_and_no_hard_coded_order_survives',
    'the hard-coded order the mechanism replaced is back',
    'every number can stay equal while the last write wins the order — only the source read sees it'
);

$control('control-default-order-after-plants', 'the_directory_opens_with_the_names_reading_top_to_bottom');
$control('control-offered-set-after-plants', 'only_the_two_columns_the_query_holds_are_offered_as_orders');

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL KILLED' : 'NOT ALL KILLED', $legs, $bad);
exit($bad === 0 ? 0 : 1);
