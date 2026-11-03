<?php

/**
 * Falsifier for the academic term list's order (§28.3 G-40, the twenty-eighth wired table).
 *
 * Two shapes are new to this screen and each has its own leg. The Semester header is an ENUM, so the sequence
 * belongs to the connection rather than to this file; the plant points the header at a column every fixture row
 * shares, which is the tidy-list-of-the-wrong-thing defect. The Status cell is DERIVED from the row dates, so a
 * header there promises an order the query cannot answer — that is planted on both sides (the handler offers the
 * key, the page paints it) because the two runtimes hold the two halves of the same claim.
 *
 * Usage: php storage/app/scratch/mutate-g40-term-sort-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

$controller = 'app/Http/Controllers/Admin/ReferenceDataController.php';
$jsx = 'resources/js/Pages/Admin/ReferenceData/Terms.jsx';
$testFile = 'tests/Feature/Admin/TermListSortTest.php';

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
        if (file_put_contents($f, $body) === false) {
            fwrite(STDERR, "REFUSED: the restore could not write {$f}.\n");
            exit(1);
        }
    }
};
register_shutdown_function($restore);

$bad = 0;
$legs = 0;

$runFile = static function (string $filter) use ($root, $php, $testFile): array {
    $out = [];
    exec('cd '.$root.' && "'.$php.'" artisan test '.$testFile.' --filter '.$filter.' 2>&1', $out, $code);

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

/** @param list<array{0:string,1:string}> $plants */
$leg2 = static function (string $name, string $file, array $plants, string $filter, string $needle, string $why) use (
    &$bad, &$legs, $runFile, $restore, $assertRestored
): void {
    $legs++;
    $body = (string) file_get_contents($file);
    foreach ($plants as [$from, $to]) {
        $hits = substr_count($body, $from);
        if ($hits !== 1) {
            echo str_pad($name, 44)."BAD   needle occurs {$hits} time(s), wanted 1 — the leg's age, not a defect\n";
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
    if ($body === (string) file_get_contents($file)) {
        echo str_pad($name, 44)."BAD   the plant changed no bytes\n";
        $bad++;

        return;
    }
    if (file_put_contents($file, $body) === false || substr_count((string) file_get_contents($file), $plants[0][1]) !== 1) {
        echo str_pad($name, 44)."BAD   the plant did not land on disk\n";
        $bad++;
        $restore();

        return;
    }

    [$code, $out] = $runFile($filter);
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

$control = static function (string $name, string $filter) use (&$bad, &$legs, $runFile): void {
    $legs++;
    [$code, $out] = $runFile($filter);
    if ($code !== 0 || ! str_contains($out, '1 passed')) {
        echo str_pad($name, 44).'BAD   control is not green: '.str_replace("\n", ' / ', mb_substr($out, -220))."\n";
        $bad++;

        return;
    }
    echo str_pad($name, 44)."ok    control green\n";
};

// --------------------------------------------------------------------------- the handler's own blocks
$yearBlock = <<<'YB'
            'academicYear' => [
                'column' => 'yearLabel',
                'join' => 'academicyears',
                'foreign' => 'academicYearId',
                'local' => 'academicterms.academicYearId',
            ],
YB;

$semesterSpec = "            'semester' => ['column' => 'semester'],";

$applyCall = <<<'AC'
        $sort = ListSort::apply(
            $terms,
            (string) $request->input('sort', ''),
            (string) $request->input('direction', 'asc'),
            $sortable,
            'academicYear',
            'academicterms.termId',
        );
AC;

$echoBlock = <<<'EB'
            'filters' => $request->only(['search', 'semester', 'status'])
                + ['sort' => $sort['column'], 'direction' => $sort['direction']],
EB;

$endDateSpec = "            'endDate' => ['column' => 'endDate'],";

$paginateLine = '        $page = $terms->paginate(20)->withQueryString();';

$control('control-default-order', 'the_list_opens_with_the_school_years_reading_oldest_first');
$control('control-enum-order', 'the_semester_header_answers_the_order_this_connection_holds_for_the_enum');
$control('control-derived-status-withheld', 'the_derived_status_column_is_not_offered_and_the_four_real_ones_are');
$control('control-qualified-day-filter', 'the_day_filter_names_its_own_table_so_the_join_a_header_adds_cannot_answer_it');

// The defect this screen produced on the school's server: the Active/Inactive filter asked for `startDate` and
// `endDate` by bare name, and the School Year hop joins `academicyears`, which holds both columns too. Live `ems`
// answered 500 for `status=active` beside a fallback order while every suite case stayed green, because the suite
// never put the tab and the header on one request.
$dayFilter = <<<'DF'
            ->when($request->status === 'active', fn ($q) => $q
                ->where('academicterms.startDate', '<=', $today)
                ->where('academicterms.endDate', '>=', $today))
DF;
$dayFilterUnqualified = <<<'DF2'
            ->when($request->status === 'active', fn ($q) => $q
                ->where('startDate', '<=', $today)
                ->where('endDate', '>=', $today))
DF2;

$leg(
    'day-filter-lost-its-qualifier-beside-the-hop',
    $controller,
    $dayFilter,
    $dayFilterUnqualified,
    'the_day_filter_names_its_own_table_so_the_join_a_header_adds_cannot_answer_it',
    'ambiguous',
    'a hop that joins a table holding the same column names turns a bare filter into a refusal on BOTH drivers (MySQL 1052, SQLite "ambiguous column name"), so the case fails before it reads a sequence'
);

$leg(
    'default-flipped-to-newest-year',
    $controller,
    "            (string) \$request->input('direction', 'asc'),\n            \$sortable,\n            'academicYear',",
    "            (string) \$request->input('direction', 'desc'),\n            \$sortable,\n            'academicYear',",
    'the_list_opens_with_the_school_years_reading_oldest_first',
    'opens ascending',
    'a catalogue of school years opens oldest-first; the desk meets the wrong end on arrival'
);

$leg(
    'year-header-pointed-at-its-own-key',
    $controller,
    $yearBlock,
    str_replace("'column' => 'yearLabel'", "'column' => 'academicterms.academicYearId'", $yearBlock),
    'the_school_year_header_orders_through_the_hop_and_adds_nothing_to_the_row',
    'did not order by the label one hop away',
    'the header keeps its label and orders the foreign key, which is a tidy list of the wrong thing'
);

$leg(
    'enum-header-pointed-at-a-shared-key',
    $controller,
    $semesterSpec,
    "            'semester' => ['column' => 'academicYearId'],",
    'the_semester_header_answers_the_order_this_connection_holds_for_the_enum',
    'did not answer the order this connection holds for the column',
    'the enum order is the school\'s declaration; pointing the header elsewhere prints a sequence nobody wrote'
);

$leg(
    'derived-status-offered-by-the-handler',
    $controller,
    $endDateSpec,
    $endDateSpec."\n            'status' => ['column' => 'startDate'],",
    'the_derived_status_column_is_not_offered_and_the_four_real_ones_are',
    'a header was offered for a value the screen computes',
    'the cell is computed from the row dates against the school\'s day, so no column answers that header'
);

$leg(
    'page-paints-a-header-the-handler-refuses',
    $jsx,
    "sortKey: 'startDate'",
    "sortKey: 'created_at'",
    'every_header_the_page_paints_is_a_key_the_action_obeys',
    'the page offers a header for',
    'DataTable draws a button only for a listed key, so an invented one is an affordance that silently never appears'
);

$leg(
    'semester-choices-retyped-in-the-page',
    $jsx,
    "        ...semesters.map((s) => ({ value: s.value, label: `\${s.value} Semester` })),\n    ], [semesters]);",
    "        { value: '1st', label: '1st Semester' },\n        { value: '2nd', label: '2nd Semester' },\n        { value: 'Summer', label: 'Summer' },\n    ], []);",
    'the_semester_filter_and_the_semester_choices_read_one_list_from_the_server',
    'the choices are not derived from the server list',
    'one enum written twice drifts silently: a fourth semester would reach the create form and never the filter'
);

$leg(
    'applied-order-never-echoed',
    $controller,
    $echoBlock,
    "            'filters' => \$request->only(['search', 'semester', 'status']),",
    'every_control_the_desk_set_comes_back_so_none_of_them_resets',
    'never came back',
    "lane 46's NO-RETURN from the sort side: the header resets while the list stays ordered"
);

$leg(
    'fallback-moved-off-the-school-year',
    $controller,
    $applyCall,
    str_replace("            'academicYear',\n            'academicterms.termId',", "            'semester',\n            'academicterms.termId',", $applyCall),
    'a_column_the_desk_never_lists_falls_back_to_the_school_year_order',
    'did not fall back',
    'the fallback is the screen\'s own default; moving it re-orders everything the moment an unlisted key arrives'
);

$leg(
    'hard-coded-order-returns',
    $controller,
    $paginateLine,
    "        \$page = \$terms->orderByDesc('termId')->paginate(20)->withQueryString();",
    'the_action_reads_its_own_request_and_no_hard_coded_order_survives',
    'hard-coded newest-first order',
    'the order the mechanism replaced sits beside it, so the last write wins while every header still reports its column'
);

$control('control-default-order-after-plants', 'the_list_opens_with_the_school_years_reading_oldest_first');
$control('control-hop-and-pin-after-plants', 'the_school_year_header_orders_through_the_hop_and_adds_nothing_to_the_row');
$control('control-derived-status-after-plants', 'the_derived_status_column_is_not_offered_and_the_four_real_ones_are');
$control('control-qualified-day-filter-after-plants', 'the_day_filter_names_its_own_table_so_the_join_a_header_adds_cannot_answer_it');

echo "\n=== verdict ===\n";
echo $bad === 0
    ? "ALL KILLED: {$legs} leg(s), 0 bad\n"
    : 'NOT ALL KILLED: '.$legs.' leg(s), '.$bad." bad\n";
exit($bad === 0 ? 0 : 1);
