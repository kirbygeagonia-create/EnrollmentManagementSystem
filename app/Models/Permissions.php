<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $module
 */
class Permissions extends Model
{
    protected $table = 'permissions';

    public $timestamps = false;

    protected $fillable = ['name', 'module', 'guard_name'];

    public function role_permissions(): HasMany
    {
        return $this->hasMany(RolePermissions::class, 'permissionId');
    }
}
