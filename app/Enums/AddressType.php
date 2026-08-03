<?php

namespace App\Enums;

enum AddressType: string
{
    case Home = 'home';
    case Current = 'current';
    case Permanent = 'permanent';
}
