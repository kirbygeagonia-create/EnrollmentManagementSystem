<?php

namespace App\Models;

use App\Enums\SemesterOffered;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Curriculumsubjects extends Model
{
    protected $table = 'curriculumsubjects';

    protected $primaryKey = 'curriculumSubjectId';

    public $timestamps = false;

    protected $fillable = ['curriculumId', 'subjectId', 'prerequisiteSubjectId', 'yearLevel', 'semesterOffered', 'is_elective', 'elective_group', 'elective_min_choices', 'elective_max_choices'];

    protected function casts(): array
    {
        return [
            'semesterOffered' => SemesterOffered::class,
            'is_elective' => 'boolean',
            'elective_min_choices' => 'integer',
            'elective_max_choices' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Curriculums, $this>
     */
    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculums::class, 'curriculumId');
    }

    /**
     * @return BelongsTo<Subjects, $this>
     */
    public function prerequisiteSubject(): BelongsTo
    {
        return $this->belongsTo(Subjects::class, 'prerequisiteSubjectId');
    }

    /**
     * @return BelongsTo<Subjects, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subjects::class, 'subjectId');
    }

    public function isElective(): bool
    {
        return $this->is_elective;
    }

    public function scopeElectives(Builder $query): Builder
    {
        return $query->where('is_elective', true);
    }

    public function scopeMandatory(Builder $query): Builder
    {
        return $query->where('is_elective', false);
    }
}
