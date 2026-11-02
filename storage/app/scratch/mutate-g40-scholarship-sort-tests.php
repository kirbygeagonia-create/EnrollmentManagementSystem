<?php

/**
 * Falsifier for the scholarship catalogue's order (§28.3 G-40, the twenty-first wired table).
 *
 * One planted defect per promise the wiring makes, each of which must make the case that OWNS that promise print
 * `1 failed` naming its own assertion — a plant that does not compile also prints `1 failed` and proves nothing.
 * Controls run before and after, and both touched files are restored by hash in a shutdown hook.
 *
 * Two surfaces are patched, not one: six defects live in the controller's `$sortable` / echo / order, and the
 * seventh lives in the JSX, where a header spelled for a key the action refuses is the same defect the backend
 * cases cannot see (lane 46's DEAD-SEND reached from the screen side).
 *
 * Every needle is anchored on a line unique to the scholarshipTypes block, because `ReferenceDataController` holds a
 * dozen actions that share this exact shape: a needle like "the direction input" matches all of them and the plant
 * would land in a sibling action while the named case stayed green — the T06 lesson, which is a needle unique in
 * the FILE but not in the METHOD it targets.
 *
 * Usage: php storage/app/scratch/mutate-g40-scholarship-sort-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

$controller = 'app/Http/Controllers/Admin/ReferenceDataController.php';
$jsx = 'resources/js/Pages/Admin/ReferenceData/ScholarshipTypes.jsx';
$testFile = 'tests/Feature/Admin/ScholarshipTypeSortTest.php';

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
        echo str_pad($name, 34)."BAD   needle occurs {$hits} time(s), wanted exactly 1 — the leg's age, not a defect\n";
        $bad++;

        return;
    }
    if ($from === $to) {
        echo str_pad($name, 34)."BAD   the plant is a no-op by construction\n";
        $bad++;

        return;
    }
    file_put_contents($file, str_replace($from, $to, $body));
    $after = (string) file_get_contents($file);
    // A DELETION plant must leave its needle gone; an INSERTION plant necessarily keeps it, because the inserted
    // line is followed by the original. One expectation for both shapes refuses a correct write — the append hazard
    // the docx passes record — so the check is taken from the plant's own shape.
    $landed = $after !== $body && str_contains($after, $to) && ($to === '' ? ! str_contains($after, $from) : true);
    if (! $landed) {
        echo str_pad($name, 34)."BAD   the plant did not land on disk\n";
        $bad++;
        $restore();

        return;
    }

    [$code, $out] = $runFile($testFile, $filter);
    $restore();

    if (str_contains($out, 'No tests found')) {
        echo str_pad($name, 34)."BAD   the filter selected no test\n";
        $bad++;
    } elseif ($code === 0 || ! str_contains($out, '1 failed')) {
        echo str_pad($name, 34).'SURVIVED — the promise is real but the case stayed green'."\n";
        $bad++;
    } elseif (! str_contains($out, $needle)) {
        echo str_pad($name, 34)."BAD   failed, but not naming its own assertion (wanted [{$needle}])\n";
        $bad++;
    } elseif (! $assertRestored()) {
        $bad++;
    } else {
        echo str_pad($name, 34)."ok    killed by the case that owns it — {$why}\n";
    }
};

$control = static function (string $name, string $filter) use (&$bad, &$legs, $runFile, $testFile): void {
    $legs++;
    [$code, $out] = $runFile($testFile, $filter);
    if ($code !== 0 || ! str_contains($out, '1 passed')) {
        echo str_pad($name, 34).'BAD   control is not green: '.str_replace("\n", ' / ', mb_substr($out, -220))."\n";
        $bad++;

        return;
    }
    echo str_pad($name, 34)."ok    control green\n";
};

$control('control-default-order', 'the_catalogue_opens_with_the_names_reading_top_to_bottom');
$control('control-screen-pairing', 'every_header_the_page_paints_is_a_key_the_action_obeys');

$leg(
    'name-column-pointed-at-the-key',
    $controller,
    "            'scholarshipName' => ['column' => 'scholarshipName'],",
    "            'scholarshipName' => ['column' => 'scholarshiptypes.scholarshipTypeId'],",
    'the_catalogue_opens_with_the_names_reading_top_to_bottom',
    'the catalogue did not open in name order',
    'the header a board clicks would order by the record id it never paints'
);

$leg(
    'ascending-default-flipped',
    $controller,
    "            (string) \$request->input('direction', 'asc'),
            \$sortable,
            'scholarshipName',",
    "            (string) \$request->input('direction', 'desc'),
            \$sortable,
            'scholarshipName',",
    'the_catalogue_opens_with_the_names_reading_top_to_bottom',
    'a catalogue with no direction asked for opens ascending',
    "the helper's descending answer is a queue's need, not a catalogue's"
);

$leg(
    'percent-column-withheld',
    $controller,
    "            'coveragePercent' => ['column' => 'coveragePercent'],\n",
    '',
    'the_coverage_percent_header_orders_by_the_figure_and_not_by_the_record_key',
    'the percent header did not order by percent',
    'a money figure the desk reads and the query can name was left unorderable'
);

$leg(
    'enum-column-withheld',
    $controller,
    "            'coverageType' => ['column' => 'coverageType'],\n",
    '',
    'the_coverage_header_orders_the_enum_by_the_order_this_connection_gives_it',
    'did not order the enum the way this server orders it',
    "the owner's ruling of 2026-10-31 said an ENUM may carry a header; this re-withholds it"
);

$leg(
    'applied-order-never-echoed',
    $controller,
    "            'filters' => \$request->only(['search', 'coverage']) + [
                'sort' => \$sort['column'],
                'direction' => \$sort['direction'],
            ],",
    "            'filters' => \$request->only(['search', 'coverage']),",
    'a_filter_and_an_order_are_both_echoed_so_neither_control_resets',
    'the order the server obeyed never came back',
    "lane 46's NO-RETURN from the sort side: the header resets while the list stays ordered"
);

$leg(
    'fallback-moved-onto-the-record-key',
    $controller,
    "            'scholarshipName',
            'scholarshiptypes.scholarshipTypeId'",
    "            'scholarshipTypeId',
            'scholarshiptypes.scholarshipTypeId'",
    'a_column_the_board_never_lists_falls_back_to_the_catalogue_order',
    'an unlisted key did not fall back to the column the catalogue is ordered by anyway',
    'a forged key answering a different order than the page opens with is a second, invisible order'
);

$leg(
    'header-offered-for-a-key-the-action-refuses',
    $jsx,
    "sortKey: 'coveragePercent'",
    "sortKey: 'percentCoverage'",
    'every_header_the_page_paints_is_a_key_the_action_obeys',
    'the page offers a header for [percentCoverage]',
    'the screen would paint a control the handler ignores, which no backend case can see'
);

$leg(
    'hard-coded-order-returns',
    $controller,
    "        \$types = \$query->paginate(20)->withQueryString();",
    "        \$query->orderByDesc('scholarshipTypeId');
        \$types = \$query->paginate(20)->withQueryString();",
    'the_action_reads_its_own_request_and_no_hard_coded_order_survives',
    'the hard-coded order the mechanism replaced is back',
    'every number can stay equal while the last write wins the order — only the source read sees it'
);

$control('control-default-order-after-plants', 'the_catalogue_opens_with_the_names_reading_top_to_bottom');
$control('control-screen-pairing-after-plants', 'every_header_the_page_paints_is_a_key_the_action_obeys');

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL KILLED' : 'NOT ALL KILLED', $legs, $bad);
exit($bad === 0 ? 0 : 1);
