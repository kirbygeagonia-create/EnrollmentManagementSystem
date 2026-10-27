<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Pending = 'pending';
    case Evaluated = 'evaluated';
    case Assessed = 'assessed';
    case Paid = 'paid';
    case ReturnedToEvaluation = 'returnedToEvaluation';
    /**
     * Sent back to the Admission office, which holds no box on this record's workflow form
     * (development prompt §6.4, gap G-10). Distinct from `returnedToEvaluation` on purpose: a record
     * parked at a desk with nothing to sign must not read as though the department simply had not
     * evaluated it yet, and the queue that shows it is the Admission one, not Evaluation's.
     */
    case ReturnedToAdmission = 'returnedToAdmission';
    case Enrolled = 'enrolled';
    case Dropped = 'dropped';
}
