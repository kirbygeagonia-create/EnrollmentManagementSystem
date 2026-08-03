<?php

namespace App\Events;

use App\Models\Enrollmentworkflow;
use App\Models\Staffusers;
use App\Models\Workflowsteps;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowStepSigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Enrollmentworkflow $workflow,
        public Workflowsteps $step,
        public Staffusers $signedBy
    ) {}
}
