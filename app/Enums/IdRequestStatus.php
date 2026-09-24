<?php

namespace App\Enums;

enum IdRequestStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';
    case Released = 'released';
    // No longer set by any action (card-making removed), but kept so
    // historical cancelled rows still hydrate.
    case Cancelled = 'cancelled';
}
