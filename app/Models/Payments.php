<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payments extends Model
{
    protected $table = 'payments';
    public $timestamps = false;
    protected $fillable = ['enrollmentId', 'orNumber', 'amount', 'paymentDate', 'paymentMode', 'processedBy', 'paymentStatus'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paymentDate' => 'datetime',
            'paymentMode' => \App\Enums\PaymentMode::class,
            'paymentStatus' => \App\Enums\PaymentStatus::class,
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'enrollmentId');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'processedBy');
    }
}
