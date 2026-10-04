<?php

namespace App\Models;

use App\Enums\ShiftRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A program change, as the school records it (ruling 11, G-7).
 *
 * Before this row existed, the only evidence a student had shifted was a `courseId` that
 * differed from their previous term's — nothing notified, nothing retired the old block
 * seat, and no history row said the two enrollments were one move (§21.4, §28 G-7).
 *
 * The signatures are three separate columns rather than a status alone, because the
 * status only says how far the paper got; the defense question is *who* filed it at
 * Department Evaluation, *who* endorsed it as dean or program head, and *who* made the
 * final call — which is the Guidance Councillor, granting or refusing.
 */
class Shiftingrequests extends Model
{
    protected $table = 'shiftingrequests';

    protected $primaryKey = 'shiftingRequestId';

    protected $fillable = [
        'studentId', 'currentCourseId', 'targetCourseId', 'currentEnrollmentId', 'grantedEnrollmentId',
        'willStatement', 'requestStatus', 'requestedBy', 'requestedAt',
        'termId', 'yearLevel', 'departmentSignedBy', 'departmentSignedAt',
        'decisionBy', 'decidedAt', 'decisionRemarks',
    ];

    protected function casts(): array
    {
        return [
            'requestStatus' => ShiftRequestStatus::class,
            'requestedAt' => 'datetime',
            'departmentSignedAt' => 'datetime',
            'decidedAt' => 'datetime',
        ];
    }

    /**
     * The step the request is waiting on, in the words the desk reads.
     */
    public function waitingOn(): string
    {
        return match ($this->requestStatus) {
            ShiftRequestStatus::Pending => 'the dean or program head signature',
            ShiftRequestStatus::Endorsed => 'the Guidance Councillor, whose signature is the final call',
            ShiftRequestStatus::Granted => 'nothing — the receiving enrollment has been issued',
            ShiftRequestStatus::Rejected => 'nothing — Guidance refused the shift',
        };
    }

    /**
     * @return BelongsTo<Students, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }

    /**
     * @return BelongsTo<Courses, $this>
     */
    public function currentCourse(): BelongsTo
    {
        return $this->belongsTo(Courses::class, 'currentCourseId');
    }

    /**
     * @return BelongsTo<Courses, $this>
     */
    public function targetCourse(): BelongsTo
    {
        return $this->belongsTo(Courses::class, 'targetCourseId');
    }

    /**
     * The record whose block seat this shift retires.
     *
     * @return BelongsTo<Enrollments, $this>
     */
    public function currentEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'currentEnrollmentId');
    }

    /**
     * The enrollment the grant issued — the receiving side of "these two are the shift".
     *
     * @return BelongsTo<Enrollments, $this>
     */
    public function grantedEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'grantedEnrollmentId');
    }

    /**
     * @return BelongsTo<Academicterms, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Academicterms::class, 'termId');
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'requestedBy');
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function departmentSignedByUser(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'departmentSignedBy');
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function decisionByUser(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'decisionBy');
    }
}
