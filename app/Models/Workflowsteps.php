<?php

namespace App\Models;

use App\Enums\WorkflowStepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Workflowsteps extends Model
{
    protected $table = 'workflowsteps';

    protected $primaryKey = 'workflowStepId';

    public $timestamps = false;

    protected $fillable = ['workflowId', 'officeId', 'stepOrder', 'stepStatus', 'signedBy', 'signedDate'];

    protected function casts(): array
    {
        return [
            'stepStatus' => WorkflowStepStatus::class,
            'signedDate' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Offices, $this>
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Offices::class, 'officeId');
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'signedBy');
    }

    /**
     * @return BelongsTo<Enrollmentworkflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Enrollmentworkflow::class, 'workflowId');
    }
}
