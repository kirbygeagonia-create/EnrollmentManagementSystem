<?php

namespace App\Enums;

enum WorkflowStatus: string
{
    case InProgress = 'inProgress';
    case Completed = 'completed';
    case Lost = 'lost';
}
