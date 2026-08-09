<?php

namespace App\Models;

use App\Enums\EnrolledSubjectStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrolledsubjects extends Model
{
    protected $table = 'enrolledsubjects';

    protected $primaryKey = 'enrolledSubjectId';

    public $timestamps = false;

    protected $fillable = ['enrollmentId', 'subjectId', 'blockId', 'scheduleId', 'grade', 'status', 'attempt_number', 'original_enrolled_subject_id'];

    protected function casts(): array
    {
        return [
            'grade' => 'decimal:2',
            'status' => EnrolledSubjectStatus::class,
            'attempt_number' => 'integer',
            'original_enrolled_subject_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Blocks, $this>
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(Blocks::class, 'blockId');
    }

    /**
     * @return BelongsTo<Enrollments, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'enrollmentId');
    }

    /**
     * @return BelongsTo<Schedules, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedules::class, 'scheduleId');
    }

    /**
     * @return BelongsTo<Subjects, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subjects::class, 'subjectId');
    }

    /**
     * @return BelongsTo<Enrolledsubjects, $this>
     */
    public function originalAttempt(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_enrolled_subject_id', 'enrolledSubjectId');
    }

    public function isRetake(): bool
    {
        return $this->attempt_number > 1;
    }
}
