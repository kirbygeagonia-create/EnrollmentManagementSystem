<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Clearanceapprovals extends Model
{
    protected $table = 'clearanceapprovals';
    public $timestamps = false;
    protected $fillable = ['studentClearanceId', 'clearanceRequirementId', 'status', 'approvedBy', 'approvalDate', 'remarks'];

    protected function casts(): array
    {
        return [
            'status' => \App\Enums\ClearanceApprovalStatus::class,
            'approvalDate' => 'date',
        ];
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'approvedBy');
    }

    public function clearanceRequirement(): BelongsTo
    {
        return $this->belongsTo(Clearancerequirements::class, 'clearanceRequirementId');
    }

    public function studentClearance(): BelongsTo
    {
        return $this->belongsTo(Studentclearances::class, 'studentClearanceId');
    }
}
