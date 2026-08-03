<?php

namespace App\Models;

use App\Enums\Semester;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Academicterms extends Model
{
    protected $table = 'academicterms';

    protected $primaryKey = 'termId';

    public $timestamps = false;

    protected $fillable = ['academicYearId', 'semester', 'startDate', 'endDate'];

    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
            'startDate' => 'date',
            'endDate' => 'date',
        ];
    }

    /**
     * @return HasMany<Admissions, $this>
     */
    public function admissions(): HasMany
    {
        return $this->hasMany(Admissions::class, 'termId');
    }

    /**
     * @return BelongsTo<Academicyears, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(Academicyears::class, 'academicYearId');
    }

    /**
     * @return HasMany<Blocks, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(Blocks::class, 'termId');
    }

    /**
     * @return HasMany<Clearanceperiods, $this>
     */
    public function clearanceperiods(): HasMany
    {
        return $this->hasMany(Clearanceperiods::class, 'termId');
    }

    /**
     * @return HasMany<Enrollments, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollments::class, 'termId');
    }

    /**
     * @return HasMany<Examresults, $this>
     */
    public function examresults(): HasMany
    {
        return $this->hasMany(Examresults::class, 'termId');
    }

    /**
     * @return HasMany<Studentscholarships, $this>
     */
    public function studentscholarships(): HasMany
    {
        return $this->hasMany(Studentscholarships::class, 'termId');
    }
}
