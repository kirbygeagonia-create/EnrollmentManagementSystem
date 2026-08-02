<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollmentworkflow extends Model
{
    protected $table = 'enrollmentworkflow';
    public $timestamps = false;
    protected $fillable = ['enrollmentId', 'currentStep', 'workflowStatus'];

    protected function casts(): array
    {
        return [
            'workflowStatus' => \App\Enums\WorkflowStatus::class,
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'enrollmentId');
    }

    public function workflowsteps(): HasMany
    {
        return $this->hasMany(Workflowsteps::class, 'workflowId');
    }
}
