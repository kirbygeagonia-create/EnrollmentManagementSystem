<?php

namespace App\Policies;

use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Support\CollegeScope;

/**
 * Who may send an enrollment back to another desk (development prompt §6.4, gap G-10).
 *
 * The return is one act held by three desks — Registrar, Department Evaluation and Admission — so
 * its boundary is not a question of office number the way `registrar.drop` is. Two facts decide it,
 * and the second is the reason this class exists at all: `enrollment.return` is a permission, and
 * asking the permission alone would let a college-bound department desk return another college's
 * record, because the right is deliberately seated with every department account.
 *
 * Ruling 12's college boundary (`CollegeScope`) is the only place that says which records a
 * department owns, so the return asks it. A desk belonging to no college — the Registrar,
 * Admission, Accounting — is bounded by nothing and may return any record it is shown.
 *
 * What a record's own state allows is NOT decided here. A return to a desk that has not signed, a
 * record already parked at its destination, and a dropped one are routing rules about the workflow,
 * and `ReturnRecorder::refusalFor()` is their only statement — the same predicate the screen reads
 * so it can offer no destination the save would refuse. This class answers only "is this person one
 * of the desks that returns records, and is this record theirs to return".
 */
class EnrollmentReturnPolicy
{
    public function returnRecord(Staffusers $user, Enrollments $enrollment): bool
    {
        return self::refusalFor($user, $enrollment) === null;
    }

    /**
     * Why this account may not return this record, or null when it may. The bool above is derived
     * from this method rather than written beside it, so a refusal the desk is told in words is the
     * same refusal the gate answered with.
     */
    public static function refusalFor(?Staffusers $user, Enrollments $enrollment): ?string
    {
        if ($user === null || ! $user->hasPermissionTo('enrollment.return')) {
            return 'Returning a record is held by the Registrar, Admission and the evaluating department desks; '
                .'this account does not hold that right.';
        }

        if (! CollegeScope::holds($user, $enrollment->course?->unitId)) {
            return "Program “{$enrollment->course?->courseCode}” belongs to another college; a department returns "
                .'its own college’s records.';
        }

        return null;
    }
}
