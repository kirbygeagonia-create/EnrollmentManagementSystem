<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Addresses extends Model
{
    protected $table = 'addresses';
    public $timestamps = false;
    protected $fillable = ['studentId', 'addressType', 'houseBuildingNo', 'street', 'sitioPurok', 'barangay', 'cityMunicipality', 'district', 'province', 'region', 'country', 'zipCode'];

    protected function casts(): array
    {
        return [
            'addressType' => \App\Enums\AddressType::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }
}
