<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Academicunits extends Model
{
    protected $table = 'academicunits';
    public $timestamps = false;
    protected $fillable = ['unitName', 'unitType', 'parentUnitId'];

    protected function casts(): array
    {
        return [
            'unitType' => \App\Enums\UnitType::class,
        ];
    }

    public function parentUnit(): BelongsTo
    {
        return $this->belongsTo(Academicunits::class, 'parentUnitId');
    }

    public function academicunits(): HasMany
    {
        return $this->hasMany(Academicunits::class, 'parentUnitId');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Courses::class, 'unitId');
    }

    public function staffusers(): HasMany
    {
        return $this->hasMany(Staffusers::class, 'unitId');
    }
}
