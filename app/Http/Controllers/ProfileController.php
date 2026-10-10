<?php

namespace App\Http\Controllers;

use App\Enums\StaffStatus;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(): Response
    {
        // No props: Profile/Edit reads the signed-in user from the shared auth.user, which is
        // where the two Breeze keys this method used to send (mustVerifyEmail, status) ended up
        // anyway — the trimmed page never named them. Staff accounts are created by an
        // administrator and there is no verification link to acknowledge.
        return Inertia::render('Profile/Edit');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated())->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Deactivate the user's account.
     *
     * Staff accounts are never hard-deleted (other tables reference staffusers
     * via foreign keys); disabling is done through the status field instead.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        $user->update(['status' => StaffStatus::Inactive]);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
