<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Courses extends Model
{
    protected $table = 'courses';
    public $timestamps = false;
    protected $fillable = ['unitId', 'courseName', 'courseCode', 'requiresEntranceExam', 'requiresRetentionExam'];

    protected function casts(): array
    {
        return [
            'requiresEntranceExam' => 'boolean',
            'requiresRetentionExam' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Academicunits::class, 'unitId');
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admissions::class, 'courseId');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Blocks::class, 'courseId');
    }

    public function curriculums(): HasMany
    {
        return $this->hasMany(Curriculums::class, 'courseId');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollments::class, 'courseId');
    }

    public function examresults(): HasMany
    {
        return $this->hasMany(Examresults::class, 'courseId');
    }

    public function majors(): HasMany
    {
        return $this->hasMany(Majors::class, 'courseId');
    }
}
