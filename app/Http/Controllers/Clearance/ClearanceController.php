<?php

namespace App\Http\Controllers\Clearance;

use App\Enums\ClearanceApprovalStatus;
use App\Enums\ClearanceOverallStatus;
use App\Enums\ClearancePeriodStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Clearanceapprovals;
use App\Models\Clearanceperiods;
use App\Models\Clearancerequirements;
use App\Models\Feetypes;
use App\Models\Payments;
use App\Models\Studentclearances;
use App\Models\Students;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ClearanceController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display clearance management screen.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Studentclearances::class);

        $periods = Clearanceperiods::with('term.academicYear')->get();

        $query = Studentclearances::with(['student', 'clearancePeriod.term.academicYear', 'approvals.requirement.office', 'receivedBy'])
            ->when($request->periodId, fn ($q, $id) => $q->where('clearancePeriodId', $id))
            ->when($request->status, fn ($q, $status) => $q->where('overallStatus', $status))
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->orderByDesc('studentClearanceId');

        $clearances = $query->paginate(20)->withQueryString();

        return Inertia::render('Clearance/Index', [
            'clearances' => $clearances,
            'periods' => $periods,
            'filters' => $request->only(['periodId', 'status', 'search']),
        ]);
    }

    /**
     * Manage clearance periods (open/close).
     */
    public function periods(Request $request): Response
    {
        $this->authorize('managePeriods', Studentclearances::class);

        $periods = Clearanceperiods::with('term.academicYear')->orderByDesc('clearancePeriodId')->get();

        return Inertia::render('Clearance/Periods', [
            'periods' => $periods,
        ]);
    }

    /**
     * Store new clearance period.
     */
    public function storePeriod(Request $request): RedirectResponse
    {
        $this->authorize('managePeriods', Studentclearances::class);

        $validated = $request->validate([
            'termId' => 'required|exists:academicterms,termId',
            'clearanceStartDate' => 'required|date',
            'clearanceEndDate' => 'required|date|after:clearanceStartDate',
            'periodStatus' => 'required|in:open,closed',
        ]);

        Clearanceperiods::create($validated);

        return back()->with('success', 'Clearance period created.');
    }

    /**
     * Update clearance period status.
     */
    public function updatePeriod(Request $request, Clearanceperiods $period): RedirectResponse
    {
        $this->authorize('managePeriods', Studentclearances::class);

        $period->update([
            'periodStatus' => $request->validate(['periodStatus' => 'required|in:open,closed'])['periodStatus'],
        ]);

        return back()->with('success', 'Clearance period updated.');
    }

    /**
     * Generate clearance slip for student.
     * BR33: One free slip per student per period
     */
    public function generateSlip(Request $request): RedirectResponse
    {
        $this->authorize('clearance.generateSlip', [Students::class, Clearanceperiods::class]);

        $validated = $request->validate([
            'studentId' => 'required|exists:students,studentId',
            'clearancePeriodId' => 'required|exists:clearanceperiods,clearancePeriodId',
        ]);

        $student = Students::findOrFail($validated['studentId']);
        $period = Clearanceperiods::findOrFail($validated['clearancePeriodId']);

        if ($period->periodStatus !== ClearancePeriodStatus::Open) {
            return back()->withErrors(['period' => 'Clearance period is not open.']);
        }

        $existing = Studentclearances::where('studentId', $student->studentId)
            ->where('clearancePeriodId', $period->clearancePeriodId)
            ->first();

        if ($existing && $existing->overallStatus !== ClearanceOverallStatus::Incomplete) {
            return back()->withErrors(['student' => 'Student already has a clearance for this period.']);
        }

        if (! $existing) {
            $clearance = Studentclearances::create([
                'studentId' => $student->studentId,
                'clearancePeriodId' => $period->clearancePeriodId,
                'overallStatus' => ClearanceOverallStatus::Pending,
            ]);

            // Create approval rows for each requirement
            $requirements = Clearancerequirements::with('office')->get();
            foreach ($requirements as $req) {
                Clearanceapprovals::create([
                    'studentClearanceId' => $clearance->studentClearanceId,
                    'clearanceRequirementId' => $req->clearanceRequirementId,
                    'status' => ClearanceApprovalStatus::Pending,
                ]);
            }
        } else {
            $clearance = $existing;
            $clearance->update(['overallStatus' => ClearanceOverallStatus::Pending]);
        }

        // TODO: Generate PDF slip here

        return back()->with('success', 'Clearance slip generated.');
    }

    /**
     * Record desk receipt (Registrar desk).
     * BR34: Received by registrar staff when completed slip submitted
     */
    public function recordReceipt(Request $request, Studentclearances $clearance): RedirectResponse
    {
        $this->authorize('recordDeskReceipt', $clearance);

        $clearance->update([
            'receivedBy' => Auth::id(),
            'receivedDate' => now(),
            'overallStatus' => ClearanceOverallStatus::Approved,
        ]);

        return back()->with('success', 'Desk receipt recorded.');
    }

    /**
     * Approve/waive clearance requirement (office-scoped).
     */
    public function approveRequirement(Request $request, Clearanceapprovals $approval): RedirectResponse
    {
        $this->authorize('clearance.approveRequirement', $approval);

        $validated = $request->validate([
            'status' => 'required|in:approved,waived,rejected',
            'remarks' => 'nullable|string',
        ]);

        $approval->update([
            'status' => $validated['status'],
            'approvedBy' => Auth::id(),
            'approvalDate' => now(),
            'remarks' => $validated['remarks'],
        ]);

        // Update overall clearance status
        $clearance = $approval->studentClearance;
        $pendingCount = $clearance->approvals()
            ->where('status', ClearanceApprovalStatus::Pending)
            ->count();

        $rejectedCount = $clearance->approvals()
            ->where('status', ClearanceApprovalStatus::Rejected)
            ->count();

        if ($rejectedCount > 0) {
            $clearance->update(['overallStatus' => ClearanceOverallStatus::Rejected]);
        } elseif ($pendingCount === 0) {
            $clearance->update(['overallStatus' => ClearanceOverallStatus::Approved]);
        }

        return back()->with('success', 'Requirement updated.');
    }

    /**
     * Process lost slip replacement (₱100 at Accounting).
     * BR33: Lost slip costs ₱100 before reissue
     */
    public function replaceLostSlip(Request $request): RedirectResponse
    {
        $this->authorize('clearance.replaceLostSlip', [Students::class, Clearanceperiods::class]);

        $validated = $request->validate([
            'studentId' => 'required|exists:students,studentId',
            'clearancePeriodId' => 'required|exists:clearanceperiods,clearancePeriodId',
            'orNumber' => 'required|string|max:50|unique:payments,orNumber',
        ]);

        $student = Students::findOrFail($validated['studentId']);
        $period = Clearanceperiods::findOrFail($validated['clearancePeriodId']);

        $clearance = Studentclearances::where('studentId', $student->studentId)
            ->where('clearancePeriodId', $period->clearancePeriodId)
            ->first();

        if (! $clearance || $clearance->overallStatus !== ClearanceOverallStatus::Incomplete) {
            return back()->withErrors(['clearance' => 'No lost clearance to replace.']);
        }

        // Record payment
        $feeType = Feetypes::where('feeName', 'Clearance Slip Replacement')->first();

        Payments::create([
            'enrollmentId' => null, // No enrollment for clearance replacement
            'orNumber' => $validated['orNumber'],
            'amount' => $feeType->defaultAmount ?? 100,
            'paymentDate' => now(),
            'paymentMode' => PaymentMode::Cash,
            'processedBy' => Auth::id(),
            'paymentStatus' => PaymentStatus::Paid,
        ]);

        // Reset clearance for reissue
        $clearance->update(['overallStatus' => ClearanceOverallStatus::Pending]);

        return back()->with('success', 'Lost slip replacement processed. New slip can be generated.');
    }

    /**
     * Print clearance slip (PDF).
     */
    public function printSlip(Studentclearances $clearance): Response
    {
        $this->authorize('view', $clearance);

        $clearance->load(['student', 'clearancePeriod.term.academicYear', 'approvals.requirement.office']);

        // TODO: Generate PDF using Browsershot
        return Inertia::render('Clearance/PrintSlip', [
            'clearance' => $clearance,
        ]);
    }
}
