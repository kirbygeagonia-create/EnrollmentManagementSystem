<?php

/**
 * Falsifier for the block roster's order (§28.3 G-40, the twenty-fourth wired table).
 *
 * One planted defect per promise the wiring makes, each of which must make the case that OWNS that promise print
 * `1 failed` naming its own assertion — a plant that does not compile also prints `1 failed` and proves nothing, so
 * the wrong-relation leg is written to produce a plausible sequence rather than a server error.
 *
 * This screen's own contribution to the family is that both halves of the offer claim are planted: the Course header
 * is an order the query must reach through a hop, and the Term header is a compound label that must NOT be offered.
 * Seven earlier harnesses can break a column mapping; only a leg that hands the roster a `sortKey: 'term'` proves the
 * withholding is read rather than decorative. Controls run before and after, and both touched files are restored by
 * hash in a shutdown hook.
 *
 * Usage: php storage/app/scratch/mutate-g40-block-roster-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

$controller = 'app/Http/Controllers/Admin/ReferenceDataController.php';
$jsx = 'resources/js/Pages/Admin/ReferenceData/Blocks.jsx';
$testFile = 'tests/Feature/Admin/BlockRosterSortTest.php';

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
        echo str_pad($name, 42)."BAD   needle occurs {$hits} time(s), wanted exactly 1 — the leg's age, not a defect\n";
        $bad++;

        return;
    }
    if ($from === $to) {
        echo str_pad($name, 42)."BAD   the plant is a no-op by construction\n";
        $bad++;

        return;
    }
    file_put_contents($file, str_replace($from, $to, $body));
    $after = (string) file_get_contents($file);
    $landed = $after !== $body && str_contains($after, $to) && ($to === '' ? ! str_contains($after, $from) : true);
    if (! $landed) {
        echo str_pad($name, 42)."BAD   the plant did not land on disk\n";
        $bad++;
        $restore();

        return;
    }

    [$code, $out] = $runFile($testFile, $filter);
    $restore();

    if (str_contains($out, 'No tests found')) {
        echo str_pad($name, 42)."BAD   the filter selected no test\n";
        $bad++;
    } elseif ($code === 0 || ! str_contains($out, '1 failed')) {
        echo str_pad($name, 42).'SURVIVED — the promise is real but the case stayed green'."\n";
        $bad++;
    } elseif (! str_contains($out, $needle)) {
        echo str_pad($name, 42)."BAD   failed, but not naming its own assertion (wanted [{$needle}])\n";
        $bad++;
    } elseif (! $assertRestored()) {
        $bad++;
    } else {
        echo str_pad($name, 42)."ok    killed by the case that owns it — {$why}\n";
    }
};

$control = static function (string $name, string $filter) use (&$bad, &$legs, $runFile, $testFile): void {
    $legs++;
    [$code, $out] = $runFile($testFile, $filter);
    if ($code !== 0 || ! str_contains($out, '1 passed')) {
        echo str_pad($name, 42).'BAD   control is not green: '.str_replace("\n", ' / ', mb_substr($out, -220))."\n";
        $bad++;

        return;
    }
    echo str_pad($name, 42)."ok    control green\n";
};

$hop = "            'course' => [
                'column' => 'courseName',
                'join' => 'courses',
                'foreign' => 'courseId',
                'local' => 'blocks.courseId',
            ],";
$offered = "            'maxStudents' => ['column' => 'maxStudents'],
        ];";

$control('control-course-hop', 'the_course_header_orders_through_the_hop_it_names_and_adds_nothing_to_the_row');
$control('control-offered-set', 'only_the_columns_the_query_holds_are_offered_and_the_compound_term_label_is_not');

$leg(
    'course-header-wired-at-the-term-relation',
    $controller,
    $hop,
    "            'course' => [
                'column' => 'semester',
                'join' => 'academicterms',
                'foreign' => 'termId',
                'local' => 'blocks.termId',
            ],",
    'the_course_header_orders_through_the_hop_it_names_and_adds_nothing_to_the_row',
    'did not order by the name one hop away',
    'a header that joins the wrong relation still renders a tidy list, and only the course sequence says so'
);

$leg(
    'hop-added-without-a-join',
    $controller,
    $hop,
    "            'course' => ['column' => 'courseName'],",
    'the_course_header_orders_through_the_hop_it_names_and_adds_nothing_to_the_row',
    'did not order by the name one hop away',
    'a column two tables away cannot be reached by naming it — the join is the other half of the promise'
);

$leg(
    'compound-term-label-offered-as-an-order',
    $controller,
    $offered,
    "            'maxStudents' => ['column' => 'maxStudents'],
            'term' => ['column' => 'blockName'],
        ];",
    'only_the_columns_the_query_holds_are_offered_and_the_compound_term_label_is_not',
    'a header was offered for a compound label with no column behind it',
    'the cell prints a year label joined to a semester, so no column is what the desk reads or orders'
);

$leg(
    'page-offers-a-header-for-the-term',
    $jsx,
    "            key: 'term',
            label: 'Term',",
    "            key: 'term',
            label: 'Term',
            sortKey: 'term',",
    'every_header_the_page_paints_is_a_key_the_action_obeys',
    'the page offers a header for [term]',
    'the handler refuses that order on purpose, so the screen must not paint a button nothing answers'
);

$leg(
    'painted-column-loses-its-header',
    $jsx,
    "{ key: 'maxStudents', label: 'Max Students', sortKey: 'maxStudents', className:",
    "{ key: 'maxStudents', label: 'Max Students', className:",
    'every_header_the_page_paints_is_a_key_the_action_obeys',
    'a painted column lost its header',
    'the column stays on the screen and the order stays in the handler; only the count of headers notices the loss'
);

$leg(
    'max-students-pointed-at-the-name',
    $controller,
    "            'maxStudents' => ['column' => 'maxStudents'],",
    "            'maxStudents' => ['column' => 'blockName'],",
    'the_max_students_header_orders_by_the_number_and_not_by_the_digit',
    'Max Students was not ordered as a number',
    'a capacity column read as a digit orders 100 before 9, which a desk notices the moment it sorts the roster'
);

$leg(
    'name-column-pointed-at-the-key',
    $controller,
    "            'blockName' => ['column' => 'blockName'],",
    "            'blockName' => ['column' => 'blocks.blockId'],",
    'the_roster_opens_with_the_block_names_reading_top_to_bottom',
    'the roster did not open in block-name order',
    'the payload still reports the name as the applied column, so the sequence is the only surface that shows the swap'
);

$leg(
    'ascending-default-flipped',
    $controller,
    "            (string) \$request->input('direction', 'asc'),
            \$sortable,
            'blockName',",
    "            (string) \$request->input('direction', 'desc'),
            \$sortable,
            'blockName',",
    'the_roster_opens_with_the_block_names_reading_top_to_bottom',
    'a roster with no direction asked for opens ascending',
    "the helper's descending answer is a queue's need, not a roster's"
);

$leg(
    'applied-order-never-echoed',
    $controller,
    "            'filters' => \$request->only(['search']) + [
                'sort' => \$sort['column'],
                'direction' => \$sort['direction'],
            ],
            'sort' => ListSort::present(\$sort, \$sortable),
        ]);
    }

    public function storeBlock",
    "            'filters' => \$request->only(['search']),
            'sort' => ListSort::present(\$sort, \$sortable),
        ]);
    }

    public function storeBlock",
    'a_filter_and_an_order_are_both_echoed_so_neither_control_resets',
    'the order the server obeyed never came back',
    "lane 46's NO-RETURN from the sort side: the header resets while the list stays ordered"
);

$leg(
    'fallback-moved-onto-another-real-column',
    $controller,
    "            'blockName',
            'blocks.blockId'",
    "            'yearLevel',
            'blocks.blockId'",
    'a_column_the_desk_never_lists_falls_back_to_the_roster_order',
    'an unlisted key did not fall back to the column the roster is ordered by anyway',
    'a forged key answering a different order than the page opens with is a second, invisible order'
);

$leg(
    'hard-coded-order-returns',
    $controller,
    '        $blocks = $query->paginate(20)->withQueryString();',
    "        \$query->orderByDesc('blockId');
        \$blocks = \$query->paginate(20)->withQueryString();",
    'the_action_reads_its_own_request_and_no_hard_coded_order_survives',
    'the hard-coded order the mechanism replaced is back',
    'every number can stay equal while the last write wins the order — only the source read sees it'
);

$control('control-course-hop-after-plants', 'the_course_header_orders_through_the_hop_it_names_and_adds_nothing_to_the_row');
$control('control-offered-set-after-plants', 'only_the_columns_the_query_holds_are_offered_and_the_compound_term_label_is_not');

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL KILLED' : 'NOT ALL KILLED', $legs, $bad);
exit($bad === 0 ? 0 : 1);
