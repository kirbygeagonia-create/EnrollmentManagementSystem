<?php

namespace App\Enums;

enum IdValidationStatus: string
{
    case PendingValidation = 'pendingValidation';
    case Active = 'active';
    case Lost = 'lost';
    case Replaced = 'replaced';
}
