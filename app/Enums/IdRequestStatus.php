<?php

namespace App\Enums;

enum IdRequestStatus: string
{
    case Pending = 'pending'; case CardProduced = 'cardProduced';
}
