<?php

namespace App\Enums;

enum AppliesTo: string
{
    case FirstYear = 'firstYear'; case Transferee = 'transferee'; case Shifter = 'shifter'; case Continuing = 'continuing'; case All = 'all';
}
