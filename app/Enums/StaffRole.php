<?php

namespace App\Enums;

enum StaffRole: string
{
    case Staff = 'staff'; case OfficeHead = 'officeHead'; case Dean = 'dean'; case ProgramHead = 'programHead'; case Admin = 'admin';
}
