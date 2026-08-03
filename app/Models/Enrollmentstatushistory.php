<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollmentstatushistory extends Model
{
    protected $table = 'enrollmentstatushistory';

    protected $primaryKey = 'historyId';

    public $timestamps = false;

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
