<?php

namespace App\Policies;

use App\Enums\ClearanceApprovalStatus;
use App\Enums\ClearanceOverallStatus;
use App\Enums\OfficeId;
use App\Models\Clearanceapprovals;
use App\Models\Clearanceperiods;
use App\Models\Staffusers;
use App\Models\Studentclearances;
use App\Models\Students;

class ClearancePolicy
{
    /**
     * Determine whether the user can view any clearances.
     */
    public function viewAny(Staffusers $user): bool
    {
        return $user->hasPermissionTo('clearance.view');
    }

    /**
     * Determine whether the user can view the clearance.
     */
    public function view(Staffusers $user, Studentclearances $clearance): bool
    {
        return $user->hasPermissionTo('clearance.view');
    }

    /**
     * Determine whether the user can open/close clearance periods.
     */
    public function managePeriods(Staffusers $user): bool
    {
        return $user->hasPermissionTo('clearance.periods.manage');
    }

    /**
     * Determine whether the user can generate clearance slips.
     * BR33: One free slip per student per period
     */
    public function generateSlip(Staffusers $user, Students $student, Clearanceperiods $period): bool
    {
        if (! $user->hasPermissionTo('clearance.slip.generate')) {
            return false;
        }

        // The window must be accepting slips: open, or extended past its end date.
        if (! $period->isAccepting()) {
            return false;
        }

        // Check if student already has a clearance for this period
        $existing = Studentclearances::where('studentId', $student->studentId)
            ->where('clearancePeriodId', $period->clearancePeriodId)
            ->first();

        // Allow if no existing or if replacing lost slip (with payment)
        return ! $existing || $existing->overallStatus === ClearanceOverallStatus::Incomplete;
    }

    /**
     * Determine whether the user can record desk receipt (receivedBy/receivedDate).
     * BR34: Registrar desk receipt recorded when completed slip submitted
     */
    public function recordDeskReceipt(Staffusers $user, Studentclearances $clearance): bool
    {
        if (! $user->hasPermissionTo('clearance.receipt.record')) {
            return false;
        }

        // Only Registrar desk staff (officeId = 1) can record receipt
        if ($user->officeId !== OfficeId::Registrar->value) {
            return false;
        }

        // Clearance must not already be received and must not be rejected or incomplete
        if ($clearance->receivedDate !== null) {
            return false;
        }

        if (in_array($clearance->overallStatus, [ClearanceOverallStatus::Rejected, ClearanceOverallStatus::Incomplete])) {
            return false;
        }

        $pendingApprovals = $clearance->clearanceapprovals()
            ->where('status', '!=', ClearanceApprovalStatus::Approved->value)
            ->where('status', '!=', ClearanceApprovalStatus::Waived->value)
            ->count();

        return $pendingApprovals === 0;
    }

    /**
     * Determine whether the user can approve/waive clearance requirements.
     * Office-scoped: only the responsible office can approve their requirement
     */
    public function approveRequirement(Staffusers $user, Clearanceapprovals $approval): bool
    {
        if (! $user->hasPermissionTo('clearance.approve')) {
            return false;
        }

        // Office-scoped: user's office must match the requirement's office
        $requirement = $approval->clearanceRequirement;
        if ($user->officeId !== $requirement->officeId) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can process a lost slip replacement.
     * BR33: a replacement is paid for at Accounting before the slip is reissued — the
     * amount is the Reference Data replacement fee type, not a figure fixed here.
     */
    public function replaceLostSlip(Staffusers $user, Students $student, Clearanceperiods $period): bool
    {
        if (! $user->hasPermissionTo('clearance.slip.replace')) {
            return false;
        }

        // Ruling 8 puts this act with Accounting and with Clearance, and the counter that
        // takes a lost slip is the Registrar's since ruling 12 folded the legacy Clearance
        // office into it. The action files the replacement OR as it reissues, so either
        // office is recording the fee rather than relying on someone else to have done it.
        if (! in_array($user->officeId, [OfficeId::Accounting->value, OfficeId::Registrar->value], true)) {
            return false;
        }

        $clearance = Studentclearances::where('studentId', $student->studentId)
            ->where('clearancePeriodId', $period->clearancePeriodId)
            ->first();

        return $clearance && in_array($clearance->overallStatus, [
            ClearanceOverallStatus::Incomplete,
            ClearanceOverallStatus::Pending,
        ]);
    }
}
