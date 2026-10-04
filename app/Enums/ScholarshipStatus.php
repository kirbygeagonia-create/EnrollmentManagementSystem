<?php

namespace App\Enums;

/**
 * A grant the school is standing behind, or one it has taken back.
 *
 * `revoked` and `expired` were dead values until ruling 15 gave the Scholarship desk the
 * two acts that write them: withdrawing a grant that was mistaken or forfeited, and letting
 * one run out. Either way the assessment is recomputed and the student owes again — the
 * money consequence is what makes the state worth recording at all.
 */
enum ScholarshipStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
