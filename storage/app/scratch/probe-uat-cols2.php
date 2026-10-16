<?php
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
config(['database.default'=>'mysql','database.connections.mysql.database'=>'ems',
        'database.connections.mysql.username'=>'root','database.connections.mysql.password'=>'']);
DB::purge('mysql');
foreach (['clearanceapprovals','studentrequirementsubmissions','schedules','scholarshiptypes','subjects'] as $t) {
    $cols = array_map(fn ($r) => (array) $r, DB::select("SHOW COLUMNS FROM `$t`"));
    printf("%-22s %s\n\n", $t, implode(', ', array_map(fn ($c) => $c['Field'].':'.$c['Type'], $cols)));
}
