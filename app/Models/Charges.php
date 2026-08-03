<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Charges extends Model
{
    protected $table = 'charges';

    protected $primaryKey = 'chargeId';

    public $timestamps = false;

    protected $fillable = ['assessmentId', 'feeTypeId', 'amount', 'waivedAmount'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'waivedAmount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Studentassessments, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Studentassessments::class, 'assessmentId');
    }

    /**
     * @return BelongsTo<Feetypes, $this>
     */
    public function feeType(): BelongsTo
    {
        return $this->belongsTo(Feetypes::class, 'feeTypeId');
    }
}
