<?php
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
config(['database.default'=>'mysql','database.connections.mysql.database'=>'ems',
        'database.connections.mysql.username'=>'root','database.connections.mysql.password'=>'']);
DB::purge('mysql');
printf("permissions=%d  roles=%d  grants=%d\n", DB::table('permissions')->count(),
    DB::table('roles')->count(), DB::table('role_permissions')->count());
printf("dashboard.view rows=%d  its grants=%d\n", DB::table('permissions')->where('name','dashboard.view')->count(),
    DB::table('role_permissions as rp')->join('permissions as p','p.id','=','rp.permissionId')->where('p.name','dashboard.view')->count());
printf("orphan grants (no permission row)=%d\n", DB::table('role_permissions as rp')->leftJoin('permissions as p','p.id','=','rp.permissionId')->whereNull('p.id')->count());
printf("modules in catalogue=%d\n", DB::table('permissions')->distinct()->count('module'));
printf("role counts changed: %s\n", DB::table('role_permissions as rp')->join('roles as r','r.id','=','rp.roleId')->groupBy('r.name')->select('r.name', DB::raw('count(*) c'))->orderBy('r.name')->get()->map(fn($x)=>$x->name.':'.$x->c)->implode(', '));
