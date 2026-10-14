<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditlogs extends Model
{
    protected $table = 'auditlogs';

    protected $primaryKey = 'auditId';

    public $timestamps = false;

    /**
     * The application is the only process allowed to stamp an audit row: `createdAt` has no database
     * default, because a MySQL session zone that is not the app's zone would put an eleven-hour
     * difference into a plain DATETIME that stores no zone. Ruled 2026-10-13, after measuring PHP and
     * `NOW()` disagreeing by 11.0 hours on the development box.
     *
     * Filled here rather than at each call site so that no insert can forget it — the first version
     * of that rule patched the two `UserManagementController` writers and the suite then failed on a
     * third one (a test fixture writing a `login` row), which is the argument for putting it here.
     */
    protected static function booted(): void
    {
        static::creating(fn (self $log) => $log->createdAt ??= now());
    }

    protected $fillable = ['userId', 'action', 'adminOverride', 'entityTable', 'entityId', 'oldValues', 'newValues', 'ipAddress', 'createdAt'];

    protected function casts(): array
    {
        return [
            'adminOverride' => 'boolean',
            'oldValues' => 'array',
            'newValues' => 'array',
            'createdAt' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'userId');
    }
}
