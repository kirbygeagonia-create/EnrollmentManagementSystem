<?php

/**
 * Falsifier for the curriculum catalogue's order (§28.3 G-40, the twenty-seventh wired table).
 *
 * This screen's own shape is the LEFT hop: `curriculums.majorId` is nullable AND null on all 16 live rows, so the
 * defect is not a wrong sequence but an empty catalogue. Two legs plant hop-level defects (INNER where LEFT is
 * proven, and a hop spec read as no hop) because a column-only harness cannot see either.
 *
 * Usage: php storage/app/scratch/mutate-g40-curriculum-sort-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

$controller = 'app/Http/Controllers/Admin/ReferenceDataController.php';
$jsx = 'resources/js/Pages/Admin/ReferenceData/Curriculums.jsx';
$testFile = 'tests/Feature/Admin/CurriculumCatalogueSortTest.php';

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

$run = static function (string $filter) use ($root, $php, $testFile): array {
    $out = [];
    exec('cd '.$root.' && "'.$php.'" artisan test '.$testFile.' --filter '.$filter.' 2>&1', $out, $code);

    return [(int) $code, preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $out))];
};

$restored = static function () use ($targets, $hashes): bool {
    foreach ($targets as $f) {
        if (md5((string) file_get_contents($f)) !== $hashes[$f]) {
            echo '  TREE NOT RESTORED after '.basename($f)."\n";

            return false;
        }
    }

    return true;
};

$leg = static function (string $name, string $file, string $from, string $to, string $filter, string $needle, string $why) use (
    &$bad, &$legs, $run, $restore, $restored
): void {
    $legs++;
    $body = (string) file_get_contents($file);
    $hits = substr_count($body, $from);
    if ($hits !== 1) {
        echo str_pad($name, 42)."BAD   needle occurs {$hits} time(s), wanted 1 — the leg's age, not a defect\n";
        $bad++;

        return;
    }
    $mutated = str_replace($from, $to, $body);
    if ($mutated === $body) {
        echo str_pad($name, 42)."BAD   the plant is a no-op\n";
        $bad++;

        return;
    }
    if (file_put_contents($file, $mutated) === false || ! str_contains((string) file_get_contents($file), $to)) {
        echo str_pad($name, 42)."BAD   the plant did not land on disk\n";
        $bad++;
        $restore();

        return;
    }
    [$code, $out] = $run($filter);
    $restore();

    if (str_contains($out, 'No tests found')) {
        echo str_pad($name, 42)."BAD   the filter selected no test\n";
        $bad++;
    } elseif ($code === 0 || ! str_contains($out, '1 failed')) {
        echo str_pad($name, 42)."SURVIVED — the promise is real but the case stayed green\n";
        $bad++;
    } elseif (! str_contains($out, $needle)) {
        echo str_pad($name, 42)."BAD   failed, but not naming its own assertion (wanted [{$needle}])\n";
        $bad++;
    } elseif (! $restored()) {
        $bad++;
    } else {
        echo str_pad($name, 42)."ok    killed by the case that owns it — {$why}\n";
    }
};

$control = static function (string $name, string $filter) use (&$bad, &$legs, $run): void {
    $legs++;
    [$code, $out] = $run($filter);
    if ($code !== 0 || ! str_contains($out, '1 passed')) {
        echo str_pad($name, 42).'BAD   control not green: '.str_replace("\n", ' / ', mb_substr($out, -200))."\n";
        $bad++;

        return;
    }
    echo str_pad($name, 42)."ok    control green\n";
};

$majorBlock = <<<'PHP'
            'major' => [
                'column' => 'majorName',
                'join' => 'majors',
                'foreign' => 'majorId',
                'local' => 'curriculums.majorId',
                'type' => 'left',
            ],
PHP;

$control('control-default-order', 'the_catalogue_opens_with_the_titles_reading_top_to_bottom');
$control('control-major-left-hop', 'the_major_header_keeps_every_curriculum_because_the_key_is_null_on_all_of_them');
$control('control-offered-set', 'only_the_five_columns_the_page_paints_are_offered_as_orders');

$leg(
    'major-hop-quietly-widened-to-inner',
    $controller,
    $majorBlock,
    str_replace("                'type' => 'left',\n", '', $majorBlock),
    'the_major_header_keeps_every_curriculum_because_the_key_is_null_on_all_of_them',
    'dropped the curriculums that have no major',
    'an INNER hop here is not a wrong sequence, it is an empty catalogue — the only shape that can show it is a row that survives the header'
);

// The Course hop block is byte-identical in blocks(), and the filters echo block is identical in
// clearanceRequirements(), so both needles are widened to context only THIS action holds — the same
// "needle occurs 2 times" refusal that stopped the first cut from planting in a sibling method.
$courseBlock = <<<'CB'
            'course' => [
                'column' => 'courseName',
                'join' => 'courses',
                'foreign' => 'courseId',
                'local' => 'curriculums.courseId',
            ],
CB;
$courseBlockKeyed = <<<'CB2'
            'course' => [
                'column' => 'courseId',
                'join' => 'courses',
                'foreign' => 'courseId',
                'local' => 'curriculums.courseId',
            ],
CB2;

$leg(
    'course-header-pointed-at-its-own-key',
    $controller,
    $courseBlock,
    $courseBlockKeyed,
    'the_course_header_orders_through_an_inner_hop_that_cannot_drop_a_row',
    'did not order by the name one hop away',
    'the header keeps its label and orders by the foreign key, which is a tidy list of the wrong thing'
);

$leg(
    'record-key-offered-as-an-order',
    $controller,
    "            'effectiveYear' => ['column' => 'effectiveYear'],",
    "            'effectiveYear' => ['column' => 'effectiveYear'],\n            'majorId' => ['column' => 'majorId'],",
    'only_the_five_columns_the_page_paints_are_offered_as_orders',
    'the raw foreign key was offered beside the Major name',
    'a header over a key nobody reads is a way to reorder a list the desk never asked to sort'
);

$leg(
    'default-flipped-to-descending',
    $controller,
    "            (string) \$request->input('direction', 'asc'),\n            \$sortable,\n            'curriculumName',",
    "            (string) \$request->input('direction', 'desc'),\n            \$sortable,\n            'curriculumName',",
    'the_catalogue_opens_with_the_titles_reading_top_to_bottom',
    'opens ascending',
    'a catalogue that opens on its last title is a queue answer, and the desk sees it on arrival'
);

$leg(
    'fallback-moved-onto-the-id',
    $controller,
    "            \$sortable,\n            'curriculumName',\n            'curriculums.curriculumId',",
    "            \$sortable,\n            'curriculumId',\n            'curriculums.curriculumId',",
    'a_column_the_desk_never_lists_falls_back_to_the_catalogue_order',
    'did not fall back',
    'the fallback is the screen\'s own default; moving it re-orders everything on an unlisted key'
);

$echoBlock = <<<'EB'
            'majors' => $majors,
            'filters' => $request->only(['search'])
                + ['sort' => $sort['column'], 'direction' => $sort['direction']],
EB;
$echoGone = <<<'EB2'
            'majors' => $majors,
            'filters' => $request->only(['search']),
EB2;

$leg(
    'applied-order-never-echoed',
    $controller,
    $echoBlock,
    $echoGone,
    'a_search_and_an_order_are_both_echoed_so_neither_control_resets',
    'never came back',
    'lane 46\'s NO-RETURN from the sort side: the header resets while the list stays ordered'
);

$leg(
    'hard-coded-order-returns',
    $controller,
    '        $page = $curriculums->paginate(20)->withQueryString();',
    '        $page = $curriculums->orderByDesc(\'curriculumId\')->paginate(20)->withQueryString();',
    'the_action_reads_its_own_request_and_no_hard_coded_order_survives',
    'hard-coded newest-first order',
    'the order the mechanism replaced is back beside it, so the last write wins while every header still reports its column'
);

$leg(
    'page-offers-a-header-the-handler-ignores',
    $jsx,
    "sortKey: 'curriculumName'",
    "sortKey: 'created_at'",
    'every_header_the_page_paints_is_a_key_the_action_obeys',
    'offers a header for',
    'DataTable paints a button only for a listed key, so an invented one is an affordance that silently never appears'
);

$leg(
    'painted-column-loses-its-header',
    $jsx,
    ", sortKey: 'major'",
    '',
    'every_header_the_page_paints_is_a_key_the_action_obeys',
    'a painted column lost its header',
    'the Major column stays on the screen and the hop stays in the handler; only the count of headers notices the loss'
);

$control('control-major-left-hop-after-plants', 'the_major_header_keeps_every_curriculum_because_the_key_is_null_on_all_of_them');
$control('control-default-order-after-plants', 'the_catalogue_opens_with_the_titles_reading_top_to_bottom');

echo "\n=== verdict ===\n";
echo $bad === 0
    ? "ALL KILLED: {$legs} leg(s), 0 bad\n"
    : 'NOT ALL KILLED: '.$legs.' leg(s), '.$bad." bad\n";
exit($bad === 0 ? 0 : 1);
