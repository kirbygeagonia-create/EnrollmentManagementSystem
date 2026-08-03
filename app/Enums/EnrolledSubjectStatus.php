<?php

namespace App\Enums;

enum EnrolledSubjectStatus: string
{
    case Proposed = 'proposed';
    case Confirmed = 'confirmed';
    case Dropped = 'dropped';
}
