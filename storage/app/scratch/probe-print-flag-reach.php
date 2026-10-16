<?php
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
config(['database.default'=>'mysql','database.connections.mysql.database'=>'ems',
        'database.connections.mysql.username'=>'root','database.connections.mysql.password'=>'']);
DB::purge('mysql');

$cols = array_column(DB::select('describe staffusers'),'Field');
echo "staffusers cols: ".implode(', ', array_slice($cols,0,14))."\n\n";

$rows = DB::table('staff_roles as sr')
    ->join('roles as r','r.id','=','sr.roleId')
    ->where('r.name','OfficeHead')
    ->pluck('sr.model_id')->all();
echo "OfficeHead accounts: ".count($rows)."\n";
foreach ($rows as $id) {
    $u = DB::table('staffusers')->where("userId",$id)->first();
    if (! $u) { echo "  model_id $id -> no staffusers row\n"; continue; }
    $office = DB::table('offices')->where('officeId',$u->officeId)->value('officeName');
    printf("  %-26s officeId=%-3s (%s)\n", $u->email ?? '?', $u->officeId, $office ?? 'unknown');
}
