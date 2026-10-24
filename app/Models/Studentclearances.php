<?php

namespace App\Models;

use App\Enums\ClearanceOverallStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Studentclearances extends Model
{
    protected $table = 'studentclearances';

    protected $primaryKey = 'studentClearanceId';

    public $timestamps = true;

    protected $fillable = ['studentId', 'clearancePeriodId', 'overallStatus', 'receivedBy', 'receivedDate'];

    protected function casts(): array
    {
        return [
            'overallStatus' => ClearanceOverallStatus::class,
            'receivedDate' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Clearanceperiods, $this>
     */
    public function clearancePeriod(): BelongsTo
    {
        return $this->belongsTo(Clearanceperiods::class, 'clearancePeriodId');
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'receivedBy');
    }

    /**
     * @return BelongsTo<Students, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }

    /**
     * @return HasMany<Clearanceapprovals, $this>
     */
    public function clearanceapprovals(): HasMany
    {
        return $this->hasMany(Clearanceapprovals::class, 'studentClearanceId');
    }

    /**
     * Alias for clearanceapprovals().
     *
     * @return HasMany<Clearanceapprovals, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(Clearanceapprovals::class, 'studentClearanceId');
    }

    /**
     * The enrollment this clearance covers — the student's enrollment in the term the
     * clearance period belongs to, or nothing when that term holds none. Reading
     * `student->enrollments` in list order gives the first enrollment the student ever
     * had, so a student who cleared after advancing would be printed with their course
     * and year level from years ago.
     */
    public function termEnrollment(): ?Enrollments
    {
        $this->loadMissing('student.enrollments');

        $termId = $this->clearancePeriod?->termId;

        if ($termId === null) {
            return null;
        }

        // Ruled 2026-10-23 (P-17): a clearance is issued when the semester ends, for the enrollment
        // of that term. A slip raised after its term closed therefore names no enrollment rather
        // than borrowing a later one's course and year level; each print path already renders the
        // absence (clearance-slip.blade.php:69 "N/A", PrintSlip.jsx:33 "—", and both print-log calls
        // key the issuance on the student). The remedy for such a slip is the right period.
        return $this->student->enrollments
            ->first(fn (Enrollments $enrollment) => $enrollment->termId === $termId);
    }
}
