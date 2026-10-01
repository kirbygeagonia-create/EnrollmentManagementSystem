<?php

namespace App\Models;

use App\Enums\FeeUnitBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feetypes extends Model
{
    protected $table = 'feetypes';

    protected $primaryKey = 'feeTypeId';

    public $timestamps = false;

    protected $fillable = ['feeName', 'defaultAmount', 'unitBasis'];

    protected function casts(): array
    {
        return [
            'defaultAmount' => 'decimal:2',
            'unitBasis' => FeeUnitBasis::class,
        ];
    }

    /**
     * @return HasMany<Charges, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(Charges::class, 'feeTypeId');
    }

    /**
     * What a lost clearance slip costs to replace. The Accounting desk charges this
     * and the slip prints it, so the amount a student is told cannot drift away from
     * the amount recorded against them.
     */
    public static function clearanceSlipReplacementFee(): float
    {
        $configured = static::where('feeName', 'Clearance Slip Replacement')->first()?->defaultAmount;

        if ($configured !== null) {
            return (float) $configured;
        }

        return (float) (config('settings.clearanceReplacementFee') ?: 100);
    }
}
