<?php

namespace App\Models;

use App\Enums\AcademicStanding;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\StudentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enrollments extends Model
{
    protected $table = 'enrollments';

    protected $primaryKey = 'enrollmentId';

    public $timestamps = true;

    protected $fillable = ['studentId', 'courseId', 'majorId', 'termId', 'yearLevel', 'admissionId', 'studentType', 'enrollmentType', 'academicStanding', 'enrollmentStatus', 'evaluatedBy', 'registrarProcessedBy', 'returnReason', 'dropReason', 'clearanceConfirmedBy', 'clearanceConfirmedAt', 'curriculumId', 'enrolledDate', 'formIssuedDate', 'formSignedDate'];

    protected function casts(): array
    {
        return [
            'studentType' => StudentType::class,
            'enrollmentType' => EnrollmentType::class,
            'academicStanding' => AcademicStanding::class,
            'enrollmentStatus' => EnrollmentStatus::class,
            'enrolledDate' => 'date',
            'clearanceConfirmedAt' => 'datetime',
            'formIssuedDate' => 'date',
            'formSignedDate' => 'date',
        ];
    }

    /**
     * Standing and student type live on the enrollment, but the Exam and
     * Clearance queues are keyed by (student, term) and carry no enrollment
     * foreign key. This reads the whole page in one query instead of one per row.
     *
     * @param  iterable<int, array{0: int, 1: int|null}>  $pairs
     * @return array<string, self> keyed "{studentId}-{termId}"
     */
    public static function standingMapFor(iterable $pairs): array
    {
        $pairs = collect($pairs)->filter(fn (array $p) => $p[0] && $p[1]);

        if ($pairs->isEmpty()) {
            return [];
        }

        // Oldest first so the later write wins and a pair keeps its newest enrollment.
        return static::query()
            ->whereIn('studentId', $pairs->pluck(0)->unique())
            ->whereIn('termId', $pairs->pluck(1)->unique())
            ->orderBy('enrollmentId')
            ->get(['enrollmentId', 'studentId', 'termId', 'studentType', 'academicStanding'])
            ->keyBy(fn (self $e) => $e->studentId.'-'.$e->termId)
            ->all();
    }

    /**
     * @return BelongsTo<Admissions, $this>
     */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admissions::class, 'admissionId');
    }

    /**
     * @return BelongsTo<Courses, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Courses::class, 'courseId');
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function evaluatedByUser(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'evaluatedBy');
    }

    /**
     * The Evaluation desk staff member who confirmed the student's clearance pass
     * slip (ruling 5). Null means nobody at the department ever confirmed it, which is
     * not the same as it being confirmed and is what stops the Registrar's approval.
     *
     * @return BelongsTo<Staffusers, $this>
     */
    public function clearanceConfirmedByUser(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'clearanceConfirmedBy');
    }

    /**
     * @return BelongsTo<Majors, $this>
     */
    public function major(): BelongsTo
    {
        return $this->belongsTo(Majors::class, 'majorId');
    }

    /**
     * Curriculum version this enrollment is pinned to (item 7).
     *
     * @return BelongsTo<Curriculums, $this>
     */
    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculums::class, 'curriculumId');
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function registrarProcessedByUser(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'registrarProcessedBy');
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

    /**
     * @return HasMany<Clinicrecords, $this>
     */
    public function clinicrecords(): HasMany
    {
        return $this->hasMany(Clinicrecords::class, 'enrollmentId');
    }

    /**
     * @return HasMany<Creditedsubjects, $this>
     */
    public function creditedsubjects(): HasMany
    {
        return $this->hasMany(Creditedsubjects::class, 'enrollmentId');
    }

    /**
     * @return HasMany<Documentprintlog, $this>
     */
    public function documentprintlog(): HasMany
    {
        return $this->hasMany(Documentprintlog::class, 'enrollmentId');
    }

    /**
     * @return HasMany<Enrolledsubjects, $this>
     */
    public function enrolledSubjects(): HasMany
    {
        return $this->hasMany(Enrolledsubjects::class, 'enrollmentId');
    }

    /**
     * @return HasMany<Enrollmentstatushistory, $this>
     */
    public function enrollmentstatushistory(): HasMany
    {
        return $this->hasMany(Enrollmentstatushistory::class, 'enrollmentId');
    }

    /**
     * @return HasOne<Enrollmentworkflow, $this>
     */
    public function enrollmentworkflow(): HasOne
    {
        return $this->hasOne(Enrollmentworkflow::class, 'enrollmentId');
    }

    /**
     * @return HasMany<Idrequests, $this>
     */
    public function idrequests(): HasMany
    {
        return $this->hasMany(Idrequests::class, 'enrollmentId');
    }

    /**
     * @return HasMany<Payments, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payments::class, 'enrollmentId');
    }

    /**
     * @return HasOne<Studentassessments, $this>
     */
    public function studentassessments(): HasOne
    {
        return $this->hasOne(Studentassessments::class, 'enrollmentId');
    }
}
