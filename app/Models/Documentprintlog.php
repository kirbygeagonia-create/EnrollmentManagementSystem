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

    protected $fillable = ['enrollmentId', 'studentId', 'blockId', 'documentType', 'printedDate', 'printedBy', 'documentNumber'];

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
     * @return BelongsTo<Students, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }

    /**
     * @return BelongsTo<Blocks, $this>
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(Blocks::class, 'blockId');
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'printedBy');
    }
}
