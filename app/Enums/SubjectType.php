<?php

namespace App\Enums;

enum SubjectType: string
{
    case Lecture = 'lecture'; case Lab = 'lab'; case Both = 'both';
}
