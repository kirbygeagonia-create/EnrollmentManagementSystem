<?php

namespace App\Enums;

enum PassResult: string
{
    case Passed = 'passed';
    case Failed = 'failed';
}
