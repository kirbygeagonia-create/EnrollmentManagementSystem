<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Submitted = 'submitted'; case Verified = 'verified'; case Rejected = 'rejected'; case Incomplete = 'incomplete'; case Pending = 'pending';
}
