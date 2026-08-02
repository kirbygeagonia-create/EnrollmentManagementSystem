<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Studentclearances extends Model
{
    protected $table = 'studentclearances';
    public $timestamps = false;
    protected $fillable = ['studentId', 'clearancePeriodId', 'overallStatus', 'extendedDeadline', 'receivedBy', 'receivedDate'];

    protected function casts(): array
    {
        return [
            'overallStatus' => \App\Enums\ClearanceOverallStatus::class,
            'extendedDeadline' => 'date',
            'receivedDate' => 'datetime',
        ];
    }

    public function clearancePeriod(): BelongsTo
    {
        return $this->belongsTo(Clearanceperiods::class, 'clearancePeriodId');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'receivedBy');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }

    public function clearanceapprovals(): HasMany
    {
        return $this->hasMany(Clearanceapprovals::class, 'studentClearanceId');
    }
}
