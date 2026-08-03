<?php

namespace App\Models;

use App\Enums\IdValidationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Studentids extends Model
{
    protected $table = 'studentids';

    protected $primaryKey = 'idId';

    public $timestamps = false;

    protected $fillable = ['studentId', 'idRequestId', 'qrCode', 'issueDate', 'validationStatus', 'securityPhotoPath', 'validatedBy', 'validatedDate'];

    protected function casts(): array
    {
        return [
            'issueDate' => 'date',
            'validationStatus' => IdValidationStatus::class,
            'validatedDate' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Idrequests, $this>
     */
    public function idRequest(): BelongsTo
    {
        return $this->belongsTo(Idrequests::class, 'idRequestId');
    }

    /**
     * The enrollment this ID was requested for, via the ID request.
     *
     * @return HasOneThrough<Enrollments, Idrequests, $this>
     */
    public function enrollment(): HasOneThrough
    {
        return $this->hasOneThrough(
            Enrollments::class,
            Idrequests::class,
            'idRequestId',
            'enrollmentId',
            'idRequestId',
            'enrollmentId'
        );
    }

    /**
     * @return BelongsTo<Students, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'validatedBy');
    }
}
