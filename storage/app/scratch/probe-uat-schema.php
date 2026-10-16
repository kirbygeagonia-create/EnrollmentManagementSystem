<?php
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
config(['database.default'=>'mysql','database.connections.mysql.database'=>'ems',
        'database.connections.mysql.username'=>'root','database.connections.mysql.password'=>'']);
DB::purge('mysql');
$want = ['staffusers','roles','staff_roles','admissions','admissionrequirements','enrollments',
         'studentassessments','studentscholarships','payments','studentclearances','studentclearancedetails',
         'clearanceperiods','documentprintlog','shiftingrequests','clinicrecords','idrequests',
         'workflowsteps','enrollmentworkflow','examresults','schedule','blocks','schedulemeetings',
         'enrollmentSubjects','feeTypes','courses','referenceData','academicterms'];
foreach ($want as $t) {
    $exists = DB::select("SHOW TABLES LIKE '$t'");
    printf("%-26s %s\n", $t, $exists ? 'EXISTS' : 'MISSING');
}
echo "\n=== all tables ===\n";
foreach (DB::select('SHOW TABLES') as $r) { echo implode(',', (array) $r), "\n"; }
