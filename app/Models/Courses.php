<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Courses extends Model
{
    protected $table = 'courses';

    protected $primaryKey = 'courseId';

    public $timestamps = false;

    protected $fillable = ['unitId', 'courseName', 'courseCode', 'requiresEntranceExam', 'requiresRetentionExam'];

    protected function casts(): array
    {
        return [
            'requiresEntranceExam' => 'boolean',
            'requiresRetentionExam' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Academicunits, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Academicunits::class, 'unitId');
    }

    /**
     * @return HasMany<Admissions, $this>
     */
    public function admissions(): HasMany
    {
        return $this->hasMany(Admissions::class, 'courseId');
    }

    /**
     * @return HasMany<Blocks, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(Blocks::class, 'courseId');
    }

    /**
     * @return HasMany<Curriculums, $this>
     */
    public function curriculums(): HasMany
    {
        return $this->hasMany(Curriculums::class, 'courseId');
    }

    /**
     * @return HasMany<Enrollments, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollments::class, 'courseId');
    }

    /**
     * @return HasMany<Examresults, $this>
     */
    public function examresults(): HasMany
    {
        return $this->hasMany(Examresults::class, 'courseId');
    }

    /**
     * @return HasMany<Majors, $this>
     */
    public function majors(): HasMany
    {
        return $this->hasMany(Majors::class, 'courseId');
    }
}
