<?php
/**
 * Read-only probe: does an eager-load count (`withCount`) leave a bare `*` in the builder's own column list?
 *
 * It matters because `ListSort::apply()` pins the select to the base table's columns only when the list is EMPTY
 * — the G-51 rule. A handler that already carries a `*` plus a count subselect is not empty, so the pin would be
 * skipped and an order that joins a table would widen the desk row. This asks the builder instead of guessing from
 * the framework's source, and prints the array it actually holds. Nothing is written and no query is executed.
 */
require __DIR__.'/../../../vendor/autoload.php';
$app = require_once __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$q = App\Models\Blocks::query()->withCount(['enrolledSubjects' => fn ($sq) => $sq->select(DB::raw('count(distinct enrollmentId)'))]);
$cols = $q->getQuery()->getColumns();
echo 'withCount columns: '.json_encode($cols)."\n";
echo 'has bare star: '.(in_array('*', $cols, true) ? "YES\n" : "NO\n");
echo 'count: '.count($cols)."\n";

$plain = App\Models\Blocks::query();
echo 'plain empty: '.($plain->getQuery()->getColumns() === [] ? "YES\n" : "NO\n");
