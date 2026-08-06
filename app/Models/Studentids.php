<?php

namespace App\Models;

use App\Enums\IdValidationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     * The enrollment this ID was requested for, via the ID request.
     * NOTE: use $this->idRequest->enrollment — a direct hasOneThrough is not
     * possible here because studentids points at idrequests (reverse direction).
     *
     * @return BelongsTo<Idrequests, $this>
     */
    public function idRequest(): BelongsTo
    {
        return $this->belongsTo(Idrequests::class, 'idRequestId');
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
