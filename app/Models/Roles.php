<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasPermissions;

class Roles extends Model
{
    use HasPermissions;

    protected $table = 'roles';

    public $timestamps = false;

    protected $fillable = ['name', 'description', 'guard_name'];

    public function role_permissions(): HasMany
    {
        return $this->hasMany(RolePermissions::class, 'roleId');
    }

    public function staff_roles(): HasMany
    {
        return $this->hasMany(StaffRoles::class, 'roleId');
    }
}
