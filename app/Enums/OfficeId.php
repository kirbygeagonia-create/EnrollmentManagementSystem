<?php

namespace App\Enums;

/**
 * Canonical office IDs (audit §4.1).
 *
 * Policies and seeders previously compared `$user->officeId` against bare
 * integers (2, 11, 22, ...). These IDs come from seed/insert order, so raw
 * literals silently break — or grant the wrong office access — if offices
 * are ever re-seeded or renumbered. All authorization code must reference
 * these named cases instead of bare integers.
 */
enum OfficeId: int
{
    case Registrar = 1;
    case Accounting = 2;
    case Scholarship = 3;
    case Guidance = 4;
    case Blocking = 5;
    case Admission = 6;
    case Clinic = 11;
    case IdOffice = 22;
}
