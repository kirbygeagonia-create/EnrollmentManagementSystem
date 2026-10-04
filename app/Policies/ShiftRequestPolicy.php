<?php

namespace App\Policies;

use App\Enums\ShiftRequestStatus;
use App\Models\Shiftingrequests;
use App\Models\Staffusers;

/**
 * Who may act on a shift request, and at which moment (ruling 11, G-7).
 *
 * The flow has three hands and they are deliberately different hands, because the point
 * of routing a program change through paper signatures is that no single desk can move a
 * student between programs on its own:
 *
 *   Academic Department Evaluation files it  → shift.request.create
 *   the dean or program head endorses it     → shift.sign.department
 *   the Guidance Councillor decides it       → shift.grant   (the final call, either way)
 *
 * A desk that filed a request can also endorse it only if it independently holds the
 * endorsement right — the permission, not the role name, is what is checked, so a
 * department evaluator who is also a program head is trusted on the strength of the second
 * hat and the audit trail that says which one signed.
 */
class ShiftRequestPolicy
{
    private const ANY_RIGHT_IN_THE_FLOW = [
        'shift.request.create',
        'shift.sign.department',
        'shift.grant',
    ];

    /**
     * The people who read this screen are the three desks in the flow — and a desk outside
     * it has nothing to do here, which is why no broader view right opens it.
     */
    public function viewAny(Staffusers $user): bool
    {
        foreach (self::ANY_RIGHT_IN_THE_FLOW as $right) {
            if ($user->checkPermissionTo($right)) {
                return true;
            }
        }

        return false;
    }

    public function create(Staffusers $user): bool
    {
        return $user->checkPermissionTo('shift.request.create');
    }

    /**
     * The dean or program head signature. Endorsing an already-decided shift would rewrite
     * a closed paper, so the request has to still be waiting on this exact step.
     */
    public function endorse(Staffusers $user, Shiftingrequests $request): bool
    {
        if (! $user->checkPermissionTo('shift.sign.department')) {
            return false;
        }

        return $request->requestStatus === ShiftRequestStatus::Pending;
    }

    /**
     * The Guidance Councillor's signature — the final call, granting or refusing. Only an
     * endorsed request reaches it, so the department head cannot be skipped, and a decided
     * request cannot be decided twice.
     */
    public function decide(Staffusers $user, Shiftingrequests $request): bool
    {
        if (! $user->checkPermissionTo('shift.grant')) {
            return false;
        }

        return $request->requestStatus === ShiftRequestStatus::Endorsed;
    }
}
