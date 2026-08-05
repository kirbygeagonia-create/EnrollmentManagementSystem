<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payments;
use App\Models\Studentassessments;
use App\Services\EnrollmentStateMachine;
use App\Services\WorkflowService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private EnrollmentStateMachine $stateMachine,
        private WorkflowService $workflowService
    ) {}

    /**
     * Display payment desk screen.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Payments::class);

        $query = Studentassessments::with(['enrollment.student', 'enrollment.course', 'enrollment.term'])
            ->where('remainingBalance', '>', 0)
            ->whereHas('enrollment', fn ($q) => $q->where('enrollmentStatus', EnrollmentStatus::Assessed))
            ->when($request->search, fn ($q, $search) => $q->whereHas('enrollment.student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->orderByDesc('assessmentId');

        $assessments = $query->paginate(20)->withQueryString();

        return Inertia::render('Accounting/Index', [
            'assessments' => $assessments,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show payment recording form.
     */
    public function show(Studentassessments $assessment): Response
    {
        $this->authorize('view', $assessment);

        $assessment->load(['enrollment.student', 'enrollment.course', 'enrollment.term', 'charges.feeType']);

        return Inertia::render('Accounting/Show', [
            'assessment' => $assessment,
            'paymentModes' => collect(PaymentMode::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
        ]);
    }

    /**
     * Record payment.
     * BR11: Assessment must exist before payment
     * BR5: OR number must be unique
     */
    public function record(Request $request, Studentassessments $assessment): RedirectResponse
    {
        $this->authorize('payment.record', $assessment);

        $validated = $request->validate([
            'orNumber' => 'required|string|max:50|unique:payments,orNumber',
            'amount' => 'required|numeric|min:0.01',
            'paymentMode' => 'required|in:cash,check,online',
            'paymentDate' => 'required|date',
        ]);

        $payment = Payments::create([
            'enrollmentId' => $assessment->enrollmentId,
            'orNumber' => $validated['orNumber'],
            'amount' => $validated['amount'],
            'paymentDate' => $validated['paymentDate'],
            'paymentMode' => $validated['paymentMode'],
            'processedBy' => Auth::user()->userId,
            'paymentStatus' => PaymentStatus::Paid,
        ]);

        // Recalculate remaining balance
        $totalPaid = $assessment->payments()->sum('amount') + $validated['amount'];
        $newBalance = max(0, $assessment->totalAssessedAmount - $assessment->totalScholarshipCoverage - $assessment->totalWaived - $totalPaid);

        $assessment->update([
            'remainingBalance' => $newBalance,
        ]);

        // Transition enrollment to paid if fully paid
        $enrollment = $assessment->enrollment;
        if ($newBalance <= 0 && $enrollment) {
            $this->stateMachine->transition($enrollment, EnrollmentStatus::Paid, Auth::user(), 'Full payment received');

            // Sign workflow step 4 (Accounting Payment)
            $workflow = $enrollment->enrollmentworkflow;
            if ($workflow) {
                $this->workflowService->signStepByOffice($workflow, 2, Auth::user());
            }
        }

        return redirect()->route('accounting.index')->with('success', 'Payment recorded successfully.');
    }

    /**
     * Daily collection report.
     */
    public function dailyReport(Request $request): Response
    {
        $this->authorize('dailyReport', Payments::class);

        $date = $request->date ?? now()->toDateString();

        $payments = Payments::with(['enrollment.student', 'processedBy'])
            ->whereDate('paymentDate', $date)
            ->where('paymentStatus', PaymentStatus::Paid)
            ->orderByDesc('paymentId')
            ->get();

        $summary = [
            'totalAmount' => $payments->sum('amount'),
            'totalCount' => $payments->count(),
            'byMode' => $payments->groupBy('paymentMode')->map(fn ($g) => ['count' => $g->count(), 'amount' => $g->sum('amount')]),
        ];

        return Inertia::render('Accounting/DailyReport', [
            'payments' => $payments,
            'summary' => $summary,
            'date' => $date,
        ]);
    }

    /**
     * Void payment.
     */
    public function void(Request $request, Payments $payment): RedirectResponse
    {
        $this->authorize('void', $payment);

        $payment->update(['paymentStatus' => PaymentStatus::Pending]);

        // Recalculate assessment balance
        $assessment = $payment->enrollment->studentassessments;
        if ($assessment) {
            $totalPaid = $assessment->payments()->where('paymentStatus', PaymentStatus::Paid)->sum('amount');
            $assessment->update([
                'remainingBalance' => max(0, $assessment->totalAssessedAmount - $assessment->totalScholarshipCoverage - $assessment->totalWaived - $totalPaid),
            ]);
        }

        return back()->with('success', 'Payment voided.');
    }
}
