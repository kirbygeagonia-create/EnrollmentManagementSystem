<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Idrequests extends Model
{
    protected $table = 'idrequests';
    public $timestamps = false;
    protected $fillable = ['enrollmentId', 'requestReason', 'emergencyContactName', 'emergencyContactNumber', 'bloodType', 'cardPhotoPath', 'producedByVendor', 'requestDate', 'status'];

    protected function casts(): array
    {
        return [
            'requestReason' => \App\Enums\IdRequestReason::class,
            'requestDate' => 'date',
            'status' => \App\Enums\IdRequestStatus::class,
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'enrollmentId');
    }

    public function studentids(): HasOne
    {
        return $this->hasOne(Studentids::class, 'idRequestId');
    }
}
