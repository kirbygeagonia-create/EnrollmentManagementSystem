<?php

namespace App\Enums;

enum IdRequestStatus: string
{
    case Pending = 'pending';
    case CardProduced = 'cardProduced';
    case Validated = 'validated';
    case Released = 'released';
    case ReissuePending = 'reissuePending';
    case Cancelled = 'cancelled';
}
