<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clearanceperiods extends Model
{
    protected $table = 'clearanceperiods';
    public $timestamps = false;
    protected $fillable = ['termId', 'clearanceStartDate', 'clearanceEndDate', 'periodStatus'];

    protected function casts(): array
    {
        return [
            'clearanceStartDate' => 'date',
            'clearanceEndDate' => 'date',
            'periodStatus' => \App\Enums\ClearancePeriodStatus::class,
        ];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Academicterms::class, 'termId');
    }

    public function studentclearances(): HasMany
    {
        return $this->hasMany(Studentclearances::class, 'clearancePeriodId');
    }
}
