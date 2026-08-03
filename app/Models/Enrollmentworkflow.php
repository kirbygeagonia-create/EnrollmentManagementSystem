<?php

namespace App\Models;

use App\Enums\WorkflowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollmentworkflow extends Model
{
    protected $table = 'enrollmentworkflow';

    protected $primaryKey = 'workflowId';

    public $timestamps = false;

    protected $fillable = ['enrollmentId', 'currentStep', 'workflowStatus'];

    protected function casts(): array
    {
        return [
            'workflowStatus' => WorkflowStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Enrollments, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'enrollmentId');
    }

    /**
     * @return HasMany<Workflowsteps>
     */
    /**
     * @return HasMany<Workflowsteps, $this>
     */
    public function workflowsteps(): HasMany
    {
        return $this->hasMany(Workflowsteps::class, 'workflowId');
    }
}
