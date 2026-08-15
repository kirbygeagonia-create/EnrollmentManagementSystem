<?php

namespace App\Models;

use App\Enums\SubjectType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subjects extends Model
{
    protected $table = 'subjects';

    protected $primaryKey = 'subjectId';

    public $timestamps = false;

    protected $fillable = ['subjectCode', 'subjectName', 'lectureUnits', 'labUnits', 'subjectType'];

    protected function casts(): array
    {
        return [
            'lectureUnits' => 'decimal:2',
            'labUnits' => 'decimal:2',
            'subjectType' => SubjectType::class,
        ];
    }

    /**
     * @return HasMany<Creditedsubjects, $this>
     */
    public function creditedsubjects(): HasMany
    {
        return $this->hasMany(Creditedsubjects::class, 'creditedToSubjectId');
    }

    /**
     * @return HasMany<Curriculumsubjects, $this>
     */
    public function prerequisiteCurriculumsubjects(): HasMany
    {
        return $this->hasMany(Curriculumsubjects::class, 'prerequisiteSubjectId');
    }

    /**
     * @return HasMany<Curriculumsubjects, $this>
     */
    public function curriculumsubjects(): HasMany
    {
        return $this->hasMany(Curriculumsubjects::class, 'subjectId');
    }

    /**
     * @return HasMany<Enrolledsubjects, $this>
     */
    public function enrolledSubjects(): HasMany
    {
        return $this->hasMany(Enrolledsubjects::class, 'subjectId');
    }

    /**
     * @return HasMany<Schedules, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedules::class, 'subjectId');
    }
}
