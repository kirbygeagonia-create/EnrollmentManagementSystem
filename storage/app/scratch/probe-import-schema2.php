<?php
// Read-only probe 2: the two enum columns' accepted values, and what uniqueness the
// students table actually enforces.
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

foreach (['gender', 'status', 'civilStatus'] as $col) {
    $row = DB::selectOne("select COLUMN_TYPE t from information_schema.COLUMNS
        where TABLE_SCHEMA = database() and TABLE_NAME = 'students' and COLUMN_NAME = ?", [$col]);
    echo "{$col}: {$row->t}\n";
}

echo "\n== indexes on students\n";
$idx = DB::select("select INDEX_NAME i, COLUMN_NAME c, NON_UNIQUE u from information_schema.STATISTICS
    where TABLE_SCHEMA = database() and TABLE_NAME = 'students' order by INDEX_NAME, SEQ_IN_INDEX");
$byName = [];
foreach ($idx as $x) {
    $byName[$x->i][] = $x->c.($x->u ? '' : ' [UNIQUE]');
}
foreach ($byName as $name => $cols) {
    echo "  {$name}: ".implode(' + ', $cols)."\n";
}

echo "\n== live counts\n";
printf("  students: %d\n", DB::table('students')->count());
printf("  distinct schoolIdNumber: %d\n", DB::table('students')->distinct()->count('schoolIdNumber'));
printf("  distinct username: %d\n", DB::table('students')->distinct()->count('username'));
printf("  empty/NULL email: %d\n", DB::table('students')->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))->count());
printf("  schoolIdNumber like 'DEMO%%': %d\n", DB::table('students')->where('schoolIdNumber', 'like', 'DEMO%')->count());
echo "  schoolIdNumber samples: ".implode(', ', DB::table('students')->limit(4)->pluck('schoolIdNumber')->all())."\n";
echo "  username samples: ".implode(', ', DB::table('students')->limit(4)->pluck('username')->all())."\n";

echo "\n== enums in PHP\n";
foreach (['StudentStatus', 'Gender'] as $c) {
    $fq = "App\\Enums\\{$c}";
    if (enum_exists($fq)) {
        echo "  {$c}: ".implode(', ', array_map(fn ($x) => $x->value, $fq::cases()))."\n";
    } else {
        echo "  {$c}: (no such enum class)\n";
    }
}
echo "  App\\Enums files: ".implode(', ', array_map('basename', glob(app_path('Enums').'/*.php')))."\n";
