<?php

namespace App\Models;

use App\Enums\UnitType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Academicunits extends Model
{
    protected $table = 'academicunits';

    protected $primaryKey = 'unitId';

    public $timestamps = false;

    protected $fillable = ['unitName', 'unitType', 'parentUnitId'];

    protected function casts(): array
    {
        return [
            'unitType' => UnitType::class,
        ];
    }

    /**
     * @return BelongsTo<Academicunits, $this>
     */
    public function parentUnit(): BelongsTo
    {
        return $this->belongsTo(Academicunits::class, 'parentUnitId');
    }

    /**
     * @return HasMany<Academicunits, $this>
     */
    public function academicunits(): HasMany
    {
        return $this->hasMany(Academicunits::class, 'parentUnitId');
    }

    /**
     * @return HasMany<Courses, $this>
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Courses::class, 'unitId');
    }

    /**
     * @return HasMany<Staffusers, $this>
     */
    public function staffusers(): HasMany
    {
        return $this->hasMany(Staffusers::class, 'unitId');
    }
}
