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

    protected $fillable = ['studentId', 'clearancePeriodId', 'overallStatus', 'extendedDeadline', 'receivedBy', 'receivedDate'];

    protected function casts(): array
    {
        return [
            'overallStatus' => ClearanceOverallStatus::class,
            'extendedDeadline' => 'date',
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
     * clearance period belongs to. Reading `student->enrollments` in list order gives
     * the first enrollment the student ever had, so a student who cleared after
     * advancing would be printed with their course and year level from years ago.
     */
    public function termEnrollment(): ?Enrollments
    {
        $this->loadMissing('student.enrollments');

        $enrollments = $this->student->enrollments;

        if ($enrollments->isEmpty()) {
            return null;
        }

        $termId = $this->clearancePeriod?->termId;
        $exact = $termId === null
            ? null
            : $enrollments->first(fn (Enrollments $enrollment) => $enrollment->termId === $termId);

        // PROVISIONAL: a slip raised after its term closed has no enrollment in that
        // term, so the newest enrollment is used rather than printing "N/A". Registrar
        // to confirm which enrollment a clearance slip should name.
        return $exact ?? $enrollments->sortByDesc('enrollmentId')->first();
    }
}
