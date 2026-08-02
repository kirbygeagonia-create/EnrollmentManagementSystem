<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Charges extends Model
{
    protected $table = 'charges';
    public $timestamps = false;
    protected $fillable = ['assessmentId', 'feeTypeId', 'amount', 'waivedAmount'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'waivedAmount' => 'decimal:2',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Studentassessments::class, 'assessmentId');
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(Feetypes::class, 'feeTypeId');
    }
}
