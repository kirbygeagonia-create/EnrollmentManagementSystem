<?php

/**
 * Falsifier for the nineteenth sortable table (§28.3 G-40, the workflow monitor).
 *
 * One planted defect per promise the wiring makes, each of which must make the case that OWNS that promise print
 * `1 failed` naming its own assertion — because a plant that does not compile also prints `1 failed` and proves
 * nothing. A green control runs before and after, and every file is restored by hash in a shutdown hook, since a
 * half-restored controller makes every later verdict a lie about a file nobody meant to change.
 *
 * Rules this harness is built to, each learned elsewhere in this repository:
 *  - `--filter` takes the METHOD name, not the sentence Pest prints, and `No tests found` is BAD, never a kill;
 *  - a needle that matches nothing is reported as the harness's own age, not as a surviving defect;
 *  - a leg that cannot fail is withheld and the reason is written here rather than folded into the tally.
 *
 * WITHHELD LEG, and why: `tiles-cloned-after-the-order`. Moving the tile clones below `ListSort::apply()` is a real
 * defect the family has met (lane 108's PINNED-SELECT and the 1055 at the desk), but this page's fixture holds four
 * records against a 25-row page, so `paginate()` would put `LIMIT 25 OFFSET 0` on a builder whose count is still
 * four — the tile cannot move. A leg that measures page one is a leg that cannot fail, and `lane47`'s rule is that
 * a page-two defect needs a fixture of 25 to 60 rows before it can be proved at all.
 *
 * Usage: php storage/app/scratch/mutate-g40-workflow-monitor-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

$controller = 'app/Http/Controllers/Admin/WorkflowMonitorController.php';
$testFile = 'tests/Feature/Sorting/WorkflowMonitorSortTest.php';

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

/** Plant one exact replacement, then require the named case to fail naming its own assertion. */
$leg = static function (string $name, string $file, string $from, string $to, string $filter, string $needle, string $why) use (&$bad, &$legs, $runFile, $restore, $assertRestored, $testFile): void {
    $legs++;
    $body = (string) file_get_contents($file);
    $hits = substr_count($body, $from);
    if ($hits !== 1) {
        echo str_pad($name, 32)."BAD   needle occurs {$hits} time(s), wanted exactly 1\n";
        $bad++;

        return;
    }
    file_put_contents($file, str_replace($from, $to, $body));
    if (! str_contains((string) file_get_contents($file), $to)) {
        echo str_pad($name, 32)."BAD   the plant did not land on disk\n";
        $bad++;
        $restore();

        return;
    }

    [$code, $out] = $runFile($testFile, $filter);
    $restore();

    if (str_contains($out, 'No tests found')) {
        echo str_pad($name, 32)."BAD   the filter selected no test\n";
        $bad++;
    } elseif ($code === 0 || ! str_contains($out, '1 failed')) {
        echo str_pad($name, 32).'SURVIVED the promise is real but the case stayed green'."\n";
        $bad++;
    } elseif (! str_contains($out, $needle)) {
        echo str_pad($name, 32)."BAD   failed, but not naming its own assertion (wanted [{$needle}])\n";
        $bad++;
    } elseif (! $assertRestored()) {
        $bad++;
    } else {
        echo str_pad($name, 32)."ok    killed by the case that owns it — {$why}\n";
    }
};

$control = static function (string $name, string $filter) use (&$bad, &$legs, $runFile, $testFile): void {
    $legs++;
    [$code, $out] = $runFile($testFile, $filter);
    if ($code !== 0 || ! str_contains($out, '1 passed')) {
        echo str_pad($name, 32).'BAD   the control is not green: '.str_replace("\n", ' / ', mb_substr($out, -220))."\n";
        $bad++;

        return;
    }
    echo str_pad($name, 32)."ok    control green\n";
};

// ------------------------------------------------------------------ controls, on the untouched tree
$control('control-surname-order', 'the_student_header_orders_by_surname_through_a_hop_the_row_never_shows');
$control('control-echo', 'the_order_and_a_filter_are_both_echoed_so_neither_control_resets');

$leg(
    'sort-never-read', $controller,
    "            (string) \$request->input('sort', ''),",
    "            '',",
    'the_student_header_orders_by_surname_through_a_hop_the_row_never_shows',
    'the surname header did not order the pipeline by the student it names',
    'the helper was handed a key the action never read, so no header could ever move the list'
);

$leg(
    'direction-never-read', $controller,
    "            (string) \$request->input('direction', ''),",
    "            '',",
    'the_student_header_orders_by_surname_through_a_hop_the_row_never_shows',
    'the second click did not reverse the same column',
    'a desk could start an order but never reverse it — the arrow and the list would disagree'
);

$leg(
    'applied-order-never-echoed', $controller,
    "            'filters' => \$request->only(['office', 'status', 'studentType', 'term', 'search'])\n                + ['sort' => \$sort['column'], 'direction' => \$sort['direction']],",
    "            'filters' => \$request->only(['office', 'status', 'studentType', 'term', 'search']),",
    'the_order_and_a_filter_are_both_echoed_so_neither_control_resets',
    'the order the server obeyed never came back',
    'lane 46\'s NO-RETURN seen from the sort side: the control resets while the list stays narrowed'
);

$leg(
    'enum-column-withheld-again', $controller,
    "            'studentType' => ['column' => 'enrollments.studentType'],\n",
    '',
    'the_four_labels_that_are_derived_per_row_offer_no_header',
    'carries a header the handler still obeys',
    'the owner\'s ruling of 2026-10-31 was reversed: an ENUM column sits on the row and lost its header anyway'
);

$leg(
    'derived-column-offered', $controller,
    "            'enrollmentId' => ['column' => 'enrollments.enrollmentId'],",
    "            'progress' => ['column' => 'enrollments.yearLevel'],\n            'enrollmentId' => ['column' => 'enrollments.enrollmentId'],",
    'the_four_labels_that_are_derived_per_row_offer_no_header',
    'is built per row from the workflow',
    'a header painted for a value the query cannot order — the desk would click and get the same list'
);

$leg(
    'hard-coded-order-returns', $controller,
    "            ->when(\$request->search, \$search);",
    "            ->when(\$request->search, \$search)\n            ->orderByDesc('enrollmentId');",
    'the_action_asks_its_own_request_for_the_order_rather_than_the_helper_reaching_past_it',
    'the hard-coded order the mechanism replaced is back',
    'every number could stay equal while the last write wins the order — only the source read sees it'
);

$control('control-surname-order-after-all-plants', 'the_student_header_orders_by_surname_through_a_hop_the_row_never_shows');
$control('control-withheld-labels', 'the_four_labels_that_are_derived_per_row_offer_no_header');

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL KILLED' : 'NOT ALL KILLED', $legs, $bad);
exit($bad === 0 ? 0 : 1);
