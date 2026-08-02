<?php

namespace App\Enums;

enum InstitutionType: string
{
    case Elementary = 'elementary'; case JuniorHigh = 'juniorHigh'; case SeniorHigh = 'seniorHigh'; case Vocational = 'vocational'; case College = 'college';
}
