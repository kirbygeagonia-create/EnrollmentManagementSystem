<?php

/**
 * Falsifier for the clearance checklist catalogue's order (§28.3 G-40, the twenty-sixth wired table).
 *
 * One planted defect per promise the wiring makes, each of which must make the case that OWNS that promise print
 * `1 failed` naming its own assertion. A plant that does not compile also prints `1 failed`, so the hop legs point
 * the Office header at another REAL column and produce a plausible wrong sequence rather than an error.
 *
 * This screen's own shape is the ORDER THE LIST OPENS WITH: its Office header is a hop, and the hop is also the
 * fallback, so the checklist's default changed from the seeder's office numbering to the office name. Two legs
 * plant that pair — the default flipped, and the fallback moved to the other column — because a screen whose
 * opening order silently reverts is invisible to every sequence case that only asks for one.
 *
 * Every needle is a nowdoc whose CLOSING MARKER SITS AT COLUMN ZERO: a flexible heredoc strips the closing
 * marker's own indentation from every body line, so an indented terminator would quietly re-indent the needle and
 * the plant would match nothing. They are also nowdoc rather than double-quoted, so `$search`, `$sort` and `$oq`
 * stay literal text — the trap this repository has recorded six times.
 *
 * Usage: php storage/app/scratch/mutate-g40-clearance-requirement-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

$controller = 'app/Http/Controllers/Admin/ReferenceDataController.php';
$jsx = 'resources/js/Pages/Admin/ReferenceData/ClearanceRequirements.jsx';
$testFile = 'tests/Feature/Admin/ClearanceRequirementSortTest.php';

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
        echo str_pad($name, 44)."SURVIVED — the promise is real but the case stayed green\n";
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

// ---------------------------------------------------------------- needles and plants (closing markers at column zero)
$hopColumn = <<<'PHP'
                'column' => 'officeName',
PHP;
$hopColumnKey = <<<'PHP'
                'column' => 'officeId',
PHP;

$hopBlock = <<<'PHP'
            'office' => [
                'column' => 'officeName',
                'join' => 'offices',
                'foreign' => 'officeId',
                'local' => 'clearancerequirements.officeId',
            ],
PHP;
$hopNoJoin = <<<'PHP'
            'office' => ['column' => 'officeId'],
PHP;

$nameKeyLine = <<<'PHP'
            'requirementName' => ['column' => 'requirementName'],
        ];
PHP;
$nameKeyPlusId = <<<'PHP'
            'requirementName' => ['column' => 'requirementName'],
            'clearanceRequirementId' => ['column' => 'clearanceRequirementId'],
        ];
PHP;

$directionDefault = <<<'PHP'
            (string) $request->input('direction', 'asc'),
            $sortable,
            'office',
PHP;
$DirectionDesc = <<<'PHP'
            (string) $request->input('direction', 'desc'),
            $sortable,
            'office',
PHP;

$fallbackPair = <<<'PHP'
            $sortable,
            'office',
            'clearancerequirements.clearanceRequirementId',
PHP;
$fallbackMoved = <<<'PHP'
            $sortable,
            'requirementName',
            'clearancerequirements.clearanceRequirementId',
PHP;

$echoBlock = <<<'PHP'
            'filters' => $request->only(['search'])
                + ['sort' => $sort['column'], 'direction' => $sort['direction']],
PHP;
$echoGone = <<<'PHP'
            'filters' => $request->only(['search']),
PHP;

$paginateLine = <<<'PHP'
        $page = $requirements->paginate(20)->withQueryString();
PHP;
$hardOrdered = <<<'PHP'
        $page = $requirements->orderBy('officeId')->paginate(20)->withQueryString();
PHP;
$nullFiltered = <<<'PHP'
        $page = $requirements->whereNotNull('requirementName')->paginate(20)->withQueryString();
PHP;

$searchBlock = <<<'PHP'
                    $sub->where('requirementName', 'like', "%{$search}%")
                        ->orWhereHas('office', fn ($oq) => $oq->where('officeName', 'like', "%{$search}%"));
PHP;
$searchNameOnly = <<<'PHP'
                    $sub->where('requirementName', 'like', "%{$search}%");
PHP;

// ---------------------------------------------------------------- controls
$control('control-default-order', 'the_checklist_opens_with_the_office_names_reading_alphabetically');
$hopCase = 'the_office_header_orders_through_the_hop_it_names_and_adds_nothing_to_the_row';
$control('control-hop-order', $hopCase);
$control('control-offered-set', 'only_the_two_columns_the_page_paints_are_offered_as_orders');
$control('control-unworded-row', 'a_requirement_the_school_has_not_worded_yet_stays_on_the_list_whatever_the_order_is');

$leg(
    'office-header-pointed-at-the-own-key',
    $controller,
    $hopColumn,
    $hopColumnKey,
    $hopCase,
    'did not order by the name one hop away',
    "the header keeps its label and orders by the requirement's own foreign key — the seeder numbering this screen had before the mechanism"
);

$leg(
    'office-header-without-a-join',
    $controller,
    $hopBlock,
    $hopNoJoin,
    $hopCase,
    'did not order by the name one hop away',
    'a hop spec read as no hop at all does not fail, it answers a different sequence, and only the declared name order notices'
);

$leg(
    'record-key-offered-as-an-order',
    $controller,
    $nameKeyLine,
    $nameKeyPlusId,
    'only_the_two_columns_the_page_paints_are_offered_as_orders',
    'the record key became an order on a screen that paints no id',
    'an order the desk cannot see is not a control, it is a way to reorder a list nobody asked to sort'
);

$leg(
    'default-flipped-to-descending',
    $controller,
    $directionDefault,
    $DirectionDesc,
    'the_checklist_opens_with_the_office_names_reading_alphabetically',
    'opens ascending',
    "the helper's descending answer is a queue's need; a checklist read top to bottom opens Z-first, which the desk sees on arrival"
);

$leg(
    'fallback-moved-onto-the-other-column',
    $controller,
    $fallbackPair,
    $fallbackMoved,
    'a_column_the_desk_never_lists_falls_back_to_the_checklist_order',
    'did not fall back',
    "the fallback is the screen's own default; moving it silently re-orders the whole list on an unlisted key"
);

$leg(
    'applied-order-never-echoed',
    $controller,
    $echoBlock,
    $echoGone,
    'a_search_and_an_order_are_both_echoed_so_neither_control_resets',
    'never came back',
    "lane 46's NO-RETURN from the sort side: the header resets while the list stays ordered"
);

$leg(
    'hard-coded-order-returns',
    $controller,
    $paginateLine,
    $hardOrdered,
    'the_action_reads_its_own_request_and_no_hard_coded_order_survives',
    'hard-coded seeder-numbering order',
    'the order the mechanism replaced is back beside it, so the last write wins the sequence while every header still reports the column it asked'
);

$leg(
    'unworded-row-filtered-out',
    $controller,
    $paginateLine,
    $nullFiltered,
    'a_requirement_the_school_has_not_worded_yet_stays_on_the_list_whatever_the_order_is',
    'dropped the row it cannot read',
    'a sort that reads a nullable column became a filter on it, deleting the row the desk needs to finish'
);

$leg(
    'office-name-search-stopped-reaching-the-row',
    $controller,
    $searchBlock,
    $searchNameOnly,
    'a_search_that_reaches_the_office_is_not_undone_by_the_order',
    'did not reach the requirement',
    'the search half of the pairing is not decoration: an office typed into the box is how the desk finds its own checklist'
);

$leg(
    'page-offers-a-header-the-handler-ignores',
    $jsx,
    "sortKey: 'requirementName'",
    "sortKey: 'clearanceRequirementId'",
    'every_header_the_page_paints_is_a_key_the_action_obeys',
    'offers a header for',
    'DataTable paints a button only for a listed key, so an invented one is an affordance that silently never appears'
);

$leg(
    'painted-column-loses-its-header',
    $jsx,
    ", sortKey: 'office'",
    '',
    'every_header_the_page_paints_is_a_key_the_action_obeys',
    'a painted column lost its header',
    'the office stays on the screen and the order stays in the handler; only the count of headers notices the loss'
);

$control('control-default-order-after-plants', 'the_checklist_opens_with_the_office_names_reading_alphabetically');
$control('control-hop-order-after-plants', $hopCase);

echo "\n=== verdict ===\n";
echo $bad === 0
    ? 'ALL KILLED: '.($legs - $bad)." leg(s), {$bad} bad\n"
    : 'NOT ALL KILLED: '.$legs.' leg(s), '.$bad." bad\n";
exit($bad === 0 ? 0 : 1);
