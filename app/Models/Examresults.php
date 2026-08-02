<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Examresults extends Model
{
    protected $table = 'examresults';
    public $timestamps = false;
    protected $fillable = ['studentId', 'courseId', 'termId', 'examStage', 'examType', 'examResult', 'examDate'];

    protected function casts(): array
    {
        return [
            'examStage' => \App\Enums\ExamStage::class,
            'examType' => \App\Enums\ExamType::class,
            'examResult' => \App\Enums\ExamResult::class,
            'examDate' => 'date',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Courses::class, 'courseId');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Academicterms::class, 'termId');
    }
}
