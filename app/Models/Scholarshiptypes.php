<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scholarshiptypes extends Model
{
    protected $table = 'scholarshiptypes';
    public $timestamps = false;
    protected $fillable = ['scholarshipName', 'coverageType', 'coveragePercent'];

    protected function casts(): array
    {
        return [
            'coverageType' => \App\Enums\CoverageType::class,
            'coveragePercent' => 'decimal:2',
        ];
    }

    public function studentscholarships(): HasMany
    {
        return $this->hasMany(Studentscholarships::class, 'scholarshipTypeId');
    }
}
