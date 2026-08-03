<?php

namespace App\Enums;

enum ClearanceOverallStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Waived = 'waived';
    case Incomplete = 'incomplete';
}
