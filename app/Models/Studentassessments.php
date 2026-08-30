<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Studentassessments extends Model
{
    protected $table = 'studentassessments';

    protected $primaryKey = 'assessmentId';

    public $timestamps = true;

    protected $fillable = ['enrollmentId', 'totalAssessedAmount', 'totalScholarshipCoverage', 'totalWaived', 'remainingBalance', 'assessmentDate'];

    protected function casts(): array
    {
        return [
            'totalAssessedAmount' => 'decimal:2',
            'totalScholarshipCoverage' => 'decimal:2',
            'totalWaived' => 'decimal:2',
            'remainingBalance' => 'decimal:2',
            'assessmentDate' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Enrollments, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'enrollmentId');
    }

    /**
     * Payments made against the enrollment this assessment belongs to.
     *
     * @return HasMany<Payments, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payments::class, 'enrollmentId', 'enrollmentId');
    }

    /**
     * Scholarships awarded to the student of the enrollment this assessment belongs to.
     *
     * @return HasManyThrough<Studentscholarships, Enrollments, $this>
     */
    public function scholarships(): HasManyThrough
    {
        return $this->hasManyThrough(
            Studentscholarships::class,
            Enrollments::class,
            'enrollmentId',
            'studentId',
            'enrollmentId',
            'studentId'
        );
    }

    /**
     * @return HasMany<Charges, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(Charges::class, 'assessmentId');
    }
}
