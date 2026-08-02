<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Academicyears extends Model
{
    protected $table = 'academicyears';
    public $timestamps = false;
    protected $fillable = ['yearLabel', 'startDate', 'endDate'];

    protected function casts(): array
    {
        return [
            'startDate' => 'date',
            'endDate' => 'date',
        ];
    }

    public function ﻿academicterms(): HasMany
    {
        return $this->hasMany(﻿academicterms::class, 'academicYearId');
    }
}
