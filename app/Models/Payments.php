<?php

namespace App\Models;

use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payments extends Model
{
    protected $table = 'payments';

    protected $primaryKey = 'paymentId';

    public $timestamps = true;

    protected $fillable = ['enrollmentId', 'orNumber', 'amount', 'paymentDate', 'paymentMode', 'processedBy', 'paymentStatus', 'refundedAt', 'refundedBy', 'refundedReason'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paymentDate' => 'datetime',
            'refundedAt' => 'datetime',
            'paymentMode' => PaymentMode::class,
            'paymentStatus' => PaymentStatus::class,
        ];
    }

    /**
     * The receipts whose money the school is still holding.
     *
     * A part payment is cash in the drawer exactly as a settled one is, so every balance,
     * collection total and revenue figure has to read both — otherwise the installment the
     * cashier took would disappear from the account it was paid against, and the student
     * would be shown as owing money twice. `pending` (a voided receipt) and `refunded`
     * (money handed back) are excluded by that same reasoning: neither is money held.
     *
     * @param  Builder<Payments>  $query
     * @return Builder<Payments>
     */
    public function scopeHeld(Builder $query): Builder
    {
        return $query->whereIn('paymentStatus', [
            PaymentStatus::Paid->value,
            PaymentStatus::Partial->value,
        ]);
    }

    /**
     * @return BelongsTo<Enrollments, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'enrollmentId');
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'processedBy');
    }

    /**
     * The cashier who handed the money back (ruling 14), with `refundedAt` and
     * `refundedReason` the disbursement record for the receipt.
     *
     * @return BelongsTo<Staffusers, $this>
     */
    public function refundedByUser(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'refundedBy');
    }
}
