<?php

namespace App\Enums;

enum ClinicRecordStatus: string
{
    case Pending = 'pending'; case Completed = 'completed';
}
