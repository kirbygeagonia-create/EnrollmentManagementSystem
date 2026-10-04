<?php

namespace App\Enums;

/**
 * The shift request's own vocabulary (ruling 11, G-7).
 *
 * A shift is not an admission — a SEAIT student changing program is already enrolled and
 * is not an applicant (§11) — so it does not borrow `admissionStatus`. It also has three
 * distinct moments rather than one yes/no: the department files the student's declared
 * will to move, the dean or program head endorses it, and the Guidance Councillor's
 * signature is the final call either way.
 */
enum ShiftRequestStatus: string
{
    // Filed at Academic Department Evaluation; waiting for the department head.
    case Pending = 'pending';
    // Dean or program head signed; waiting for Guidance, which decides.
    case Endorsed = 'endorsed';
    // Guidance Councillor granted it: the receiving enrollment is issued.
    case Granted = 'granted';
    // Guidance Councillor refused it, with the reason on the record.
    case Rejected = 'rejected';
}
