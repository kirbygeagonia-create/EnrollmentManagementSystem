<?php

namespace App\Enums;

enum LevelCompleted: string
{
    case Elementary = 'elementary';
    case JuniorHigh = 'juniorHigh';
    case SeniorHigh = 'seniorHigh';
    case Vocational = 'vocational';
    case College = 'college';
}
