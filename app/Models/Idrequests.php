<?php

namespace App\Models;

use App\Enums\IdRequestReason;
use App\Enums\IdRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Idrequests extends Model
{
    protected $table = 'idrequests';

    protected $primaryKey = 'idRequestId';

    public $timestamps = false;

    protected $fillable = ['enrollmentId', 'requestReason', 'emergencyContactName', 'emergencyContactNumber', 'bloodType', 'cardPhotoPath', 'producedByVendor', 'requestDate', 'status', 'reissueReason', 'is_reissue'];

    protected function casts(): array
    {
        return [
            'requestReason' => IdRequestReason::class,
            'requestDate' => 'date',
            'status' => IdRequestStatus::class,
            'is_reissue' => 'boolean',
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
     * @return HasOne<Studentids, $this>
     */
    public function studentids(): HasOne
    {
        return $this->hasOne(Studentids::class, 'idRequestId');
    }
}
