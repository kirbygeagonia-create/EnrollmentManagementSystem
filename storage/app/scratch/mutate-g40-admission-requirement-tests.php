<?php

/**
 * Falsifier for the intake requirement catalogue's order (§28.3 G-40, the twenty-fifth wired table).
 *
 * One planted defect per promise the wiring makes, each of which must make the case that OWNS that promise print
 * `1 failed` naming its own assertion. A plant that does not compile also prints `1 failed`, so the enum leg points
 * the column at another REAL column and produces a plausible wrong sequence rather than an error.
 *
 * This screen contributes the shape the earlier harnesses have no way to test: its type filter must read the
 * `appliesTo` prop rather than a list written out beside it, so a leg puts the hand-written list BACK — twice, in
 * one file, because the const and the `options=` that consumes it are two separate lines and retiring only one of
 * them would leave the page reading correctly. `$leg2` takes the pairs for that; a single-needle helper cannot
 * describe a defect that lives in two places.
 *
 * Usage: php storage/app/scratch/mutate-g40-admission-requirement-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

$controller = 'app/Http/Controllers/Admin/ReferenceDataController.php';
$jsx = 'resources/js/Pages/Admin/ReferenceData/AdmissionRequirements.jsx';
$testFile = 'tests/Feature/Admin/AdmissionRequirementSortTest.php';

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

/**
 * Plant one or more pairs in one file, run one filter, and require the named assertion to fail.
 *
 * @param  list<array{0: string, 1: string}>  $pairs
 */
$leg2 = static function (string $name, string $file, array $pairs, string $filter, string $needle, string $why) use (
    &$bad, &$legs, $runFile, $restore, $assertRestored, $testFile, $originals
): void {
    $legs++;
    $body = (string) file_get_contents($file);
    foreach ($pairs as [$from, $to]) {
        $hits = substr_count($body, $from);
        if ($hits !== 1) {
            echo str_pad($name, 44)."BAD   needle occurs {$hits} time(s), wanted exactly 1 — the leg's age, not a defect\n";
            $bad++;

            return;
        }
        if ($from === $to) {
            echo str_pad($name, 44)."BAD   the plant is a no-op by construction\n";
            $bad++;

            return;
        }
        $body = str_replace($from, $to, $body);
    }
    file_put_contents($file, $body);
    $after = (string) file_get_contents($file);
    // A plant that never reached the disk changes no verdict. The comparison is against the ORIGINAL bytes of THIS
    // file — not against `$body`, which always differs from what was read — and each planted text must be present.
    $landed = $after !== (string) $originals[$file]
        && (static fn (array $ps): bool => (bool) array_reduce(
            $ps, static fn (bool $c, array $p): bool => $c && str_contains($after, $p[1]), true))($pairs);
    if (! $landed) {
        echo str_pad($name, 44)."BAD   the plant did not land on disk\n";
        $bad++;
        $restore();

        return;
    }

    [$code, $out] = $runFile($testFile, $filter);
    $restore();

    if (str_contains($out, 'No tests found')) {
        echo str_pad($name, 44)."BAD   the filter selected no test\n";
        $bad++;
    } elseif ($code === 0 || ! str_contains($out, '1 failed')) {
        echo str_pad($name, 44).'SURVIVED — the promise is real but the case stayed green'."\n";
        $bad++;
    } elseif (! str_contains($out, $needle)) {
        echo str_pad($name, 44)."BAD   failed, but not naming its own assertion (wanted [{$needle}])\n";
        $bad++;
    } elseif (! $assertRestored()) {
        $bad++;
    } else {
        echo str_pad($name, 44)."ok    killed by the case that owns it — {$why}\n";
    }
};

$leg = static function (string $name, string $file, string $from, string $to, string $filter, string $needle, string $why) use ($leg2): void {
    $leg2($name, $file, [[$from, $to]], $filter, $needle, $why);
};

$control = static function (string $name, string $filter) use (&$bad, &$legs, $runFile, $testFile): void {
    $legs++;
    [$code, $out] = $runFile($testFile, $filter);
    if ($code !== 0 || ! str_contains($out, '1 passed')) {
        echo str_pad($name, 44).'BAD   control is not green: '.str_replace("\n", ' / ', mb_substr($out, -220))."\n";
        $bad++;

        return;
    }
    echo str_pad($name, 44)."ok    control green\n";
};

$control('control-enum-order', 'the_applies_to_header_sorts_the_enum_the_way_this_connection_sorts_it');
$control('control-offered-set', 'only_the_three_columns_the_query_holds_are_offered_as_orders');

$leg(
    'enum-header-pointed-at-the-name',
    $controller,
    "            'appliesTo' => ['column' => 'appliesTo'],",
    "            'appliesTo' => ['column' => 'requirementName'],",
    'the_applies_to_header_sorts_the_enum_the_way_this_connection_sorts_it',
    'did not answer the order this connection holds',
    'a header wired at another column still prints a tidy list, and only the declared enum order says otherwise'
);

