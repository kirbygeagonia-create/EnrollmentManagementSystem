<?php
// Which per-table "Rows in the demo database" figures has the build actually moved?

$flat = file(dirname(__DIR__, 3).'/storage/app/scratch/docx-flat.txt', FILE_IGNORE_NEW_LINES);

$blocks = [];
$current = null;
foreach ($flat as $line) {
    if (preg_match('/^P \d+: ([a-z_]+) — \d+ columns$/', $line, $m)) {
        $current = ['table' => $m[1], 'heading' => $line, 'count' => null];

        continue;
    }
    if ($current !== null && preg_match('/Rows in the demo database: (\d+)\./', $line, $m)) {
        $current['count'] = (int) $m[1];
        $blocks[] = $current;
        $current = null;
    }
}

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

config([
    'database.default' => 'mysql',
    'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.username' => 'root',
    'database.connections.mysql.password' => '',
]);
DB::purge('mysql');

$existing = collect(DB::select("select table_name t from information_schema.tables where table_schema='ems'"))
    ->pluck('t')->map(fn ($t) => strtolower($t))->all();

printf("%-32s %8s %8s  %s\n", 'table', 'doc', 'live', 'note');
$moved = 0;
foreach ($blocks as $b) {
    $table = $b['table'];
    if (! in_array($table, $existing, true)) {
        printf("%-32s %8d %8s  %s\n", $table, $b['count'], '—', 'NO SUCH TABLE live');
        $moved++;

        continue;
    }
    $live = (int) DB::table($table)->count();
    if ($live !== $b['count']) {
        printf("%-32s %8d %8d  MOVED\n", $table, $b['count'], $live);
        $moved++;
    }
}
printf("\n%d of %d documented tables read a different figure today\n", $moved, count($blocks));
