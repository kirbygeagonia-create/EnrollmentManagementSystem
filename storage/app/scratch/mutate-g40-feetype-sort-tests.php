<?php

/**
 * Falsifier for the fee-sheet catalogue's order (§28.3 G-40, the twentieth wired table).
 *
 * One planted defect per promise the wiring makes, each of which must make the case that OWNS that promise print
 * `1 failed` naming its own assertion — a plant that does not compile also prints `1 failed` and proves nothing.
 * Controls run before and after, and the controller is restored by hash in a shutdown hook, because a half-restored
 * file makes every later verdict a lie about a file nobody meant to change.
 *
 * Every needle here is anchored on a line unique to the feeTypes block, because `ReferenceDataController` holds a
 * dozen actions that share the same shape: a needle like "the direction input" matches all of them and the plant
 * would land in a sibling action while the named case stayed green — the T06 lesson, which is a needle that is
 * unique in the FILE but not in the METHOD it targets.
 *
 * Usage: php storage/app/scratch/mutate-g40-feetype-sort-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

$controller = 'app/Http/Controllers/Admin/ReferenceDataController.php';
$testFile = 'tests/Feature/Admin/FeeTypeSortTest.php';

$targets = [$controller];
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

$leg = static function (string $name, string $from, string $to, string $filter, string $needle, string $why) use (&$bad, &$legs, $runFile, $restore, $assertRestored, $controller, $testFile): void {
    $legs++;
    $body = (string) file_get_contents($controller);
    $hits = substr_count($body, $from);
    if ($hits !== 1) {
        echo str_pad($name, 30)."BAD   needle occurs {$hits} time(s), wanted exactly 1 — the leg's age, not a defect\n";
        $bad++;

        return;
    }
    file_put_contents($controller, str_replace($from, $to, $body));
    if (! str_contains((string) file_get_contents($controller), $to)) {
        echo str_pad($name, 30)."BAD   the plant did not land on disk\n";
        $bad++;
        $restore();

        return;
    }

    [$code, $out] = $runFile($testFile, $filter);
    $restore();

    if (str_contains($out, 'No tests found')) {
        echo str_pad($name, 30)."BAD   the filter selected no test\n";
        $bad++;
    } elseif ($code === 0 || ! str_contains($out, '1 failed')) {
        echo str_pad($name, 30).'SURVIVED — the promise is real but the case stayed green'."\n";
        $bad++;
    } elseif (! str_contains($out, $needle)) {
        echo str_pad($name, 30)."BAD   failed, but not naming its own assertion (wanted [{$needle}])\n";
        $bad++;
    } elseif (! $assertRestored()) {
        $bad++;
    } else {
        echo str_pad($name, 30)."ok    killed by the case that owns it — {$why}\n";
    }
};

$control = static function (string $name, string $filter) use (&$bad, &$legs, $runFile, $testFile): void {
    $legs++;
    [$code, $out] = $runFile($testFile, $filter);
    if ($code !== 0 || ! str_contains($out, '1 passed')) {
        echo str_pad($name, 30).'BAD   control is not green: '.str_replace("\n", ' / ', mb_substr($out, -220))."\n";
        $bad++;

        return;
    }
    echo str_pad($name, 30)."ok    control green\n";
};

$control('control-default-order', 'the_catalogue_opens_with_the_names_reading_top_to_bottom');
$control('control-echo', 'a_filter_and_an_order_are_both_echoed_so_neither_control_resets');

$leg(
    'name-column-pointed-at-the-key',
    "            'feeName' => ['column' => 'feeName'],",
    "            'feeName' => ['column' => 'feetypes.feeTypeId'],",
    'the_catalogue_opens_with_the_names_reading_top_to_bottom',
    'the catalogue did not open in name order',
    'the header a board clicks would order by the record id it never paints'
);

$leg(
    'ascending-default-flipped',
    "            (string) \$request->input('direction', 'asc'),
            \$sortable,
            'feeName',",
    "            (string) \$request->input('direction', 'desc'),
            \$sortable,
            'feeName',",
    'the_catalogue_opens_with_the_names_reading_top_to_bottom',
    'a catalogue with no direction asked for opens ascending',
    'the helper\'s descending answer is a queue\'s need, not a catalogue\'s'
);

$leg(
    'amount-column-withheld',
    "            'defaultAmount' => ['column' => 'defaultAmount'],\n",
    '',
    'the_amount_header_orders_by_the_amount_and_not_by_the_record_key',
    'the amount header did not order by amount',
    'a column the desk reads and the query can name was left unorderable'
);

$leg(
    'enum-column-withheld',
    "            'unitBasis' => ['column' => 'unitBasis'],\n",
    '',
    'the_unit_basis_header_orders_the_enum_by_the_order_this_connection_gives_it',
    'did not order the enum the way this server orders it',
    "the owner's ruling of 2026-10-31 said an ENUM may carry a header; this re-withholds it"
);

$leg(
    'applied-order-never-echoed',
    "            'filters' => \$request->only(['search', 'unitBasis']) + [
                'sort' => \$sort['column'],
                'direction' => \$sort['direction'],
            ],",
    "            'filters' => \$request->only(['search', 'unitBasis']),",
    'a_filter_and_an_order_are_both_echoed_so_neither_control_resets',
    'the order the server obeyed never came back',
    "lane 46's NO-RETURN from the sort side: the header resets while the list stays ordered"
);

$leg(
    'hard-coded-order-returns',
    "        \$feeTypes = \$query->paginate(20)->withQueryString();",
    "        \$query->orderByDesc('feeTypeId');
        \$feeTypes = \$query->paginate(20)->withQueryString();",
    'the_action_reads_its_own_request_and_no_hard_coded_order_survives',
    'the hard-coded order the mechanism replaced is back',
    'every number can stay equal while the last write wins the order — only the source read sees it'
);

$control('control-default-order-after-plants', 'the_catalogue_opens_with_the_names_reading_top_to_bottom');
$control('control-echo-after-plants', 'a_filter_and_an_order_are_both_echoed_so_neither_control_resets');

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL KILLED' : 'NOT ALL KILLED', $legs, $bad);
exit($bad === 0 ? 0 : 1);
