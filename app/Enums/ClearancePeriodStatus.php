<?php

namespace App\Enums;

enum ClearancePeriodStatus: string
{
    case Open = 'open'; case Closed = 'closed'; case Extended = 'extended';
}
