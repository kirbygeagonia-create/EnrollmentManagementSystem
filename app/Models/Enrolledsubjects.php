<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrolledsubjects extends Model
{
    protected $table = 'enrolledsubjects';
    public $timestamps = false;
    protected $fillable = ['enrollmentId', 'subjectId', 'blockId', 'scheduleId', 'grade', 'status'];

    protected function casts(): array
    {
        return [
            'grade' => 'decimal:2',
            'status' => \App\Enums\EnrolledSubjectStatus::class,
        ];
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Blocks::class, 'blockId');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'enrollmentId');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedules::class, 'scheduleId');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subjects::class, 'subjectId');
    }
}
