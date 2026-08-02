<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Academicterms extends Model
{
    protected $table = 'academicterms';
    public $timestamps = false;
    public $incrementing = false;
    protected $fillable = ['academicYearId', 'semester', 'startDate', 'endDate'];

    protected function casts(): array
    {
        return [
            'semester' => \App\Enums\Semester::class,
            'startDate' => 'date',
            'endDate' => 'date',
        ];
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admissions::class, 'termId');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Blocks::class, 'termId');
    }

    public function clearanceperiods(): HasMany
    {
        return $this->hasMany(Clearanceperiods::class, 'termId');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollments::class, 'termId');
    }

    public function examresults(): HasMany
    {
        return $this->hasMany(Examresults::class, 'termId');
    }

    public function studentscholarships(): HasMany
    {
        return $this->hasMany(Studentscholarships::class, 'termId');
    }
}
