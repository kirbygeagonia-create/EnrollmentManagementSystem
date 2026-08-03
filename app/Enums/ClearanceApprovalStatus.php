<?php

namespace App\Enums;

enum ClearanceApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Waived = 'waived';
}
