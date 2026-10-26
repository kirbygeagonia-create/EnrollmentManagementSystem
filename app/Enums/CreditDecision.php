<?php

namespace App\Enums;

enum CreditDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
}
