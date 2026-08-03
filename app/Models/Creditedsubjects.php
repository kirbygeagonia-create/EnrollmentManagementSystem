<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Creditedsubjects extends Model
{
    protected $table = 'creditedsubjects';

    protected $primaryKey = 'creditedId';

    public $timestamps = false;

    protected $fillable = ['enrollmentId', 'transferRecordId', 'previousSubjectName', 'creditedToSubjectId', 'creditedUnits', 'remarks'];

    protected function casts(): array
    {
        return [
            'creditedUnits' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Subjects, $this>
     */
    public function creditedToSubject(): BelongsTo
    {
        return $this->belongsTo(Subjects::class, 'creditedToSubjectId');
    }

    /**
     * @return BelongsTo<Enrollments, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'enrollmentId');
    }

    /**
     * @return BelongsTo<Transferacademicrecords, $this>
     */
    public function transferRecord(): BelongsTo
    {
        return $this->belongsTo(Transferacademicrecords::class, 'transferRecordId');
    }
}
