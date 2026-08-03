<?php

namespace App\Models;

use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Examresults extends Model
{
    protected $table = 'examresults';

    protected $primaryKey = 'examId';

    public $timestamps = false;

    protected $fillable = ['studentId', 'courseId', 'termId', 'examStage', 'examType', 'examResult', 'examDate'];

    protected function casts(): array
    {
        return [
            'examStage' => ExamStage::class,
            'examType' => ExamType::class,
            'examResult' => ExamResult::class,
            'examDate' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Courses, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Courses::class, 'courseId');
    }

    /**
     * @return BelongsTo<Students, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }

    /**
     * @return BelongsTo<Academicterms, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Academicterms::class, 'termId');
    }
}
