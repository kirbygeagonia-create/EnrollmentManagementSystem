<?php

namespace App\Models;

use App\Enums\PaymentStatus;
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

    /**
     * What the student still owes, recomputed from the receipts on file instead of
     * read from the stored `remainingBalance`. The cashier desk settles accounts on
     * this figure, so a stale column must never mark an unpaid student as settled.
     * Only receipts still standing as `Paid` count — a voided OR keeps its row for
     * the audit trail but no longer covers anything.
     */
    public function outstandingBalance(): float
    {
        $paid = (float) $this->payments()->where('paymentStatus', PaymentStatus::Paid)->sum('amount');

        return max(0, (float) $this->totalAssessedAmount - (float) $this->totalScholarshipCoverage - (float) $this->totalWaived - $paid);
    }
}
