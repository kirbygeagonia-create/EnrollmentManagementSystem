<?php

namespace App\Models;

use App\Enums\SemesterOffered;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Curriculumsubjects extends Model
{
    protected $table = 'curriculumsubjects';

    protected $primaryKey = 'curriculumSubjectId';

    public $timestamps = false;

    protected $fillable = ['curriculumId', 'subjectId', 'prerequisiteSubjectId', 'yearLevel', 'semesterOffered'];

    protected function casts(): array
    {
        return [
            'semesterOffered' => SemesterOffered::class,
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
}
