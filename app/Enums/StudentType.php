<?php

namespace App\Enums;

enum StudentType: string
{
    case FirstYear = 'firstYear'; case Continuing = 'continuing'; case Transferee = 'transferee'; case Shifter = 'shifter';
}
