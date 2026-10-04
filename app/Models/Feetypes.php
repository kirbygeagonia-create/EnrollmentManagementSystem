<?php

namespace App\Models;

use App\Enums\FeeUnitBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feetypes extends Model
{
    /**
     * The fee-type row that carries the clearance-slip replacement amount. The Registrar
     * maintains it in Admin → Reference Data → Fee Types, so this name is the link between
     * the desk's charge and the printed slip.
     */
    public const CLEARANCE_SLIP_REPLACEMENT = 'Clearance Slip Replacement';

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
     * What a lost clearance slip costs to replace. The Accounting desk charges this and
     * the slip prints it, so the amount a student is told cannot drift away from the
     * amount recorded against them — both read the one fee-type row the Registrar
     * maintains. Null when that row is missing: an amount nobody has set is the
     * Registrar's to set, and inventing one here is how a desk comes to print and charge
     * a figure no fee schedule records (§25 P-12, ruling 8).
     */
    public static function clearanceSlipReplacementFee(): ?float
    {
        $configured = static::where('feeName', self::CLEARANCE_SLIP_REPLACEMENT)->first()?->defaultAmount;

        return $configured === null ? null : (float) $configured;
    }
}
