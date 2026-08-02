<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Creditedsubjects extends Model
{
    protected $table = 'creditedsubjects';
    public $timestamps = false;
    protected $fillable = ['enrollmentId', 'transferRecordId', 'previousSubjectName', 'creditedToSubjectId', 'creditedUnits', 'remarks'];

    protected function casts(): array
    {
        return [
            'creditedUnits' => 'decimal:2',
        ];
    }

    public function creditedToSubject(): BelongsTo
    {
        return $this->belongsTo(Subjects::class, 'creditedToSubjectId');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'enrollmentId');
    }

    public function transferRecord(): BelongsTo
    {
        return $this->belongsTo(Transferacademicrecords::class, 'transferRecordId');
    }
}
