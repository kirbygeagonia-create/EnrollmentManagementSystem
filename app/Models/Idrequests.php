<?php

namespace App\Models;

use App\Enums\IdRequestReason;
use App\Enums\IdRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Idrequests extends Model
{
    protected $table = 'idrequests';

    protected $primaryKey = 'idRequestId';

    public $timestamps = true;

    protected $fillable = ['enrollmentId', 'requestReason', 'emergencyContactName', 'emergencyContactNumber', 'bloodType', 'cardPhotoPath', 'requestDate', 'status', 'validatedBy', 'validatedDate'];

    protected function casts(): array
    {
        return [
            'requestReason' => IdRequestReason::class,
            'requestDate' => 'date',
            'status' => IdRequestStatus::class,
            'validatedDate' => 'datetime',
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
    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'validatedBy', 'userId');
    }
}
