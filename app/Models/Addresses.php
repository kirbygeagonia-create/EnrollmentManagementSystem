<?php

namespace App\Models;

use App\Enums\AddressType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Addresses extends Model
{
    protected $table = 'addresses';

    protected $primaryKey = 'addressId';

    public $timestamps = false;

    protected $fillable = ['studentId', 'addressType', 'houseBuildingNo', 'street', 'sitioPurok', 'barangay', 'cityMunicipality', 'district', 'province', 'region', 'country', 'zipCode'];

    protected function casts(): array
    {
        return [
            'addressType' => AddressType::class,
        ];
    }

    /**
     * @return BelongsTo<Students, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }
}
