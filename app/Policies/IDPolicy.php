<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\IdRequestStatus;
use App\Enums\OfficeId;
use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Idrequests;
use App\Models\Staffusers;

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
     * Determine whether the user can attach the captured face photo to the
     * ID request (validation prep).
     */
    public function attachPhoto(Staffusers $user, Idrequests $request): bool
    {
        if (! $user->hasPermissionTo('id.validate')) {
            return false;
        }

        // Must be ID Office
        if ($user->officeId !== OfficeId::IdOffice->value) {
            return false;
        }

        return $request->status === IdRequestStatus::Pending;
    }

    /**
     * Determine whether the user can validate the ID request.
     * Strict validation: the request must carry the captured face photo.
     */
    public function validate(Staffusers $user, Idrequests $request): bool
    {
        if (! $user->hasPermissionTo('id.validate')) {
            return false;
        }

        // Must be ID Office
        if ($user->officeId !== OfficeId::IdOffice->value) {
            return false;
        }

        return $request->status === IdRequestStatus::Pending
            && filled($request->cardPhotoPath);
    }

    /**
     * Determine whether the user can release the physical ID card to the
     * student (cards are printed off-system; release records the handover).
     */
    public function release(Staffusers $user, Idrequests $request): bool
    {
        if (! $user->hasPermissionTo('id.release')) {
            return false;
        }

        // Must be ID Office
        if ($user->officeId !== OfficeId::IdOffice->value) {
            return false;
        }

        return $request->status === IdRequestStatus::Validated;
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
