<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Pending = 'pending';
    case Evaluated = 'evaluated';
    case Assessed = 'assessed';
    case Paid = 'paid';
    case ReturnedToEvaluation = 'returnedToEvaluation';
    case Enrolled = 'enrolled';
    case Dropped = 'dropped';
}
