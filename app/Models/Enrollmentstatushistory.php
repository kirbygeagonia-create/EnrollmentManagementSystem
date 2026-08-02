<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollmentstatushistory extends Model
{
    protected $table = 'enrollmentstatushistory';
    public $timestamps = false;
    protected $fillable = ['enrollmentId', 'fromStatus', 'toStatus', 'changedBy', 'remarks', 'changedAt'];

    protected function casts(): array
    {
        return [
            'changedAt' => 'datetime',
        ];
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'changedBy');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'enrollmentId');
    }
}
