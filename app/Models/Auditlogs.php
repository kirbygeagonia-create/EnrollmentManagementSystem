<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditlogs extends Model
{
    protected $table = 'auditlogs';

    protected $primaryKey = 'auditId';

    public $timestamps = false;

    protected $fillable = ['userId', 'action', 'entityTable', 'entityId', 'oldValues', 'newValues', 'ipAddress'];

    protected function casts(): array
    {
        return [
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
