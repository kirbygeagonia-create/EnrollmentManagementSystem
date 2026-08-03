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
}
