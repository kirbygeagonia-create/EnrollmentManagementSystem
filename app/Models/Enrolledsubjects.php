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

    protected $fillable = ['enrollmentId', 'subjectId', 'blockId', 'scheduleId', 'grade', 'status'];

    protected function casts(): array
    {
        return [
            'grade' => 'decimal:2',
            'status' => EnrolledSubjectStatus::class,
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
}
