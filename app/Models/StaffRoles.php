<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffRoles extends Model
{
    protected $table = 'staff_roles';
    public $timestamps = false;

    public function role(): BelongsTo
    {
        return $this->belongsTo(Roles::class, 'roleId');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'userId');
    }
}
