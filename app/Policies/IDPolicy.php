<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\IdRequestStatus;
use App\Enums\IdValidationStatus;
use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Idrequests;
use App\Models\Staffusers;
use App\Models\Studentids;

class IDPolicy
{
    /**
     * Determine whether the user can view any ID requests.
     */
    public function viewAny(Staffusers $user): bool
    {
        return $user->hasPermissionTo('id.view');
    }

    /**
     * Determine whether the user can view the ID request.
     */
    public function view(Staffusers $user, Idrequests $request): bool
    {
        return $user->hasPermissionTo('id.view');
    }

    /**
     * Determine whether the user can create ID request.
     * Phase 8: ID request, photo, emergency contact, blood type
     * BR13/BR14: Workflow step 8 must be completed in order
     */
    public function create(Staffusers $user, Enrollments $enrollment): bool
    {
        if (! $user->hasPermissionTo('id.request.create')) {
            return false;
        }

        // Must be ID Office (officeId = 22)
        if ($user->officeId !== 22) {
            return false;
        }

        // Enrollment must be enrolled
        if ($enrollment->enrollmentStatus !== EnrollmentStatus::Enrolled) {
            return false;
        }

        // Check workflow step 8 (ID Office) is current
        $workflow = $enrollment->enrollmentworkflow;
        if (! $workflow || $workflow->currentStep !== 8) {
            return false;
        }

        // Check if ID request already exists
        $existing = Idrequests::where('enrollmentId', $enrollment->enrollmentId)->first();
        if ($existing) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can produce ID card.
     */
    public function produceCard(Staffusers $user, Idrequests $request): bool
    {
        if (! $user->hasPermissionTo('id.card.produce')) {
            return false;
        }

        // Must be ID Office
        if ($user->officeId !== 22) {
            return false;
        }

        // Request must be pending
        return $request->status === IdRequestStatus::Pending;
    }

    /**
     * Determine whether the user can validate ID (QR code).
     */
    public function validate(Staffusers $user, Studentids $id): bool
    {
        if (! $user->hasPermissionTo('id.validate')) {
            return false;
        }

        // Must be ID Office
        if ($user->officeId !== 22) {
            return false;
        }

        // ID must be pending validation
        return $id->validationStatus === IdValidationStatus::PendingValidation;
    }

    /**
     * Determine whether the user can release ID to student.
     */
    public function release(Staffusers $user, Studentids $id): bool
    {
        if (! $user->hasPermissionTo('id.release')) {
            return false;
        }

        // Must be ID Office
        if ($user->officeId !== 22) {
            return false;
        }

        // ID must be active
        return $id->validationStatus === IdValidationStatus::Active;
    }

    /**
     * Determine whether the user can sign workflow step.
     */
    public function signWorkflow(Staffusers $user, Enrollmentworkflow $workflow): bool
    {
        if (! $user->hasPermissionTo('id.sign')) {
            return false;
        }

        // Must be ID Office
        return $user->officeId === 22;
    }
}
