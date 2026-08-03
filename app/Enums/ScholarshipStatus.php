<?php

namespace App\Enums;

enum ScholarshipStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
