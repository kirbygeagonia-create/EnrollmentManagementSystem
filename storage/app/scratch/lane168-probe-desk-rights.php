<?php

use App\Models\Staffusers;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 3);
putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE=ems_l168_a');
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$app['config']->set('database.default', 'mysql');
$app['config']->set('database.connections.mysql.database', 'ems_l168_a');
DB::purge('mysql');

$user = Staffusers::find(6);
echo 'db: '.DB::connection()->getDatabaseName()."\n";
echo 'user: '.($user?->username ?? 'NULL').' officeId='.var_export($user?->officeId, true)."\n";
Auth::setUser(Staffusers::find(6));
echo 'Auth::id(): '.var_export(Auth::id(), true).' same-object: '.var_export(Auth::getUser()?->is($user), true)."\n";
echo 'roles: '.$user->getRoleNames()->implode(',')."\n";
echo 'has block.assign: '.var_export($user->hasPermissionTo('block.assign'), true)."\n";
echo 'hasRole OfficeHead: '.var_export($user->hasRole('OfficeHead'), true)."\n";
echo 'cache store: '.config('cache.default')."\n";
