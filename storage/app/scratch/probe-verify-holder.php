<?php
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default'=>'mysql','database.connections.mysql.database'=>'ems','database.connections.mysql.username'=>'root','database.connections.mysql.password'=>'']);
Illuminate\Support\Facades\DB::purge('mysql');
$pid = Illuminate\Support\Facades\DB::table('permissions')->where('name','exam.verify.general')->value('id');
printf("permission id=%s\n", var_export($pid, true));
foreach (Illuminate\Support\Facades\DB::table('role_permissions')->where('permissionId',$pid)->get() as $g) {
    $r = Illuminate\Support\Facades\DB::table('roles')->where('id',$g->roleId)->value('name');
    printf("  granted to role: %s\n", $r);
}
printf("total permissions=%d  total grants=%d\n",
  Illuminate\Support\Facades\DB::table('permissions')->count(),
  Illuminate\Support\Facades\DB::table('role_permissions')->count());
