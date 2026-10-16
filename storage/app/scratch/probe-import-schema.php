<?php
/**
 * Read-only probe: what a student row actually requires, and which text column a
 * Registrar's spreadsheet could realistically carry for each foreign key.
 */
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::select("
    select COLUMN_NAME c, IS_NULLABLE n, COLUMN_DEFAULT d, DATA_TYPE t, CHARACTER_MAXIMUM_LENGTH m
    from information_schema.COLUMNS
    where TABLE_SCHEMA = database() and TABLE_NAME = 'students'
    order by ORDINAL_POSITION
");

echo "== students: NOT NULL with no server default (a CSV column must supply these)\n";
foreach ($rows as $r) {
    if ($r->n === 'NO' && $r->d === null) {
        printf("  %-22s %-10s max=%s\n", $r->c, $r->t, $r->m ?? '-');
    }
}

echo "\n== students: NOT NULL but the server can fill it (a CSV may omit)\n";
foreach ($rows as $r) {
    if ($r->n === 'NO' && $r->d !== null) {
        printf("  %-22s %-10s default=%s\n", $r->c, $r->t, $r->d);
    }
}

echo "\n== students: nullable\n";
echo '  '.implode(', ', array_map(fn ($r) => $r->c, array_filter($rows, fn ($r) => $r->n === 'YES')))."\n";

foreach (['courses' => 'course', 'majors' => 'major', 'religions' => 'religion', 'genders' => null] as $table => $_) {
    $cols = DB::select("select COLUMN_NAME c, DATA_TYPE t from information_schema.COLUMNS
        where TABLE_SCHEMA = database() and TABLE_NAME = ? order by ORDINAL_POSITION", [$table]);
    printf("\n== %s columns: %s\n", $table, implode(', ', array_map(fn ($x) => $x->c, $cols)));
    $sample = DB::table($table)->limit(3)->get();
    foreach ($sample as $s) {
        echo '   '.json_encode($s)."\n";
    }
    $count = DB::table($table)->count();
    echo "   rows: {$count}\n";
}

echo "\n== unique indexes on students\n";
$idx = DB::select("select INDEX_NAME i, COLUMN_NAME c, NON_UNIQUE u from information_schema.STATISTICS
    where TABLE_SCHEMA = database() and TABLE_NAME = 'students' order by INDEX_NAME, SEQ_IN_INDEX");
$byName = [];
foreach ($idx as $x) {
    $byName[$x->i][] = $x->c.($x->u ? '' : ' [UNIQUE]');
}
foreach ($byName as $name => $cols) {
    echo "  {$name}: ".implode(' + ', $cols)."\n";
}
