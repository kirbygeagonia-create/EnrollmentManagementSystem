<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollmentstatushistory extends Model
{
    protected $table = 'enrollmentstatushistory';

    protected $primaryKey = 'historyId';

    public $timestamps = false;

    /**
     * Same rule as `Auditlogs`: the application stamps the row, because `changedAt` no longer carries a
     * database default to disagree with `config('app.timezone')` about. See Auditlogs::booted().
     */
    protected static function booted(): void
    {
        static::creating(fn (self $row) => $row->changedAt ??= now());
    }

    protected $fillable = ['enrollmentId', 'fromStatus', 'toStatus', 'changedBy', 'remarks', 'changedAt'];

    protected function casts(): array
    {
        return [
            'changedAt' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'changedBy');
    }

    /**
     * @return BelongsTo<Enrollments, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'enrollmentId');
    }
}
