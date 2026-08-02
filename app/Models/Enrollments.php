<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enrollments extends Model
{
    protected $table = 'enrollments';
    public $timestamps = false;
    protected $fillable = ['studentId', 'courseId', 'majorId', 'termId', 'yearLevel', 'admissionId', 'studentType', 'enrollmentType', 'academicStanding', 'enrollmentStatus', 'evaluatedBy', 'registrarProcessedBy', 'enrolledDate', 'formIssuedDate', 'formSignedDate'];

    protected function casts(): array
    {
        return [
            'studentType' => \App\Enums\StudentType::class,
            'enrollmentType' => \App\Enums\EnrollmentType::class,
            'academicStanding' => \App\Enums\AcademicStanding::class,
            'enrollmentStatus' => \App\Enums\EnrollmentStatus::class,
            'enrolledDate' => 'date',
            'formIssuedDate' => 'date',
            'formSignedDate' => 'date',
        ];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admissions::class, 'admissionId');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Courses::class, 'courseId');
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'evaluatedBy');
    }

    public function major(): BelongsTo
    {
        return $this->belongsTo(Majors::class, 'majorId');
    }

    public function registrarProcessedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'registrarProcessedBy');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Academicterms::class, 'termId');
    }

    public function clinicrecords(): HasMany
    {
        return $this->hasMany(Clinicrecords::class, 'enrollmentId');
    }

    public function creditedsubjects(): HasMany
    {
        return $this->hasMany(Creditedsubjects::class, 'enrollmentId');
    }

    public function documentprintlog(): HasMany
    {
        return $this->hasMany(Documentprintlog::class, 'enrollmentId');
    }

    public function enrolledsubjects(): HasMany
    {
        return $this->hasMany(Enrolledsubjects::class, 'enrollmentId');
    }

    public function enrollmentstatushistory(): HasMany
    {
        return $this->hasMany(Enrollmentstatushistory::class, 'enrollmentId');
    }

    public function enrollmentworkflow(): HasOne
    {
        return $this->hasOne(Enrollmentworkflow::class, 'enrollmentId');
    }

    public function idrequests(): HasMany
    {
        return $this->hasMany(Idrequests::class, 'enrollmentId');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payments::class, 'enrollmentId');
    }

    public function studentassessments(): HasOne
    {
        return $this->hasOne(Studentassessments::class, 'enrollmentId');
    }
}
