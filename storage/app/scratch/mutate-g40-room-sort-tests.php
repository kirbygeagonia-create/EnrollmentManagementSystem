<?php

/**
 * Falsifier for the rooms directory's order (§28.3 G-40, the twenty-third wired table).
 *
 * One planted defect per promise the wiring makes, each of which must make the case that OWNS that promise print
 * `1 failed` naming its own assertion — a plant that does not compile also prints `1 failed` and proves nothing.
 * Controls run before and after, and both touched files are restored by hash in a shutdown hook.
 *
 * This screen's own contribution to the family is the two claims the office directory next to it cannot make: the
 * Capacity header is an INTEGER, so a leg points it at another column and asks the case to catch a sequence that
 * reads as plausible, and a room whose building was never typed must stay on the list its own header arranged —
 * so a leg hides those rows and asks the presence case to notice. The rest of the legs are the pairing shapes:
 * an offered key the table does not hold, a painted header the handler would ignore, a header that vanished, and
 * the order or its echo going silent.
 *
 * A landing check is taken from the plant's shape, not from a single rule: a deletion must leave its needle gone,
 * an insertion necessarily keeps the line it followed.
 *
 * What the fallback leg's FIRST cut found, in the harness rather than in the screen: it moved the fallback from
 * `roomName` to `roomId`, and `ListSort::apply()` answered `Undefined array key` — a 500 at the desk, not a
 * fallback. The helper reads the allowed spec for whichever key it settles on, so a fallback string that names no
 * key in the same handler's own list is a crash rather than an order. No request can trigger it, because the asked
 * key is tested against the list before the fallback is ever used, so this is a maintenance hazard and not a live
 * defect. The leg is therefore re-cut onto `building`, a key the list really holds, so it judges the promise — an
 * unlisted key answers the order the page opens with — instead of the helper's arithmetic. A guard for the hazard
 * belongs in the shared helper with its own falsifier over every wired screen, not here.
 *
 * Usage: php storage/app/scratch/mutate-g40-room-sort-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

$controller = 'app/Http/Controllers/Admin/ReferenceDataController.php';
$jsx = 'resources/js/Pages/Admin/ReferenceData/Rooms.jsx';
$testFile = 'tests/Feature/Admin/RoomDirectorySortTest.php';

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
        echo str_pad($name, 40)."BAD   needle occurs {$hits} time(s), wanted exactly 1 — the leg's age, not a defect\n";
        $bad++;

        return;
    }
    if ($from === $to) {
        echo str_pad($name, 40)."BAD   the plant is a no-op by construction\n";
        $bad++;

        return;
    }
    file_put_contents($file, str_replace($from, $to, $body));
    $after = (string) file_get_contents($file);
    $landed = $after !== $body && str_contains($after, $to) && ($to === '' ? ! str_contains($after, $from) : true);
    if (! $landed) {
        echo str_pad($name, 40)."BAD   the plant did not land on disk\n";
        $bad++;
        $restore();

        return;
    }

    [$code, $out] = $runFile($testFile, $filter);
    $restore();

    if (str_contains($out, 'No tests found')) {
        echo str_pad($name, 40)."BAD   the filter selected no test\n";
        $bad++;
    } elseif ($code === 0 || ! str_contains($out, '1 failed')) {
        echo str_pad($name, 40).'SURVIVED — the promise is real but the case stayed green'."\n";
        $bad++;
    } elseif (! str_contains($out, $needle)) {
        echo str_pad($name, 40)."BAD   failed, but not naming its own assertion (wanted [{$needle}])\n";
        $bad++;
    } elseif (! $assertRestored()) {
        $bad++;
    } else {
        echo str_pad($name, 40)."ok    killed by the case that owns it — {$why}\n";
    }
};

$control = static function (string $name, string $filter) use (&$bad, &$legs, $runFile, $testFile): void {
    $legs++;
    [$code, $out] = $runFile($testFile, $filter);
    if ($code !== 0 || ! str_contains($out, '1 passed')) {
        echo str_pad($name, 40).'BAD   control is not green: '.str_replace("\n", ' / ', mb_substr($out, -220))."\n";
        $bad++;

        return;
    }
    echo str_pad($name, 40)."ok    control green\n";
};

$control('control-capacity-order', 'the_capacity_header_orders_by_the_number_and_not_by_the_digit');
$control('control-offered-set', 'only_the_three_columns_the_query_holds_are_offered_as_orders');

$leg(
    'capacity-header-pointed-at-the-name',
    $controller,
    "            'capacity' => ['column' => 'capacity'],",
    "            'capacity' => ['column' => 'roomName'],",
    'the_capacity_header_orders_by_the_number_and_not_by_the_digit',
    'Capacity was not ordered as a number',
    'a header that orders a different column than it names still prints a tidy list, and only the number sequence says so'
);

$leg(
    'name-column-pointed-at-the-key',
    $controller,
    "            'roomName' => ['column' => 'roomName'],",
    "            'roomName' => ['column' => 'rooms.roomId'],",
    'the_directory_opens_with_the_rooms_reading_top_to_bottom',
    'the directory did not open in name order',
    'the payload still reports the name as the applied column, so the sequence is the only surface that shows the swap'
);

$leg(
    'ascending-default-flipped',
    $controller,
    "            (string) \$request->input('direction', 'asc'),
            \$sortable,
            'roomName',",
    "            (string) \$request->input('direction', 'desc'),
            \$sortable,
            'roomName',",
    'the_directory_opens_with_the_rooms_reading_top_to_bottom',
    'a directory with no direction asked for opens ascending',
    "the helper's descending answer is a queue's need, not a directory's"
);

$leg(
    'unpainted-key-offered-as-an-order',
    $controller,
    "            'capacity' => ['column' => 'capacity'],
        ];",
    "            'capacity' => ['column' => 'capacity'],
            'roomId' => ['column' => 'rooms.roomId'],
        ];",
    'only_the_three_columns_the_query_holds_are_offered_as_orders',
    'an order was offered that the rooms table does not hold',
    'roomId is the tie-break and no column paints it, so a header for it orders a thing the desk cannot see'
);

$leg(
    'blank-building-hidden-from-the-order',
    $controller,
    '        $rooms = $query->paginate(20)->withQueryString();',
    "        \$query->where('building', '!=', '');
        \$rooms = \$query->paginate(20)->withQueryString();",
    'a_room_with_no_building_stays_on_the_list_and_reads_first',
    'lost the room that has no building',
    'an empty building is a value the screen paints as an em dash, not a row the directory may drop'
);

$leg(
    'applied-order-never-echoed',
    $controller,
    "            'rooms' => \$rooms,
            // Both order keys come back with the filters: a control the desk set that the handler obeyed but never
            // echoed resets on the next render while the list stays narrowed — the defect lane 46 owns.
            'filters' => \$request->only(['search']) + [
                'sort' => \$sort['column'],
                'direction' => \$sort['direction'],
            ],",
    "            'rooms' => \$rooms,
            'filters' => \$request->only(['search']),",
    'a_filter_and_an_order_are_both_echoed_so_neither_control_resets',
    'the order the server obeyed never came back',
    "lane 46's NO-RETURN from the sort side: the header resets while the list stays ordered"
);

$leg(
    'fallback-moved-onto-another-real-column',
    $controller,
    "            'roomName',
            'rooms.roomId'",
    "            'building',
            'rooms.roomId'",
    'a_column_the_desk_never_lists_falls_back_to_the_directory_order',
    'an unlisted key did not fall back to the column the directory is ordered by anyway',
    'a forged key answering a different order than the page opens with is a second, invisible order'
);

$leg(
    'hard-coded-order-returns',
    $controller,
    '        $rooms = $query->paginate(20)->withQueryString();',
    "        \$query->orderByDesc('roomId');
        \$rooms = \$query->paginate(20)->withQueryString();",
    'the_action_reads_its_own_request_and_no_hard_coded_order_survives',
    'the hard-coded order the mechanism replaced is back',
    'every number can stay equal while the last write wins the order — only the source read sees it'
);

$leg(
    'page-offers-a-header-the-handler-ignores',
    $jsx,
    "sortKey: 'capacity'",
    "sortKey: 'capacityUsed'",
    'every_header_the_page_paints_is_a_key_the_action_obeys',
    'the page offers a header for [capacityUsed]',
    'DataTable paints a button only for a listed key, so an invented one is an affordance that silently never appears'
);

$leg(
    'painted-column-loses-its-header',
    $jsx,
    "{ key: 'building', label: 'Building', sortKey: 'building', render:",
    "{ key: 'building', label: 'Building', render:",
    'every_header_the_page_paints_is_a_key_the_action_obeys',
    'a painted column lost its header',
    'the column stays on the screen and the order stays in the handler; only the count of headers notices the loss'
);

$control('control-capacity-order-after-plants', 'the_capacity_header_orders_by_the_number_and_not_by_the_digit');
$control('control-offered-set-after-plants', 'only_the_three_columns_the_query_holds_are_offered_as_orders');

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL KILLED' : 'NOT ALL KILLED', $legs, $bad);
exit($bad === 0 ? 0 : 1);
