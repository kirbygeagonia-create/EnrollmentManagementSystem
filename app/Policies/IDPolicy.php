<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\IdRequestStatus;
use App\Enums\OfficeId;
use App\Enums\WorkflowStepStatus;
use App\Models\Enrollments;
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
        if (! $workflow || $workflow->workflowsteps()->where('stepStatus', WorkflowStepStatus::Pending->value)->orderBy('stepOrder')->first()?->officeId !== OfficeId::IdOffice->value) {
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
     * Determine whether the user can record a mismatch against the request.
     *
     * The desk's only other outcome was "validated", so an officer who found the
     * face at the window did not match the file had no way to say so on the
     * record. A remark is allowed while the request is still open and not after:
     * validation closes the request, and annotating a closed one would suggest
     * the note was seen before the signature.
     */
    public function remark(Staffusers $user, Idrequests $request): bool
    {
        if (! $user->hasPermissionTo('id.validate')) {
            return false;
        }

        if ($user->officeId !== OfficeId::IdOffice->value) {
            return false;
        }

        return $request->status === IdRequestStatus::Pending;
    }

    /**
     * Determine whether the user can validate the ID request.
     * Strict validation: the request must carry the captured face photo.
     *
     * Validation is terminal and it is the ID box's signature — `validate()` stamps the
     * request and signs the workflow step in one transaction — so it needs both rights, the
     * counter's `id.validate` (which ruling 7 left with OfficeHead) and the signature's
     * `id.sign` (which ruling 7 moved to IdOfficer). See the same reasoning in
     * ClinicPolicy::record and in RegistrarPolicy's treatment of enrollment.approve.
     */
    public function validate(Staffusers $user, Idrequests $request): bool
    {
        if (! $user->hasPermissionTo('id.validate')) {
            return false;
        }

        if (! $user->hasPermissionTo('id.sign')) {
            return false;
        }

        // Must be ID Office
        if ($user->officeId !== OfficeId::IdOffice->value) {
            return false;
        }

        return $request->status === IdRequestStatus::Pending
            && filled($request->cardPhotoPath);
    }
}