$leg(
    'flag-header-pointed-at-the-name',
    $controller,
    "            'isRequired' => ['column' => 'isRequired'],",
    "            'isRequired' => ['column' => 'requirementName'],",
    'the_status_header_orders_the_flag_the_badge_is_painted_from',
    'the flag did not sort optional-before-required ascending',
    'the badge is painted from the flag, so an order that reads a different column is a header that lies'
);

$leg(
    'record-key-offered-as-an-order',
    $controller,
    "            'isRequired' => ['column' => 'isRequired'],
        ];",
    "            'isRequired' => ['column' => 'isRequired'],
            'requirementId' => ['column' => 'admissionrequirements.requirementId'],
        ];",
    'only_the_three_columns_the_query_holds_are_offered_as_orders',
    'the record key became an order on a screen that paints no id',
    'the tie-break keeps rows in one sequence across pages; it is not something the desk asked to sort by'
);

$leg2(
    'type-filter-list-hand-written-again',
    $jsx,
    [
        [
            "// The filter's choices used to be written out here as well as sent by the handler",
            "const appliesToOptions = [\n    { value: '', label: 'All Types' },\n    { value: 'firstYear', label: 'First Year' },\n];\n// The filter's choices used to be written out here as well as sent by the handler",
        ],
        ['options={typeOptions}', 'options={appliesToOptions}'],
    ],
    'the_type_filter_and_the_type_form_read_one_list_from_the_server',
    'the page spells the type list again',
    'two copies of one enum drift silently: the form would offer a new type and the filter would not'
);

$leg(
    'page-offers-a-header-the-handler-ignores',
    $jsx,
    "{ key: 'requirementName', label: 'Name', sortKey: 'requirementName' },",
    "{ key: 'requirementName', label: 'Name', sortKey: 'requirementCode' },",
    'every_header_the_page_paints_is_a_key_the_action_obeys',
    'the page offers a header for [requirementCode]',
    'DataTable paints a button only for a listed key, so an invented one is an affordance that silently never appears'
);

$leg(
    'painted-column-loses-its-header',
    $jsx,
    "{ key: 'isRequired', label: 'Status', sortKey: 'isRequired', render:",
    "{ key: 'isRequired', label: 'Status', render:",
    'every_header_the_page_paints_is_a_key_the_action_obeys',
    'a painted column lost its header',
    'the column stays on the screen and the order stays in the handler; only the count of headers notices the loss'
);

$leg(
    'ascending-default-flipped',
    $controller,
    "            (string) \$request->input('direction', 'asc'),
            \$sortable,
            'requirementName',",
    "            (string) \$request->input('direction', 'desc'),
            \$sortable,
            'requirementName',",
    'the_catalogue_opens_with_the_requirement_names_reading_top_to_bottom',
    'a catalogue with no direction asked for opens ascending',
    "the helper's descending answer is a queue's need, not a catalogue's"
);

$leg(
    'name-column-pointed-at-the-key',
    $controller,
    "            'requirementName' => ['column' => 'requirementName'],",
    "            'requirementName' => ['column' => 'admissionrequirements.requirementId'],",
    'the_catalogue_opens_with_the_requirement_names_reading_top_to_bottom',
    'the catalogue did not open in name order',
    'the payload still reports the name as the applied column, so the sequence is the only surface that shows the swap'
);

$leg(
    'applied-order-never-echoed',
    $controller,
    "            'filters' => \$request->only(['search', 'appliesTo', 'status']) + [
                'sort' => \$sort['column'],
                'direction' => \$sort['direction'],
            ],",
    "            'filters' => \$request->only(['search', 'appliesTo', 'status']),",
    'a_filter_and_an_order_are_both_echoed_so_neither_control_resets',
    'the order the server obeyed never came back',
    "lane 46's NO-RETURN from the sort side, on a screen with three filters beside the order"
);

$leg(
    'fallback-moved-onto-another-real-column',
    $controller,
    "            'requirementName',
            'admissionrequirements.requirementId'",
    "            'isRequired',
            'admissionrequirements.requirementId'",
    'a_column_the_desk_never_lists_falls_back_to_the_catalogue_order',
    'an unlisted key did not fall back to the column the catalogue is ordered by anyway',
    'a forged key answering a different order than the page opens with is a second, invisible order'
);

$leg(
    'hard-coded-order-returns',
    $controller,
    '        $requirements = $query->paginate(20)->withQueryString();',
    "        \$query->orderByDesc('requirementId');
        \$requirements = \$query->paginate(20)->withQueryString();",
    'the_action_reads_its_own_request_and_no_hard_coded_order_survives',
    'the hard-coded order the mechanism replaced is back',
    'every number can stay equal while the last write wins the order — only the source read sees it'
);

$control('control-enum-order-after-plants', 'the_applies_to_header_sorts_the_enum_the_way_this_connection_sorts_it');
$control('control-offered-set-after-plants', 'only_the_three_columns_the_query_holds_are_offered_as_orders');

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL KILLED' : 'NOT ALL KILLED', $legs, $bad);
exit($bad === 0 ? 0 : 1);
