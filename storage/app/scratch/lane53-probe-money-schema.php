<?php

// Lane 53 step 1: what money actually exists in the schema. Lanes 47-52 settled what a desk is shown; this lane
// asks whether the peso figures add up — and that cannot be answered by reading the controllers, which agree in
// prose and differ in rounding. This prints every DECIMAL column and the tables that hold fee lines, so the
// arithmetic audit that follows is pointed at real columns rather than guessed names.
//
//   php storage/app/scratch/lane53-probe-money-schema.php   (read-only)

$root = dirname(__DIR__, 3);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$db = (string) DB::connection()->getDatabaseName();
printf("database: %s\n\n", $db);

$cols = DB::select(
    'select table_name t, column_name c, column_type ty from information_schema.columns
      where table_schema = ? and data_type = "decimal" order by table_name, column_name',
    [$db]
);

echo "=== DECIMAL columns\n";

foreach ($cols as $r) {
    printf("  %-28s %-28s %s\n", $r->t, $r->c, $r->ty);
}

$counts = [];

foreach ($cols as $r) {
    $counts[$r->t] = ($counts[$r->t] ?? 0) + 1;
}

echo "\n=== rows per money table\n";

foreach (array_keys($counts) as $t) {
    $n = DB::select('select count(*) n from `'.$t.'`')[0]->n;
    printf("  %-30s %6d rows (%d decimal column(s))\n", $t, $n, $counts[$t]);
}

echo "\n=== payments by status\n";

foreach (DB::select('select paymentStatus s, count(*) n, sum(amount) total from payments group by paymentStatus order by s') as $r) {
    printf("  %-12s n=%-4d sum=%s\n", (string) $r->s, $r->n, (string) $r->total);
}

echo "\n=== fee line tables the fee sheet could be built from\n";

foreach (DB::select('select table_name t from information_schema.tables where table_schema = ? and (table_name like "%fee%" or table_name like "%line%" or table_name like "%item%")', [$db]) as $r) {
    $n = DB::select('select count(*) n from `'.$r->t.'`')[0]->n;
    printf("  %-32s %6d rows\n", $r->t, $n);
}
