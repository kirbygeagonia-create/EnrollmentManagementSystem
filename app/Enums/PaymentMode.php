<?php

namespace App\Enums;

enum PaymentMode: string
{
    case Cash = 'cash'; case Online = 'online';
}
