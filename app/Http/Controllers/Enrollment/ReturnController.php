<?php

namespace App\Http\Controllers\Enrollment;

use App\Enums\ReturnCategory;
use App\Enums\ReturnDestination;
use App\Http\Controllers\Controller;
use App\Models\Enrollments;
use App\Policies\EnrollmentReturnPolicy;
use App\Services\ReturnRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The one return act development prompt §6.4 asks for (gap G-10, ruled by the owner 2026-10-26).
 *
 * One route, one right, three desks. Ruling 14 gave the Registrar a `returnToEvaluation` action of
 * its own with a single reason field; §6.4 asks every return to say who sent it, where it must go,
 * which category it is, what the receiving desk has to finish, and who and when — and it names
 * Admission, Evaluation and Registrar as offices that all return. Keeping the Registrar's private
 * version beside a shared one would leave two answers to "was this record sent back, and why", so
 * the old action is gone and this is the mechanism.
 *
 * The acting desk is not asked to name itself: it comes from the account, the same way the inbox
 * derives who must act. What it *is* asked for is the destination and the category, because those
 * are the two facts a queue cannot be rebuilt from without.
 */
class ReturnController extends Controller
{
    public function __construct(private readonly ReturnRecorder $returns) {}

    public function store(Request $request, Enrollments $enrollment): RedirectResponse
    {
        // The record-scoped ability, not the permission: `enrollment.return` is seated with three
        // desks at once, and the college boundary that decides whether this record is the acting
        // desk's is only consulted behind a name no permission can take (see the gate definition in
        // AuthServiceProvider). The refusal sentence is passed as the 403 reason so the desk that
        // lands on errors/403.blade.php is told which rule answered, not just that it was refused.
        $user = $request->user();

        abort_unless(
            $user->can('enrollment.returnRecord', $enrollment),
            403,
            EnrollmentReturnPolicy::refusalFor($user, $enrollment),
        );

        $validated = $request->validate([
            'destination' => ['required', 'in:'.implode(',', ReturnDestination::values())],
            'returnCategory' => ['required', 'in:'.implode(',', ReturnCategory::values())],
            'returnNote' => 'required|string|min:10|max:500',
            'expectedCorrection' => 'required|string|min:5|max:500',
        ]);

        $record = $this->returns->record(
            $enrollment,
            $request->user(),
            ReturnDestination::from($validated['destination']),
            ReturnCategory::from($validated['returnCategory']),
            $validated['returnNote'],
            $validated['expectedCorrection'],
        );

        return back()->with('success', "The record is returned to {$record->destination->label()}, ".
            "which owes: {$record->expectedCorrection}.");
    }
}
