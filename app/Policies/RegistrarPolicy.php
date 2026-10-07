<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\StudentType;
use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Support\EnrollmentReadiness;

class RegistrarPolicy
{
    /**
     * Determine whether the user can view any registrar approvals.
     */
    public function viewAny(Staffusers $user): bool
    {
        return $user->hasPermissionTo('enrollment.approve');
    }

    /**
     * Determine whether the user can view the enrollment for approval.
     */
    public function view(Staffusers $user, Enrollments $enrollment): bool
    {
        return $user->hasPermissionTo('enrollment.approve');
    }

    /**
     * Determine whether the user can validate enrollment prerequisites.
     * BR12: Enrollment cannot be marked enrolled without passing through all prior phases
     * BR8: Continuing students must have cleared obligations (clearance slip mandatory at Phase 5)
     */
    public function validatePrerequisites(Staffusers $user, Enrollments $enrollment): bool
    {
        if (! $user->hasPermissionTo('enrollment.approve')) {
            return false;
        }

        // Must be Registrar office (officeId = 1)
        if ($user->officeId !== OfficeId::Registrar->value) {
            return false;
        }

        // Enrollment must be in assessed or paid status
        if (! in_array($enrollment->enrollmentStatus->value, ['assessed', 'paid'])) {
            return false;
        }

        // Check payment completed
        $assessment = $enrollment->studentassessments;
        if (! $assessment || $assessment->remainingBalance > 0) {
            return false;
        }

        // Check evaluation signed
        if (! $enrollment->evaluatedBy) {
            return false;
        }

        // BR8, and ruling 4: a continuing or shifter student must hold a clearance that
        // was approved and received in the window now accepting slips. The absence of a
        // window is no longer read as a pass — it is the reason this gate used to be
        // quiet, and the one method the checklist reads decides it here too.
        if (! EnrollmentReadiness::clearanceVerdict($enrollment)['passed']) {
            return false;
        }

        // Concerns #28/#32, enforced here as well as displayed on the checklist:
        // the applicant's own required documents must be verified, and no subject
        // on the confirmed load may sit behind an unmet prerequisite. Both were
        // checked only at their own desks, so a record could reach this one
        // carrying an unverified certificate or an unentitled load and still be
        // approvable.
        if (! EnrollmentReadiness::documentsVerified($enrollment)) {
            return false;
        }

        if (! EnrollmentReadiness::prerequisitesMet($enrollment)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can approve enrollment (mark as enrolled).
     * BR31: enrollmentType derived from studentType (new/old)
     */
    public function approve(Staffusers $user, Enrollments $enrollment): bool
    {
        if (! $this->validatePrerequisites($user, $enrollment)) {
            return false;
        }

        // Must have permission to approve
        return $user->hasPermissionTo('enrollment.approve');
    }

    /**
     * Determine whether the user can drop an enrollment (ruling 17).
     *
     * This desk alone, and only from the point it is answerable for the record: assessed,
     * paid or enrolled. A drop erases what the other desks signed, which is precisely why
     * the office that holds the final signature has to be the one that takes the blame
     * for undoing it — so the base Staff role and the cross-office OfficeHead are out,
     * and `enrollment.drop` belongs to the Registrar approver.
     *
     * A pending or evaluated load is not this desk's to drop: nothing has reached
     * Accounting or this counter yet, and the department still holds it.
     */
    public function drop(Staffusers $user, Enrollments $enrollment): bool
    {
        if (! $user->hasPermissionTo('enrollment.drop')) {
            return false;
        }

        if ($user->officeId !== OfficeId::Registrar->value) {
            return false;
        }

        return in_array($enrollment->enrollmentStatus->value, [
            EnrollmentStatus::Assessed->value,
            EnrollmentStatus::Paid->value,
            EnrollmentStatus::Enrolled->value,
        ], true);
    }

    /**
     * The print boundary, drawn the same way for all four Registrar papers (C-2's sibling in
     * this model of authority: the right names the paper, the office names who may issue it).
     *
     * Before 2026-10-06 no print gate asked which office the requester ran, so a head of the
     * Clinic printed a Certificate of Enrollment as readily as the Registrar did — measured on
     * live `ems`, not inferred from the permission list. The owner ruled the four rights
     * office-scoped, which is what the other desk acts already do.
     *
     * A teacher may still print the two papers a teacher actually hands out — the class card and
     * the subject load — but only for a program their own academic unit owns, which is the same
     * unit test the workflow uses for an academic signer. A unit-less account gets no extra
     * reach from it: the demo's instructor rows carry no unitId, so on that dataset the
     * Registrar is the only desk printing until someone is assigned.
     */
    private function mayPrint(Staffusers $user, Enrollments $enrollment, string $permission, bool $owningUnitToo = false): bool
    {
        if (! $user->hasPermissionTo($permission)) {
            return false;
        }

        // Every one of the four documents certifies a finished transaction: a pending or
        // returned record has no published load and no approval to print.
        if ($enrollment->enrollmentStatus !== EnrollmentStatus::Enrolled) {
            return false;
        }

        if ($user->officeId === OfficeId::Registrar->value) {
            return true;
        }

        if ($user->hasRole('SysAdmin')) {
            return true;
        }

        return $owningUnitToo
            && $user->unitId !== null
            && $enrollment->course?->unitId !== null
            && (int) $user->unitId === (int) $enrollment->course->unitId;
    }

    /**
     * Determine whether the user can print enrollment certificate.
     */
    public function printCertificate(Staffusers $user, Enrollments $enrollment): bool
    {
        return $this->mayPrint($user, $enrollment, 'print.certificate');
    }

    /**
     * Determine whether the user can print class cards.
     */
    public function printClassCards(Staffusers $user, Enrollments $enrollment): bool
    {
        return $this->mayPrint($user, $enrollment, 'print.classCard', true);
    }

    /**
     * Determine whether the user can print subject load.
     */
    public function printSubjectLoad(Staffusers $user, Enrollments $enrollment): bool
    {
        return $this->mayPrint($user, $enrollment, 'print.subjectLoad', true);
    }

    /**
     * Determine whether the user can print the enrollment form itself.
     *
     * The other three prints describe the enrollment (a certificate about it, a card per
     * subject, a load list). This is the record as issued, signatures and all, so it is the most
     * Registrar-authored paper of the four and is scoped to that desk.
     */
    public function printEnrollmentForm(Staffusers $user, Enrollments $enrollment): bool
    {
        return $this->mayPrint($user, $enrollment, 'print.enrollmentForm');
    }

    /**
     * Determine whether the user can record/update student data (new vs old).
     * BR31: firstYear/transferee = new (record), continuing/shifter = old (update)
     */
    public function recordStudentData(Staffusers $user, Enrollments $enrollment): bool
    {
        if (! $user->hasPermissionTo('enrollment.studentdata.record')) {
            return false;
        }

        // Must be Registrar office
        return $user->officeId === OfficeId::Registrar->value;
    }
}
