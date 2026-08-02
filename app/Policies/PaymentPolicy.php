<?php

namespace App\Policies;

use App\Models\Staffusers;
use App\Models\Payments;
use App\Models\Studentassessments;
use App\Models\Enrollments;
use App\Enums\PaymentStatus;
use App\Enums\EnrollmentStatus;

class PaymentPolicy
{
    /**
     * Determine whether the user can view any payments.
     */
    public function viewAny(Staffusers $user): bool
    {
        return $user->hasPermissionTo('payment.view');
    }

    /**
     * Determine whether the user can view the payment.
     */
    public function view(Staffusers $user, Payments $payment): bool
    {
        return $user->hasPermissionTo('payment.view');
    }

    /**
     * Determine whether the user can record payment.
     * BR11: Assessment must exist before payment
     * BR5: OR number must be unique
     */
    public function record(Staffusers $user, Studentassessments $assessment): bool
    {
        if (!$user->hasPermissionTo('payment.record')) {
            return false;
        }

        // Must be Accounting office (officeId = 2)
        if ($user->officeId !== 2) {
            return false;
        }

        // Assessment must exist and have remaining balance
        if ($assessment->remainingBalance <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can void payment.
     */
    public function void(Staffusers $user, Payments $payment): bool
    {
        if (!$user->hasPermissionTo('payment.void')) {
            return false;
        }

        // Must be Accounting office (officeId = 2)
        if ($user->officeId !== 2) {
            return false;
        }

        // Payment must not already be voided
        return $payment->paymentStatus !== PaymentStatus::Pending;
    }

    /**
     * Determine whether the user can generate daily collection report.
     */
    public function dailyReport(Staffusers $user): bool
    {
        return $user->hasPermissionTo('payment.report.daily')
            && $user->officeId === 2;
    }
}