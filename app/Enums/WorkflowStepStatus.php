<?php

namespace App\Enums;

enum WorkflowStepStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Skipped = 'skipped';
}
