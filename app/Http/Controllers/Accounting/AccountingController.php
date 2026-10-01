<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\EnrollmentStatus;
use App\Enums\OfficeId;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
     *
     * The desk has two queues: accounts that still owe something, and accounts that
     * owe nothing but have not been closed yet. Only the first used to be listed, so
     * a fully-granted student disappeared from Accounting and never reached `paid`.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Payments::class);

        $queue = $request->input('queue') === 'no_balance' ? 'no_balance' : 'due';

        $base = Studentassessments::with(['enrollment.student', 'enrollment.course', 'enrollment.term'])
            ->whereHas('enrollment', fn ($q) => $q->where('enrollmentStatus', EnrollmentStatus::Assessed))
            ->when($request->search, fn ($q, $search) => $q->whereHas('enrollment.student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)));

        $query = (clone $base)
            ->when($queue === 'no_balance', fn ($q) => $q->where('remainingBalance', '<=', 0), fn ($q) => $q->where('remainingBalance', '>', 0))
            ->orderByDesc('assessmentId');

        // Financial summary across the whole filtered set — the summary tiles
        // must describe the full queue, not just the current page (audit 2026-09-25).
        // Taken before paginate(), so the page limit cannot reach the aggregates.
        $summary = (clone $query)
            ->reorder()
            ->selectRaw('coalesce(sum(totalAssessedAmount), 0) as totalAssessed, coalesce(sum(remainingBalance), 0) as totalBalance,
                sum(case when remainingBalance > 0 and remainingBalance < totalAssessedAmount then 1 else 0 end) as partialCount,
                sum(case when remainingBalance >= totalAssessedAmount and totalAssessedAmount > 0 then 1 else 0 end) as unpaidCount,
                coalesce(sum(totalScholarshipCoverage + totalWaived), 0) as totalCovered')
            ->first();

        $assessments = $query->paginate(20)->withQueryString();

        // Both queues are counted whatever the desk is looking at, so the toggle
        // can show that a zero-balance account is waiting to be signed off rather
        // than an empty term. The stored column is used here, not the recomputed
        // balance: this is a list, and record()/void()/settle keep it in step.
        $queueCounts = [
            'due' => (clone $base)->where('remainingBalance', '>', 0)->count(),
            'no_balance' => (clone $base)->where('remainingBalance', '<=', 0)->count(),
        ];

        return Inertia::render('Accounting/Index', [
            'assessments' => $assessments,
            'summary' => [
                'totalAssessed' => (float) ($summary->totalAssessed ?? 0),
                'totalBalance' => (float) ($summary->totalBalance ?? 0),
                'partialCount' => (int) ($summary->partialCount ?? 0),
                'unpaidCount' => (int) ($summary->unpaidCount ?? 0),
                'totalCovered' => (float) ($summary->totalCovered ?? 0),
            ],
            'queueCounts' => $queueCounts,
            'filters' => $request->only(['search', 'queue']),
        ]);
    }

    /**
     * Show payment recording form.
     */
    public function show(Studentassessments $assessment): Response
    {
        $this->authorize('view', $assessment);

        $assessment->load(['enrollment.student', 'enrollment.course', 'enrollment.term', 'charges.feeType', 'payments.processedBy', 'enrollment.enrollmentworkflow.workflowsteps.office', 'enrollment.enrollmentworkflow.workflowsteps.signedBy']);

        return Inertia::render('Accounting/Show', [
            'assessment' => $assessment,
            'paymentModes' => collect(PaymentMode::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->values(),
            'can' => [
                'settleNoBalance' => $assessment->enrollment?->enrollmentStatus === EnrollmentStatus::Assessed
                    && Auth::user()->can('payment.settle', $assessment),
            ],
            // Recomputed from the receipts on file: the desk decides whether it can
            // settle here on the same figure the policy checks, not on a stored
            // column that may have fallen behind.
            'outstandingBalance' => $assessment->outstandingBalance(),
        ]);
    }

    /**
     * Record payment.
     * BR11: Assessment must exist before payment
     * BR5: OR number must be unique
     */
    public function record(Request $request, Studentassessments $assessment): RedirectResponse
    {
        $this->authorize('payment.recordAtDesk', $assessment);

        // Workflow-order guard: payments are only collected for assessed enrollments.
        // Prevents paid→paid / evaluated→paid InvalidStateTransitionException (422)
        // when the cashier revisits a settled assessment, and blocks paying an
        // assessment that Assessment has not finalized yet.
        $enrollment = $assessment->enrollment;
        if ($enrollment && $enrollment->enrollmentStatus !== EnrollmentStatus::Assessed) {
            return redirect()->route('accounting.show', $assessment)
                ->with('warning', "Payment can only be collected for assessed enrollments. Current status: {$enrollment->enrollmentStatus->value}.");
        }

        $validated = $request->validate([
            'orNumber' => 'required|string|max:50|unique:payments,orNumber',
            'amount' => 'required|numeric|min:0.01',
            'paymentMode' => ['required', Rule::enum(PaymentMode::class)],
            'paymentDate' => 'required|date',
        ]);

        DB::transaction(function () use ($assessment, $validated) {
            $payment = Payments::create([
                'enrollmentId' => $assessment->enrollmentId,
                'orNumber' => $validated['orNumber'],
                'amount' => $validated['amount'],
                'paymentDate' => $validated['paymentDate'],
                'paymentMode' => $validated['paymentMode'],
                'processedBy' => Auth::user()->userId,
                'paymentStatus' => PaymentStatus::Paid,
            ]);

            // Recalculate remaining balance (payment already saved above, so sum includes it)
            $newBalance = $assessment->outstandingBalance();

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
                    $this->workflowService->signStepByOffice($workflow, OfficeId::Accounting->value, Auth::user());
                }
            }
        });

        return redirect()->route('accounting.index')->with('success', 'Payment recorded successfully.');
    }

    /**
     * Close an account that owes nothing.
     *
     * A 100% scholarship grant or a full waiver leaves a zero balance, and `record`
     * deliberately refuses those — so before this action nothing could move the
     * enrollment to `paid` or sign the Accounting step, and the student stalled
     * before Registrar. No cash is taken and no Official Receipt is issued here.
     * BR11 still applies: the assessment must be finalized first.
     */
    public function settleNoBalance(Studentassessments $assessment): RedirectResponse
    {
        $this->authorize('payment.settle', $assessment);

        $enrollment = $assessment->enrollment;
        if ($enrollment && $enrollment->enrollmentStatus !== EnrollmentStatus::Assessed) {
            return redirect()->route('accounting.show', $assessment)
                ->with('warning', "Only assessed enrollments can be settled here. Current status: {$enrollment->enrollmentStatus->value}.");
        }

        // The policy already checked this, but a payment recorded between the page
        // load and the click changes the answer — settle on the live figure.
        $balance = $assessment->outstandingBalance();
        if ($balance > 0) {
            return redirect()->route('accounting.show', $assessment)
                ->with('warning', 'This account still has an outstanding balance of ₱'.number_format($balance, 2).'. Record a payment instead.');
        }

        DB::transaction(function () use ($assessment, $enrollment, $balance) {
            // Written back so a column that had fallen behind stops showing the
            // account as owing, and the row leaves the `due` queue for good.
            $assessment->update(['remainingBalance' => $balance]);

            if ($enrollment) {
                $this->stateMachine->transition($enrollment, EnrollmentStatus::Paid, Auth::user(), 'Fees fully covered by scholarship or waiver — no cash collected');

                // Sign workflow step 4 (Accounting Payment) — the same signature a
                // collected payment would have earned.
                $workflow = $enrollment->enrollmentworkflow;
                if ($workflow) {
                    $this->workflowService->signStepByOffice($workflow, OfficeId::Accounting->value, Auth::user());
                }
            }
        });

        return redirect()->route('accounting.index')->with('success', 'Account settled — no balance due.');
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

        DB::transaction(function () use ($payment) {
            $payment->update(['paymentStatus' => PaymentStatus::Pending]);

            // Recalculate assessment balance
            $enrollment = $payment->enrollment;
            $assessment = $enrollment?->studentassessments;
            if ($assessment) {
                $newBalance = $assessment->outstandingBalance();
                $assessment->update([
                    'remainingBalance' => $newBalance,
                ]);

                // If the enrollment was 'paid' but now has a positive balance,
                // revert it to 'assessed' to prevent Registrar from approving
                // an unpaid student.
                if ($newBalance > 0 && $enrollment->enrollmentStatus === EnrollmentStatus::Paid) {
                    $this->stateMachine->transition(
                        $enrollment,
                        EnrollmentStatus::Assessed,
                        Auth::user(),
                        'Payment voided — reverted to assessed (remaining balance: ₱'.number_format($newBalance, 2).')'
                    );
                }
            }
        });

        return back()->with('success', 'Payment voided.');
    }
}
