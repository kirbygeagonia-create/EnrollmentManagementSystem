<?php

namespace App\Models;

use App\Enums\CoverageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scholarshiptypes extends Model
{
    protected $table = 'scholarshiptypes';

    protected $primaryKey = 'scholarshipTypeId';

    public $timestamps = false;

    protected $fillable = ['scholarshipName', 'coverageType', 'coveragePercent'];

    protected function casts(): array
    {
        return [
            'coverageType' => CoverageType::class,
            'coveragePercent' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<Studentscholarships, $this>
     */
    public function studentscholarships(): HasMany
    {
        return $this->hasMany(Studentscholarships::class, 'scholarshipTypeId');
    }
}
