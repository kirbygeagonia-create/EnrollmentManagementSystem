<?php

namespace App\Enums;

enum GuardianRelationship: string
{
    case Mother = 'mother'; case Father = 'father'; case Guardian = 'guardian'; case Other = 'other';
}
