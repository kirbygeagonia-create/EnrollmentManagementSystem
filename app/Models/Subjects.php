<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subjects extends Model
{
    protected $table = 'subjects';
    public $timestamps = false;
    protected $fillable = ['subjectCode', 'subjectName', 'lectureUnits', 'labUnits', 'subjectType'];

    protected function casts(): array
    {
        return [
            'lectureUnits' => 'decimal:2',
            'labUnits' => 'decimal:2',
            'subjectType' => \App\Enums\SubjectType::class,
        ];
    }

    public function creditedsubjects(): HasMany
    {
        return $this->hasMany(Creditedsubjects::class, 'creditedToSubjectId');
    }

    public function prerequisiteCurriculumsubjects(): HasMany
    {
        return $this->hasMany(Curriculumsubjects::class, 'prerequisiteSubjectId');
    }

    public function curriculumsubjects(): HasMany
    {
        return $this->hasMany(Curriculumsubjects::class, 'subjectId');
    }

    public function enrolledsubjects(): HasMany
    {
        return $this->hasMany(Enrolledsubjects::class, 'subjectId');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedules::class, 'subjectId');
    }
}
