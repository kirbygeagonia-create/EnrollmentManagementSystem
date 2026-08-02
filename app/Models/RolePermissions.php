<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermissions extends Model
{
    protected $table = 'role_permissions';
    public $timestamps = false;

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permissions::class, 'permissionId');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Roles::class, 'roleId');
    }
}
