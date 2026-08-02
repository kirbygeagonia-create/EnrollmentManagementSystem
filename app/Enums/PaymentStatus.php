<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Paid = 'paid'; case Partial = 'partial'; case Pending = 'pending';
}
