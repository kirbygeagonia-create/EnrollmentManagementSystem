<?php
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
config(['database.default'=>'mysql','database.connections.mysql.database'=>'ems',
        'database.connections.mysql.username'=>'root','database.connections.mysql.password'=>'']);
DB::purge('mysql');
$have = DB::table('role_permissions as rp')->join('permissions as p','p.id','=','rp.permissionId')
    ->where('p.name','clearance.view')->pluck('rp.roleId')->all();
foreach (DB::table('roles')->orderBy('name')->get() as $r) {
    printf("  %-22s %s\n", $r->name, in_array($r->id, $have, true) ? 'holds clearance.view' : 'LACKS it');
}
