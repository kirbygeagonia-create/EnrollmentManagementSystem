<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permissions extends Model
{
    protected $table = 'permissions';
    public $timestamps = false;
    protected $fillable = ['permissionName', 'module'];

    public function role_permissions(): HasMany
    {
        return $this->hasMany(RolePermissions::class, 'permissionId');
    }
}
