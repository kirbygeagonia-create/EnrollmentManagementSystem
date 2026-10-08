<?php

namespace App\Models;

use App\Enums\ApplicantType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Courses extends Model
{
    protected $table = 'courses';

    protected $primaryKey = 'courseId';

    public $timestamps = false;

    protected $fillable = ['courseId', 'unitId', 'courseName', 'courseCode', 'requiresEntranceExam', 'requiresCourseSpecificExam', 'entranceExamExemptsTransferee', 'requiresRetentionExam'];

    protected function casts(): array
    {
        return [
            'requiresEntranceExam' => 'boolean',
            'requiresCourseSpecificExam' => 'boolean',
            'entranceExamExemptsTransferee' => 'boolean',
            'requiresRetentionExam' => 'boolean',
        ];
    }

    /**
     * Does this program waive the school-wide General Entrance Examination for
     * the applicant in front of you? (C-4, ruled 2026-10-07.)
     *
     * Three readers must answer alike: the approval blockers, the department's
     * exam roster, and the Stage 1 prerequisite on recording a course-specific
     * result. A waiver honoured by only two of them strands the applicant —
     * either still blocked, or unable to sit the department's own exam at all.
     */
    public function waivesGeneralEntranceExam(?ApplicantType $applicantType): bool
    {
        return $this->entranceExamExemptsTransferee
            && $applicantType === ApplicantType::Transferee;
    }

    /**
     * @return BelongsTo<Academicunits, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Academicunits::class, 'unitId');
    }

    /**
     * @return HasMany<Admissions, $this>
     */
    public function admissions(): HasMany
    {
        return $this->hasMany(Admissions::class, 'courseId');
    }

    /**
     * @return HasMany<Blocks, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(Blocks::class, 'courseId');
    }

    /**
     * @return HasMany<Curriculums, $this>
     */
    public function curriculums(): HasMany
    {
        return $this->hasMany(Curriculums::class, 'courseId');
    }

    /**
     * @return HasMany<Enrollments, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollments::class, 'courseId');
    }

    /**
     * @return HasMany<Examresults, $this>
     */
    public function examresults(): HasMany
    {
        return $this->hasMany(Examresults::class, 'courseId');
    }

    /**
     * @return HasMany<Majors, $this>
     */
    public function majors(): HasMany
    {
        return $this->hasMany(Majors::class, 'courseId');
    }
}
