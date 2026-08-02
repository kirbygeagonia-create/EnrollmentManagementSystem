<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Roles extends Model
{
    protected $table = 'roles';
    public $timestamps = false;
    protected $fillable = ['roleName', 'description'];

    public function role_permissions(): HasMany
    {
        return $this->hasMany(RolePermissions::class, 'roleId');
    }

    public function staff_roles(): HasMany
    {
        return $this->hasMany(StaffRoles::class, 'roleId');
    }
}
