<?php
/**
 * Post-retirement measurement of the live RBAC tables.
 *
 * Dropping a seeded permission moves every figure the authority document quotes, so the new
 * numbers have to be measured from the server rather than computed by hand — and the row that
 * used to hold it must be gone, along with its grants. Read-only.
 */
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

config([
    'database.default' => 'mysql',
    'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.username' => 'root',
    'database.connections.mysql.password' => '',
]);
DB::purge('mysql');

$permissions = DB::table('permissions')->count();
$guards = DB::table('permissions')->distinct()->count('guard_name');
$grants = DB::table('role_permissions')->count();
$roles = DB::table('roles')->count();
$modules = DB::table('permissions')->distinct()->count('module');

printf("permissions=%d (was 95)\nroles=%d\nmodules=%d\nrole_permissions=%d (was 317)\n",
    $permissions, $roles, $modules, $grants);

printf("exam.verify.general rows still present: %d\n",
    DB::table('permissions')->where('name', 'exam.verify.general')->count());
printf("orphan grants (role_permissions pointing at no permission): %d\n",
    DB::table('role_permissions')->whereNotIn('permissionId', fn ($q) => $q->select('id')->from('permissions'))->count());
printf("exam module holds: %s\n", DB::table('permissions')->where('module', 'exam')->pluck('name')->sort()->implode(', '));

// Who used to hold it, and what they hold now — SysAdmin and GuidanceStaff each lost one grant.
foreach (['GuidanceStaff', 'SysAdmin'] as $name) {
    $roleId = DB::table('roles')->where('name', $name)->value('id');
    printf("  %-14s grants now: %d\n", $name, DB::table('role_permissions')->where('roleId', $roleId)->count());
}
