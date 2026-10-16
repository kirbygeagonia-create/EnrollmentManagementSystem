<?php
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
config(['database.default'=>'mysql','database.connections.mysql.database'=>'ems',
        'database.connections.mysql.username'=>'root','database.connections.mysql.password'=>'']);
DB::purge('mysql');
foreach (['staffusers','roles','staff_roles','admissions','admissionrequirements','enrollments',
          'studentassessments','studentscholarships','payments','studentclearances','clearancerequirements',
          'clearanceperiods','documentprintlog','shiftingrequests','clinicrecords','idrequests','examresults',
          'blocks','schedulemeetings','enrolledsubjects','feetypes','courses','academicterms'] as $t) {
    $cols = array_map(fn ($r) => (array) $r, DB::select("SHOW COLUMNS FROM `$t`"));
    printf("%-24s %s\n\n", $t, implode(', ', array_map(fn ($c) => $c['Field'].':'.$c['Type'], $cols)));
}
