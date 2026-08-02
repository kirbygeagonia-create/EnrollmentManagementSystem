<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admissions extends Model
{
    protected $table = 'admissions';
    public $timestamps = false;
    protected $fillable = ['studentId', 'termId', 'courseId', 'applicantType', 'admissionStatus', 'evaluatedBy', 'evaluatedDate'];

    protected function casts(): array
    {
        return [
            'applicantType' => \App\Enums\ApplicantType::class,
            'admissionStatus' => \App\Enums\AdmissionStatus::class,
            'evaluatedDate' => 'date',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Courses::class, 'courseId');
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'evaluatedBy');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Academicterms::class, 'termId');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollments::class, 'admissionId');
    }

    public function studentrequirementsubmissions(): HasMany
    {
        return $this->hasMany(Studentrequirementsubmissions::class, 'admissionId');
    }
}
