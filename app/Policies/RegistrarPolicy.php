<?php

namespace App\Policies;

use App\Enums\ClearanceOverallStatus;
use App\Enums\ClearancePeriodStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\StudentType;
use App\Models\Clearanceperiods;
use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Models\Studentclearances;

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

        // Check clearance for continuing students (BR8)
        if (in_array($enrollment->studentType->value, ['continuing', 'shifter'])) {
            $currentPeriod = Clearanceperiods::where('periodStatus', ClearancePeriodStatus::Open)->first();
            if ($currentPeriod) {
                $clearance = Studentclearances::where('studentId', $enrollment->studentId)
                    ->where('clearancePeriodId', $currentPeriod->clearancePeriodId)
                    ->first();

                if (! $clearance || $clearance->overallStatus !== ClearanceOverallStatus::Approved) {
                    return false;
                }

                // Check desk receipt recorded (BR34)
                if (! $clearance->receivedBy || ! $clearance->receivedDate) {
                    return false;
                }
            }
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
     * Determine whether the user can print enrollment certificate.
     */
    public function printCertificate(Staffusers $user, Enrollments $enrollment): bool
    {
        if (! $user->hasPermissionTo('print.certificate')) {
            return false;
        }

        // Enrollment must be enrolled
        return $enrollment->enrollmentStatus === EnrollmentStatus::Enrolled;
    }

    /**
     * Determine whether the user can print class cards.
     */
    public function printClassCards(Staffusers $user, Enrollments $enrollment): bool
    {
        if (! $user->hasPermissionTo('print.classCard')) {
            return false;
        }

        // Enrollment must be enrolled
        return $enrollment->enrollmentStatus === EnrollmentStatus::Enrolled;
    }

    /**
     * Determine whether the user can print subject load.
     */
    public function printSubjectLoad(Staffusers $user, Enrollments $enrollment): bool
    {
        if (! $user->hasPermissionTo('print.subjectLoad')) {
            return false;
        }

        // Enrollment must be enrolled
        return $enrollment->enrollmentStatus === EnrollmentStatus::Enrolled;
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
