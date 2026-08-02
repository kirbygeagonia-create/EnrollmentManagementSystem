<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Documentprintlog extends Model
{
    protected $table = 'documentprintlog';
    public $timestamps = false;
    protected $fillable = ['enrollmentId', 'documentType', 'printedDate', 'printedBy', 'documentNumber'];

    protected function casts(): array
    {
        return [
            'documentType' => \App\Enums\DocumentType::class,
            'printedDate' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'enrollmentId');
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'printedBy');
    }
}
