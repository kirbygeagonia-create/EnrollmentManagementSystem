<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feetypes extends Model
{
    protected $table = 'feetypes';
    public $timestamps = false;
    protected $fillable = ['feeName', 'defaultAmount', 'unitBasis'];

    protected function casts(): array
    {
        return [
            'defaultAmount' => 'decimal:2',
            'unitBasis' => \App\Enums\FeeUnitBasis::class,
        ];
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charges::class, 'feeTypeId');
    }
}
