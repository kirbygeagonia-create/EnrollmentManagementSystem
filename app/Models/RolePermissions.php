<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermissions extends Model
{
    protected $table = 'role_permissions';

    public $timestamps = false;

    /**
     * @return BelongsTo<Permissions, $this>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permissions::class, 'permissionId');
    }

    /**
     * @return BelongsTo<Roles, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Roles::class, 'roleId');
    }
}
