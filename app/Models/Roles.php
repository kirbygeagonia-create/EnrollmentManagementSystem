<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasPermissions;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $description
 */
class Roles extends Model
{
    use HasPermissions;

    protected $table = 'roles';

    public $timestamps = false;

    protected $fillable = ['name', 'description', 'guard_name'];

    /**
     * The permissions a role grants.
     *
     * HasPermissions' own permissions() is the morphToMany onto
     * model_has_permissions — permissions granted directly to a model row — and
     * spatie's Role model overrides it onto the role pivot. This class only
     * borrows the trait, so every tick on the Roles screen attached the role's
     * id to model_has_permissions and granted nothing, while the row badge —
     * reading the same wrong relation — confirmed the save. Spatie resolves a
     * staff account through staff_roles -> role_permissions, which is the pair
     * this relation now writes.
     *
     * @return BelongsToMany<Permissions, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permissions::class, 'role_permissions', 'roleId', 'permissionId');
    }

    public function role_permissions(): HasMany
    {
        return $this->hasMany(RolePermissions::class, 'roleId');
    }

    public function staff_roles(): HasMany
    {
        return $this->hasMany(StaffRoles::class, 'roleId');
    }
}
