<?php
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
config(['database.default'=>'mysql','database.connections.mysql.database'=>'ems',
        'database.connections.mysql.username'=>'root','database.connections.mysql.password'=>'']);
DB::purge('mysql');

$sysadminId = DB::table('roles')->where('name','SysAdmin')->value('id');
$granted = DB::table('role_permissions')->where('roleId','!=',$sysadminId)->distinct()->pluck('permissionId')->all();
$onlySys = DB::table('permissions')->whereNotIn('id',$granted)->orderBy('name')->pluck('name')->all();
printf("permissions held by SysAdmin alone: %d\n", count($onlySys));

printf("Role::updateOrCreate calls in RbacSeeder: %d\n",
    preg_match_all('/Role::updateOrCreate/', file_get_contents(dirname(__DIR__,3).'/database/seeders/RbacSeeder.php')));
printf("Permission::firstOrCreate calls: %d\n",
    preg_match_all('/Permission::firstOrCreate/', file_get_contents(dirname(__DIR__,3).'/database/seeders/RbacSeeder.php')));

foreach (DB::table('role_permissions as rp')->join('roles as r','r.id','=','rp.roleId')->groupBy('r.name')->select('r.name', DB::raw('count(*) c'))->orderBy('r.name')->get() as $x) {
    printf("%s=%d\n", $x->name, $x->c);
}
