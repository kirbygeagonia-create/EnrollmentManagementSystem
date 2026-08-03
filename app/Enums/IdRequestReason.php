<?php

namespace App\Enums;

enum IdRequestReason: string
{
    case NewStudent = 'newStudent';
    case Shifted = 'shifted';
    case Lost = 'lost';
    case Replaced = 'replaced';
    case Renewed = 'renewed';
}
