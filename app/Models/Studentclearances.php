<?php

namespace App\Models;

use App\Enums\ClearanceOverallStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Studentclearances extends Model
{
    protected $table = 'studentclearances';

    protected $primaryKey = 'studentClearanceId';

    public $timestamps = false;

    protected $fillable = ['studentId', 'clearancePeriodId', 'overallStatus', 'extendedDeadline', 'receivedBy', 'receivedDate'];

    protected function casts(): array
    {
        return [
            'overallStatus' => ClearanceOverallStatus::class,
            'extendedDeadline' => 'date',
            'receivedDate' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Clearanceperiods, $this>
     */
    public function clearancePeriod(): BelongsTo
    {
        return $this->belongsTo(Clearanceperiods::class, 'clearancePeriodId');
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'receivedBy');
    }

    /**
     * @return BelongsTo<Students, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }

    /**
     * @return HasMany<Clearanceapprovals, $this>
     */
    public function clearanceapprovals(): HasMany
    {
        return $this->hasMany(Clearanceapprovals::class, 'studentClearanceId');
    }

    /**
     * Alias for clearanceapprovals().
     *
     * @return HasMany<Clearanceapprovals, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(Clearanceapprovals::class, 'studentClearanceId');
    }
}
