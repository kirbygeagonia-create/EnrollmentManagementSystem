<?php

namespace App\Enums;

enum AdmissionStatus: string
{
    /**
     * Intake saved before it is complete (§7.1 of the development prompt). A draft is not in any desk's queue:
     * every downstream filter asks for pending or approved, and AdmissionPolicy's five rights all require
     * Pending, so a draft cannot be examined, approved, rejected or verified until it is submitted.
     */
    case Draft = 'draft';

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
