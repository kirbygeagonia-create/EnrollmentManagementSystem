<?php

namespace App\Support;

use App\Enums\OfficeId;

/**
 * Placeholder wording for the clearance requirement lines (D-6).
 *
 * `clearancerequirements` used to store nothing but an officeId, so the printed slip
 * could only repeat the office name where the obligation itself should read, and a
 * signer was signing against no text at all. The table now carries the text and
 * Admin → Reference Data maintains it.
 *
 * The wording below is NOT approved institutional policy. It exists so the demo slip
 * reads as a real form; every line is a proposal pending the Registrar's own list of
 * clearance requirements (§28, D-6).
 *
 * These keys are also the offices the checklist is seeded for, so a line cannot exist
 * without words behind it. Legacy office 8 ("Clearance") is absent because ruling 12
 * folded that department into the Registrar (D-1): it owned no obligation of its own, and
 * its checklist line had no text to sign against.
 */
class ProvisionalClearanceRequirements
{
    /**
     * @var array<int, string> officeId => provisional requirement text
     */
    public const NAMES = [
        OfficeId::Registrar->value => 'No unreturned Registrar documents, credentials, or ID',
        OfficeId::Accounting->value => 'No unsettled fees, assessments, or accounting obligations',
        OfficeId::Scholarship->value => 'Scholarship records returned and grant conditions met',
        OfficeId::Guidance->value => 'Guidance records complete, no pending counseling clearance',
        OfficeId::Blocking->value => 'No unresolved section or subject blocking',
        OfficeId::Admission->value => 'Admission requirements on file and verified',
        OfficeId::Academic->value => 'No incomplete grades or make-up requirements with the department',
        OfficeId::Clinic->value => 'Medical and dental examination on file',
        OfficeId::IdOffice->value => 'No unclaimed, damaged, or lost school ID',
    ];
}
