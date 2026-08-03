<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Documents extends Model
{
    protected $table = 'documents';

    protected $primaryKey = 'documentId';

    public $timestamps = false;

    protected $fillable = ['submissionId', 'fileUrl', 'fileType', 'uploadedDate', 'verifiedBy'];

    protected function casts(): array
    {
        return [
            'uploadedDate' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Studentrequirementsubmissions, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Studentrequirementsubmissions::class, 'submissionId');
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'verifiedBy');
    }
}
