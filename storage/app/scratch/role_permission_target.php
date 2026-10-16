<?php

/**
 * Probe: where does syncPermissions() on App\Models\Roles actually write?
 *
 * Run: DB_CONNECTION=sqlite DB_DATABASE="<win path>/storage/app/scratch/audit.sqlite" \
 *      MSYS_NO_PATHCONV=1 php artisan tinker storage/app/scratch/role_permission_target.php
 * (or require this file from a bootstrapped app)
 */

use App\Models\Permissions;
use App\Models\Roles;
use Illuminate\Support\Facades\DB;

$role = Roles::create([
    'name' => 'ProbeRole'.uniqid(),
    'guard_name' => 'web',
    'description' => 'probe',
]);

$permissionIds = Permissions::query()->limit(2)->pluck('id')->all();

echo 'target permission ids: '.json_encode($permissionIds)."\n";

$role->syncPermissions($permissionIds);

echo 'relation SQL: '.str_replace('?', '[?]', $role->permissions()->toRawSql())."\n";
echo 'pivot table read by permissions(): '.$role->permissions()->getTable()."\n";
echo 'pivot FKs: '.$role->permissions()->getForeignPivotKeyName().' / '.$role->permissions()->getRelatedPivotKeyName()."\n";

echo "\nrole_permissions rows for this role: ".DB::table('role_permissions')->where('roleId', $role->id)->count()."\n";
echo "model_has_permissions rows for this role: ".DB::table('model_has_permissions')
    ->where('model_type', Roles::class)
    ->where('model_id', $role->id)
    ->count()."\n";

$fresh = Roles::with('permissions')->find($role->id);
echo 'with("permissions") badge count: '.count($fresh->permissions)."\n";

echo 'does App\Models\Roles bust the spatie cache? instanceof Contract\\Role = '
    .var_export($role instanceof \Spatie\Permission\Contracts\Role, true)."\n";
