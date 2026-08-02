<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Curriculumsubjects extends Model
{
    protected $table = 'curriculumsubjects';
    public $timestamps = false;
    protected $fillable = ['curriculumId', 'subjectId', 'prerequisiteSubjectId', 'yearLevel', 'semesterOffered'];

    protected function casts(): array
    {
        return [
            'semesterOffered' => \App\Enums\SemesterOffered::class,
        ];
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculums::class, 'curriculumId');
    }

    public function prerequisiteSubject(): BelongsTo
    {
        return $this->belongsTo(Subjects::class, 'prerequisiteSubjectId');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subjects::class, 'subjectId');
    }
}
