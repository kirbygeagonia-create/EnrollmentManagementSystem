<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Studentassessments extends Model
{
    protected $table = 'studentassessments';
    public $timestamps = false;
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

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'enrollmentId');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charges::class, 'assessmentId');
    }
}
