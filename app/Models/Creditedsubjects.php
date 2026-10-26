<?php

namespace App\Models;

use App\Enums\CreditDecision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Creditedsubjects extends Model
{
    protected $table = 'creditedsubjects';

    protected $primaryKey = 'creditedId';

    public $timestamps = false;

    protected $fillable = ['enrollmentId', 'transferRecordId', 'previousSubjectName', 'creditedToSubjectId', 'creditedUnits', 'remarks', 'creditDecision', 'decidedBy', 'decidedDate', 'decisionReason'];

    protected function casts(): array
    {
        return [
            'creditedUnits' => 'decimal:2',
            'creditDecision' => CreditDecision::class,
            'decidedDate' => 'date',
        ];
    }

    /**
     * The rows that count: a credit subtracts a mandatory subject and satisfies a
     * prerequisite only while the department's decision on it is approval.
     *
     * One definition on purpose — the three readers that ask "what has this student
     * already covered" must not disagree with the panel that shows the decision.
     *
     * @param  Builder<Creditedsubjects>  $query
     * @return Builder<Creditedsubjects>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('creditDecision', CreditDecision::Approved->value);
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function decidedByUser(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'decidedBy', 'userId');
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
