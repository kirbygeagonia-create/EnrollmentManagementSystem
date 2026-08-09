<?php

namespace App\Models;

use App\Enums\ClearanceApprovalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Clearanceapprovals extends Model
{
    protected $table = 'clearanceapprovals';

    protected $primaryKey = 'clearanceApprovalId';

    public $timestamps = false;

    protected $fillable = ['studentClearanceId', 'clearanceRequirementId', 'status', 'approvedBy', 'approvalDate', 'remarks'];

    protected function casts(): array
    {
        return [
            'status' => ClearanceApprovalStatus::class,
            'approvalDate' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'approvedBy');
    }

    /**
     * @return BelongsTo<Clearancerequirements, $this>
     */
    public function clearanceRequirement(): BelongsTo
    {
        return $this->belongsTo(Clearancerequirements::class, 'clearanceRequirementId');
    }

    /**
     * Alias used by clearance pages (approvals.requirement.office).
     *
     * @return BelongsTo<Clearancerequirements, $this>
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Clearancerequirements::class, 'clearanceRequirementId');
    }

    /**
     * @return BelongsTo<Studentclearances, $this>
     */
    public function studentClearance(): BelongsTo
    {
        return $this->belongsTo(Studentclearances::class, 'studentClearanceId');
    }
}
