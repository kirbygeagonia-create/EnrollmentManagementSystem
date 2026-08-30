<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Academicyears extends Model
{
    protected $table = 'academicyears';

    protected $primaryKey = 'academicYearId';

    public $timestamps = false;

    protected $fillable = ['academicYearId', 'yearLabel', 'startDate', 'endDate'];

    protected function casts(): array
    {
        return [
            'startDate' => 'date',
            'endDate' => 'date',
        ];
    }

    /**
     * @return HasMany<Academicterms, $this>
     */
    public function academicterms(): HasMany
    {
        return $this->hasMany(Academicterms::class, 'academicYearId');
    }
}
