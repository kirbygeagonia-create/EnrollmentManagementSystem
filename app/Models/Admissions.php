<?php

namespace App\Models;

use App\Enums\AdmissionStatus;
use App\Enums\ApplicantType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admissions extends Model
{
    protected $table = 'admissions';

    protected $primaryKey = 'admissionId';

    public $timestamps = false;

    protected $fillable = ['studentId', 'termId', 'courseId', 'applicantType', 'admissionStatus', 'evaluatedBy', 'evaluatedDate'];

    protected function casts(): array
    {
        return [
            'applicantType' => ApplicantType::class,
            'admissionStatus' => AdmissionStatus::class,
            'evaluatedDate' => 'date',
        ];
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
     * @return HasMany<Enrollments, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollments::class, 'admissionId');
    }

    /**
     * @return HasMany<Studentrequirementsubmissions, $this>
     */
    public function studentrequirementsubmissions(): HasMany
    {
        return $this->hasMany(Studentrequirementsubmissions::class, 'admissionId');
    }

    /**
     * Alias for studentrequirementsubmissions().
     *
     * @return HasMany<Studentrequirementsubmissions, $this>
     */
    public function requirementSubmissions(): HasMany
    {
        return $this->hasMany(Studentrequirementsubmissions::class, 'admissionId');
    }

    /**
     * Exam results for this admission's student, scoped to the admission's term.
     *
     * @return HasMany<Examresults, $this>
     */
    public function examresults(): HasMany
    {
        return $this->hasMany(Examresults::class, 'studentId', 'studentId')
            ->whereColumn('examresults.termId', 'admissions.termId');
    }
}
