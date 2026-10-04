<?php

namespace App\Models;

use App\Enums\ClearancePeriodStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clearanceperiods extends Model
{
    protected $table = 'clearanceperiods';

    protected $primaryKey = 'clearancePeriodId';

    public $timestamps = false;

    protected $fillable = ['termId', 'clearanceStartDate', 'clearanceEndDate', 'periodStatus'];

    protected function casts(): array
    {
        return [
            'clearanceStartDate' => 'date',
            'clearanceEndDate' => 'date',
            'periodStatus' => ClearancePeriodStatus::class,
        ];
    }

    /**
     * The window that is taking clearances: open, or extended past its end date.
     *
     * Every desk that asks "is clearance season?" reads this instead of writing its own
     * comparison, because an extension used to be invisible to exactly the lookups that
     * mattered — a period moved to `extended` was read as no period at all, which stops
     * the Clearance desk issuing slips and, once the Registrar's gate blocks on a missing
     * window (ruling 4), would have held every continuing student.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAccepting(Builder $query): Builder
    {
        return $query->whereIn('periodStatus', [
            ClearancePeriodStatus::Open->value,
            ClearancePeriodStatus::Extended->value,
        ]);
    }

    public function isAccepting(): bool
    {
        return in_array($this->periodStatus, [
            ClearancePeriodStatus::Open,
            ClearancePeriodStatus::Extended,
        ], true);
    }

    /**
     * @return BelongsTo<Academicterms, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Academicterms::class, 'termId');
    }

    /**
     * @return HasMany<Studentclearances, $this>
     */
    public function studentclearances(): HasMany
    {
        return $this->hasMany(Studentclearances::class, 'clearancePeriodId');
    }
}
