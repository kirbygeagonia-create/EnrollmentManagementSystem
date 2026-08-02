<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Workflowsteps extends Model
{
    protected $table = 'workflowsteps';
    public $timestamps = false;
    protected $fillable = ['workflowId', 'officeId', 'stepOrder', 'stepStatus', 'signedBy', 'signedDate'];

    protected function casts(): array
    {
        return [
            'stepStatus' => \App\Enums\WorkflowStepStatus::class,
            'signedDate' => 'datetime',
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Offices::class, 'officeId');
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'signedBy');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Enrollmentworkflow::class, 'workflowId');
    }
}
