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

    public $timestamps = false;

    protected $fillable = ['studentId', 'courseId', 'majorId', 'termId', 'yearLevel', 'admissionId', 'studentType', 'enrollmentType', 'academicStanding', 'enrollmentStatus', 'evaluatedBy', 'registrarProcessedBy', 'enrolledDate', 'formIssuedDate', 'formSignedDate'];

    protected function casts(): array
    {
        return [
            'studentType' => StudentType::class,
            'enrollmentType' => EnrollmentType::class,
            'academicStanding' => AcademicStanding::class,
            'enrollmentStatus' => EnrollmentStatus::class,
            'enrolledDate' => 'date',
            'formIssuedDate' => 'date',
            'formSignedDate' => 'date',
        ];
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
    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'evaluatedBy');
    }

    /**
     * @return BelongsTo<Majors, $this>
     */
    public function major(): BelongsTo
    {
        return $this->belongsTo(Majors::class, 'majorId');
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function registrarProcessedBy(): BelongsTo
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
    public function enrolledsubjects(): HasMany
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
