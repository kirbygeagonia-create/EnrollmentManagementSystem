<?php

namespace App\Models;

use App\Enums\ReturnCategory;
use App\Enums\ReturnDestination;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One return of one record, with every fact development prompt §6.4 lists stored in its own column.
 *
 * Read the pair of status columns as the state machine's own words — `previousStatus` and
 * `newStatus` are the same two values `enrollmentstatushistory` writes in the same transaction — and
 * the rest as the routing: which desk sent it, which desk must act next, why, and what that desk has
 * to finish before the record moves.
 */
class Enrollmentreturns extends Model
{
    protected $table = 'enrollmentreturns';

    protected $primaryKey = 'returnId';

    public $timestamps = false;

    /**
     * Same rule as `Enrollmentstatushistory`: the application stamps the moment, because a database
     * default would disagree with `config('app.timezone')` about what UTC means on this box.
     */
    protected static function booted(): void
    {
        static::creating(fn (self $row) => $row->returnedAt ??= now());
    }

    protected $fillable = [
        'enrollmentId',
        'workflowId',
        'originOfficeId',
        'originStepOrder',
        'destination',
        'destinationOfficeId',
        'destinationStepOrder',
        'returnCategory',
        'returnNote',
        'expectedCorrection',
        'previousStatus',
        'newStatus',
        'returnedBy',
        'returnedAt',
    ];

    protected function casts(): array
    {
        return [
            'destination' => ReturnDestination::class,
            'returnCategory' => ReturnCategory::class,
            'returnedAt' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Enrollments, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'enrollmentId', 'enrollmentId');
    }

    /**
     * @return BelongsTo<Enrollmentworkflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Enrollmentworkflow::class, 'workflowId', 'workflowId');
    }

    /**
     * The account that sent it back. Named `*User` like every other staff relation.
     *
     * @return BelongsTo<Staffusers, $this>
     */
    public function returnedByUser(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'returnedBy', 'userId');
    }
}
