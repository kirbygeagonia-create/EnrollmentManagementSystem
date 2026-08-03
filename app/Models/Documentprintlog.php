<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Documentprintlog extends Model
{
    protected $table = 'documentprintlog';

    protected $primaryKey = 'printLogId';

    public $timestamps = false;

    protected $fillable = ['enrollmentId', 'documentType', 'printedDate', 'printedBy', 'documentNumber'];

    protected function casts(): array
    {
        return [
            'documentType' => DocumentType::class,
            'printedDate' => 'datetime',
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
    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'printedBy');
    }
}
