<?php

namespace App\Models;

use App\Enums\ClearancePeriodStatus;
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
