<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Pending = 'pending'; case Evaluated = 'evaluated'; case Assessed = 'assessed'; case Paid = 'paid'; case Enrolled = 'enrolled'; case Dropped = 'dropped';
}
