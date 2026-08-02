<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Documents extends Model
{
    protected $table = 'documents';
    public $timestamps = false;
    protected $fillable = ['submissionId', 'fileUrl', 'fileType', 'uploadedDate', 'verifiedBy'];

    protected function casts(): array
    {
        return [
            'uploadedDate' => 'date',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Studentrequirementsubmissions::class, 'submissionId');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'verifiedBy');
    }
}
