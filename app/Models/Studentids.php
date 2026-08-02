<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Studentids extends Model
{
    protected $table = 'studentids';
    public $timestamps = false;
    protected $fillable = ['studentId', 'idRequestId', 'qrCode', 'issueDate', 'validationStatus', 'securityPhotoPath', 'validatedBy', 'validatedDate'];

    protected function casts(): array
    {
        return [
            'issueDate' => 'date',
            'validationStatus' => \App\Enums\IdValidationStatus::class,
            'validatedDate' => 'datetime',
        ];
    }

    public function idRequest(): BelongsTo
    {
        return $this->belongsTo(Idrequests::class, 'idRequestId');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'validatedBy');
    }
}
