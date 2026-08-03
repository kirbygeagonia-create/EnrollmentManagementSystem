<?php

namespace App\Policies;

use App\Enums\ClearanceApprovalStatus;
use App\Enums\ClearanceOverallStatus;
use App\Enums\ClearancePeriodStatus;
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

        // Period must be open
        if ($period->periodStatus !== ClearancePeriodStatus::Open) {
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
        if ($user->officeId !== 1) {
            return false;
        }

        // Clearance must be pending and all approvals completed
        if ($clearance->overallStatus !== ClearanceOverallStatus::Pending) {
            return false;
        }

        $pendingApprovals = $clearance->clearanceapprovals()
            ->where('status', '!=', ClearanceApprovalStatus::Approved)
            ->where('status', '!=', ClearanceApprovalStatus::Waived)
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
     * Determine whether the user can process lost slip replacement (₱100).
     * BR33: Lost slip costs ₱100 at Accounting before reissue
     */
    public function replaceLostSlip(Staffusers $user, Students $student, Clearanceperiods $period): bool
    {
        if (! $user->hasPermissionTo('clearance.slip.replace')) {
            return false;
        }

        // Must be Accounting office (officeId = 2)
        if ($user->officeId !== 2) {
            return false;
        }

        $clearance = Studentclearances::where('studentId', $student->studentId)
            ->where('clearancePeriodId', $period->clearancePeriodId)
            ->first();

        return $clearance && $clearance->overallStatus === ClearanceOverallStatus::Incomplete;
    }
}
