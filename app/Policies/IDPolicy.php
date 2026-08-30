<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\IdRequestStatus;
use App\Enums\IdValidationStatus;
use App\Enums\OfficeId;
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
     * BR13/BR14: Workflow step for ID Office (office 22) must be completed in order
     */
    public function create(Staffusers $user, Enrollments $enrollment): bool
    {
        if (! $user->hasPermissionTo('id.request.create')) {
            return false;
        }

        // Must be ID Office (officeId = 22)
        if ($user->officeId !== OfficeId::IdOffice->value) {
            return false;
        }

        // Enrollment must be enrolled
        if ($enrollment->enrollmentStatus !== EnrollmentStatus::Enrolled) {
            return false;
        }

        // Check workflow step for ID Office (office 22) is current
        $workflow = $enrollment->enrollmentworkflow;
        if (! $workflow || $workflow->workflowsteps()->where('stepStatus', 'pending')->orderBy('stepOrder')->first()?->officeId !== OfficeId::IdOffice->value) {
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
        if ($user->officeId !== OfficeId::IdOffice->value) {
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
        if ($user->officeId !== OfficeId::IdOffice->value) {
            return false;
        }

        // ID must be pending validation
        return $id->validationStatus === IdValidationStatus::PendingValidation;
    }

    /**
     * Alias for validate - used by explicit gate 'id.validateCard'.
     */
    public function validateCard(Staffusers $user, Studentids $id): bool
    {
        return $this->validate($user, $id);
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
        if ($user->officeId !== OfficeId::IdOffice->value) {
            return false;
        }

        // ID must be active
        return $id->validationStatus === IdValidationStatus::Active;
    }

    /**
     * Determine whether the user can reissue ID card.
     * Reissue allowed when status is cardProduced (reprint) or released (replacement).
     */
    public function reissue(Staffusers $user, Idrequests $idRequest): bool
    {
        if (! $user->hasPermissionTo('id.reissue')) {
            return false;
        }

        // Must be ID Office
        if ($user->officeId !== OfficeId::IdOffice->value) {
            return false;
        }

        // Only allow reissue from cardProduced (reprint) or released (replacement)
        return in_array($idRequest->status, [IdRequestStatus::CardProduced, IdRequestStatus::Released], true);
    }

    /**
     * Determine whether the user can cancel ID request.
     * Cancel allowed when status is pending or cardProduced.
     */
    public function cancel(Staffusers $user, Idrequests $idRequest): bool
    {
        if (! $user->hasPermissionTo('id.cancel')) {
            return false;
        }

        // Must be ID Office
        if ($user->officeId !== OfficeId::IdOffice->value) {
            return false;
        }

        // Only allow cancel from pending or cardProduced
        return in_array($idRequest->status, [IdRequestStatus::Pending, IdRequestStatus::CardProduced], true);
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
        return $user->officeId === OfficeId::IdOffice->value;
    }
}
