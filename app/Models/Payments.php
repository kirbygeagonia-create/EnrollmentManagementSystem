<?php

namespace App\Models;

use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payments extends Model
{
    protected $table = 'payments';

    protected $primaryKey = 'paymentId';

    public $timestamps = false;

    protected $fillable = ['enrollmentId', 'orNumber', 'amount', 'paymentDate', 'paymentMode', 'processedBy', 'paymentStatus'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paymentDate' => 'datetime',
            'paymentMode' => PaymentMode::class,
            'paymentStatus' => PaymentStatus::class,
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
     * @return BelongsTo<Staffusers, $this>
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'processedBy');
    }
}
