<?php
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Staffusers;
use Illuminate\Support\Facades\DB;
config(['database.default'=>'mysql','database.connections.mysql.database'=>'ems',
        'database.connections.mysql.username'=>'root','database.connections.mysql.password'=>'']);
DB::purge('mysql');

$roleIds = DB::table('role_permissions as rp')
    ->join('permissions as p','p.id','=','rp.permissionId')
    ->where('p.name','enrollment.approve')->pluck('rp.roleId')->all();
$names = DB::table('roles')->whereIn('id',$roleIds)->pluck('name')->all();
echo "roles with enrollment.approve: ".implode(', ', $names)."\n";

$modelIds = DB::table('staff_roles')->whereIn('roleId',$roleIds)->pluck('model_id')->all();
printf("accounts holding one of those roles: %d\n", count($modelIds));
foreach (Staffusers::whereIn('userId',$modelIds)->get() as $u) {
    printf("  %-30s roles=%-28s officeId=%-4s unitId=%s\n", $u->email,
        implode('|',$u->getRoleNames()->all()), $u->officeId, $u->unitId ?? 'null');
}
