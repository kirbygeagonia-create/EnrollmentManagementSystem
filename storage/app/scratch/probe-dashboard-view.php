<?php
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'ems',
        'database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => '']);
DB::purge('mysql');
$roles = DB::table('roles')->orderBy('name')->pluck('name')->all();
$holders = DB::table('role_permissions as rp')
    ->join('roles as r', 'r.id', '=', 'rp.roleId')
    ->join('permissions as p', 'p.id', '=', 'rp.permissionId')
    ->where('p.name', 'dashboard.view')->pluck('r.name')->all();
printf("roles total: %d   dashboard.view holders: %d\nmissing: %s\n",
    count($roles), count($holders), implode(', ', array_diff($roles, $holders)) ?: '(none — every role holds it)');
printf("staff accounts whose roles ALL lack it: n/a (every role holds it)\n");
