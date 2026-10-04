<?php

namespace App\Http\Controllers\Clearance;

use App\Enums\ClearanceApprovalStatus;
use App\Enums\ClearanceOverallStatus;
use App\Enums\ClearancePeriodStatus;
use App\Enums\DocumentType;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Clearanceapprovals;
use App\Models\Clearanceperiods;
use App\Models\Clearancerequirements;
use App\Models\Enrollments;
use App\Models\Feetypes;
use App\Models\Payments;
use App\Models\Studentclearances;
use App\Models\Students;
use App\Services\PrintService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        $query = Studentclearances::with(['student', 'clearancePeriod.term.academicYear', 'approvals.requirement.office', 'receivedByUser'])
            ->when($request->periodId, fn ($q, $id) => $q->where('clearancePeriodId', $id))
            ->when($request->status, fn ($q, $status) => $q->where('overallStatus', $status))
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->orderByDesc('studentClearanceId');

        $clearances = $query->paginate(20)->withQueryString();

        // Standing is recorded on the enrollment, and a clearance row carries no
        // enrollmentId — only (studentId, period → termId). One lookup for the
        // page, then the queue reads like every other desk's.
        $standings = Enrollments::standingMapFor(
            $clearances->getCollection()
                ->map(fn (Studentclearances $c) => [$c->studentId, $c->clearancePeriod?->termId])
        );
        $clearances->getCollection()->each(function (Studentclearances $c) use ($standings) {
            $match = $standings[$c->studentId.'-'.$c->clearancePeriod?->termId] ?? null;
            $c->setAttribute('studentType', $match?->studentType?->value);
            $c->setAttribute('academicStanding', $match?->academicStanding?->value);
        });

        // Full-dataset status counts for the summary tiles (m2): counting
        // client-side from clearances.data understates the dataset beyond
        // page 1. Reuses the same filters so the tiles track the list.
        // reorder() strips the list's orderByDesc — under MySQL 8's default
        // ONLY_FULL_GROUP_BY, ORDER BY on a non-grouped column with GROUP BY
        // is a 500 (SQLite is lenient, so tests alone won't catch it).
        $statusCounts = (clone $query)
            ->reorder()
            ->selectRaw('overallStatus, count(*) as aggregate')
            ->groupBy('overallStatus')
            ->pluck('aggregate', 'overallStatus');

        return Inertia::render('Clearance/Index', [
            'clearances' => $clearances,
            'periods' => $periods,
            // What a lost slip costs is the fee table's answer, shown to the desk so the
            // modal quotes the amount it is about to charge rather than a remembered one.
            'replacementFee' => Feetypes::clearanceSlipReplacementFee(),
            'replacementFeeName' => Feetypes::CLEARANCE_SLIP_REPLACEMENT,
            'students' => Students::orderBy('lastName')->orderBy('firstName')->get(['studentId', 'schoolIdNumber', 'firstName', 'middleName', 'lastName']),
            'filters' => $request->only(['periodId', 'status', 'search']),
            'stats' => [
                'pending' => (int) ($statusCounts[ClearanceOverallStatus::Pending->value] ?? 0),
                'approved' => (int) ($statusCounts[ClearanceOverallStatus::Approved->value] ?? 0),
                'rejected' => (int) ($statusCounts[ClearanceOverallStatus::Rejected->value] ?? 0),
                'waived' => (int) ($statusCounts[ClearanceOverallStatus::Waived->value] ?? 0),
                'incomplete' => (int) ($statusCounts[ClearanceOverallStatus::Incomplete->value] ?? 0),
            ],
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
     *
     * Ruling 16: a window does not close on top of its unfinished clearances. The offices
     * that are still deciding a slip would lose their work with no notice, so the close is
     * refused and the count is named — the desk can see how many slips it has to clear.
     */
    public function updatePeriod(Request $request, Clearanceperiods $period): RedirectResponse
    {
        $this->authorize('managePeriods', Studentclearances::class);

        $status = $request->validate(['periodStatus' => 'required|in:open,closed'])['periodStatus'];

        if ($status === 'closed' && $period->periodStatus !== ClearancePeriodStatus::Closed) {
            $pending = $period->studentclearances()
                ->where('overallStatus', ClearanceOverallStatus::Pending)
                ->count();

            if ($pending > 0) {
                return back()->withErrors([
                    'periodStatus' => "Cannot close this window: {$pending} clearance(s) in it are still with the offices. "
                        .'Decide them — approve, reject or waive — then close.',
                ]);
            }
        }

        $period->update(['periodStatus' => $status]);

        return back()->with('success', 'Clearance period updated.');
    }

    /**
     * Extend the clearance window past its end date (ruling 13).
     *
     * `extended` is not a value the status radio offers — a desk cannot type its way into
     * it. It is the result of this action, which moves the end date out and keeps the
     * window taking slips, and is recorded in the audit log with who did it and when.
     * A closed window has to be reopened first, because an extension that also un-closes
     * a window would hide two decisions in one click.
     */
    public function extendPeriod(Request $request, Clearanceperiods $period): RedirectResponse
    {
        $this->authorize('managePeriods', Studentclearances::class);

        if ($period->periodStatus === ClearancePeriodStatus::Closed) {
            return back()->withErrors([
                'clearanceEndDate' => 'This window is closed — set it open again before extending it.',
            ]);
        }

        $validated = $request->validate([
            'clearanceEndDate' => 'required|date',
        ]);

        $newEnd = Carbon::parse($validated['clearanceEndDate']);

        if (! $newEnd->greaterThan($period->clearanceEndDate)) {
            return back()->withErrors([
                'clearanceEndDate' => 'An extension has to move the end date later than '
                    .$period->clearanceEndDate->toDateString().', which is where this window already stops.',
            ]);
        }

        $period->update([
            'clearanceEndDate' => $newEnd->toDateString(),
            'periodStatus' => ClearancePeriodStatus::Extended,
        ]);

        return back()->with('success', 'Clearance window extended to '.$newEnd->toFormattedDateString().'.');
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

        if (! $period->isAccepting()) {
            return back()->withErrors(['period' => 'This clearance window is closed — only an open or extended period issues slips.']);
        }

        $existing = Studentclearances::where('studentId', $student->studentId)
            ->where('clearancePeriodId', $period->clearancePeriodId)
            ->first();

        if ($existing && $existing->overallStatus !== ClearanceOverallStatus::Incomplete) {
            return back()->withErrors(['student' => 'Student already has a clearance for this period.']);
        }

        DB::transaction(function () use ($student, $period) {
            $existing = Studentclearances::where('studentId', $student->studentId)
                ->where('clearancePeriodId', $period->clearancePeriodId)
                ->first();

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
                        'remarks' => '',
                    ]);
                }
            } else {
                $existing->update(['overallStatus' => ClearanceOverallStatus::Pending]);
            }
        });

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
            'receivedBy' => Auth::user()->userId,
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

        DB::transaction(function () use ($approval, $validated) {
            $approval->update([
                'status' => $validated['status'],
                'approvedBy' => Auth::user()->userId,
                'approvalDate' => now(),
                'remarks' => $validated['remarks'] ?? '',
            ]);

            // Update overall clearance status
            $clearance = $approval->studentClearance;
            $pendingCount = $clearance->approvals()
                ->where('status', ClearanceApprovalStatus::Pending->value)
                ->count();

            $rejectedCount = $clearance->approvals()
                ->where('status', ClearanceApprovalStatus::Rejected->value)
                ->count();

            if ($rejectedCount > 0) {
                $clearance->update(['overallStatus' => ClearanceOverallStatus::Rejected]);
            } elseif ($pendingCount === 0) {
                $clearance->update(['overallStatus' => ClearanceOverallStatus::Approved]);
            }
        });

        return back()->with('success', 'Requirement updated.');
    }

    /**
     * Process lost slip replacement. BR33 requires payment before a slip is reissued;
     * the amount comes from the fee table rather than a constant in this method — and a
     * fee the Registrar has not set is refused by name instead of charged at a guess.
     */
    public function replaceLostSlip(Request $request): RedirectResponse
    {
        $this->authorize('clearance.replaceLostSlip', [Students::class, Clearanceperiods::class]);

        $fee = Feetypes::clearanceSlipReplacementFee();

        if ($fee === null) {
            return back()->withErrors([
                'fee' => 'No replacement fee is on file. Create the "'
                    .Feetypes::CLEARANCE_SLIP_REPLACEMENT.'" fee type in Admin → Reference Data → Fee Types, '
                    .'then charge that amount.',
            ]);
        }

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

        if (! $clearance || ! in_array($clearance->overallStatus, [
            ClearanceOverallStatus::Incomplete,
            ClearanceOverallStatus::Pending,
        ])) {
            return back()->withErrors(['clearance' => 'No lost clearance to replace.']);
        }

        DB::transaction(function () use ($validated, $clearance, $fee) {
            // Record payment
            Payments::create([
                'enrollmentId' => null, // No enrollment for clearance replacement
                'orNumber' => $validated['orNumber'],
                'amount' => $fee,
                'paymentDate' => now(),
                'paymentMode' => PaymentMode::Cash,
                'processedBy' => Auth::user()->userId,
                'paymentStatus' => PaymentStatus::Paid,
            ]);

            // Reset clearance for reissue
            $clearance->update(['overallStatus' => ClearanceOverallStatus::Pending]);
        });

        return back()->with('success', 'Lost slip replacement processed. New slip can be generated.');
    }

    /**
     * Print clearance slip (browser print screen).
     */
    public function printSlip(Studentclearances $clearance, PrintService $printService): Response
    {
        $this->authorize('view', $clearance);

        $clearance->load(['student.enrollments.course', 'clearancePeriod.term.academicYear', 'approvals.requirement.office', 'receivedByUser']);

        $termEnrollment = $clearance->termEnrollment();

        // Log print (BR: every print inserts a documentprintlog row). The slip is the
        // student's, so the student keys the issue even when no enrollment exists for
        // the period's term yet.
        $printLog = $printService->recordIssue(
            $termEnrollment?->enrollmentId,
            DocumentType::ClearanceSlip,
            Auth::user()->userId,
            $clearance->studentId
        );

        return Inertia::render('Clearance/PrintSlip', [
            'clearance' => $clearance,
            'termEnrollment' => $termEnrollment,
            'documentNumber' => $printLog->documentNumber,
        ]);
    }

    /**
     * Download the clearance slip as a PDF.
     */
    public function downloadSlip(Studentclearances $clearance, PrintService $printService): BinaryFileResponse
    {
        $this->authorize('view', $clearance);

        return $printService
            ->printClearanceSlip($clearance, Auth::user()->userId)
            ->asDownload("clearance-slip-{$clearance->student?->schoolIdNumber}.pdf");
    }
}
