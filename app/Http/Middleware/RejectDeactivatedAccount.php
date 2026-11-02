<?php

namespace App\Http\Middleware;

use App\Enums\StaffStatus;
use App\Models\Staffusers;
use App\Support\DenialReason;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deactivated account stops at its next request, not at its next login (§28.3 G-42, prompt §9's user accounts).
 *
 * The prompt asks that deactivated staff "must not be able to start a new session" and that access checks "must not
 * depend only on whether their navigation items are hidden". The login refusal covers the first half. It did not
 * cover an account that was active when it signed in and was switched off while the tab stayed open: nothing else in
 * the tree reads `staffusers.status` — measured by grepping every policy, the auth path and the providers for the
 * word — so that desk kept acting for as long as its session cookie lived, which is exactly the navigation-only
 * enforcement the prompt refuses, one layer in.
 *
 * The act is a stop, not a punishment: the request is not carried out, the session is invalidated and the token
 * regenerated so no later request from the same browser acts either, and the screen says who to ask. The account's
 * own history is untouched, because deactivation is the ruling's remedy and nothing here deletes a record.
 *
 * Guests pass through — `$request->user()` is null and there is no status to ask — and so does any authenticatable
 * that is not a staff account, because this application's desks are staff. The check is on `status`, never on
 * `role`: prompt §9 asks that the two be kept distinct, and an account may hold every permission and still be
 * closed.
 */
class RejectDeactivatedAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Staffusers || $user->status === StaffStatus::Active) {
            return $next($request);
        }

        // The same sentence the login screen prints, from the one place refusal wording lives: a person refused at
        // the door and refused differently inside should not have to learn two explanations of one state. The key is
        // `username` because that is the field the sign-in form renders, so the reason appears under the box rather
        // than as a page of prose, and the desk's own input travels with it so nothing typed is discarded.
        $reason = DenialReason::deactivated();

        Auth::guard('web')->logout();
        // The session is a boundary this middleware cannot assume: a health or stateless route reaches the web group
        // with no session store attached, and `session()` there throws rather than refuses.
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $reason], 403);
        }

        return redirect()->route('login')
            ->withErrors(['username' => $reason])
            ->withInput($request->only('username'));
    }
}
