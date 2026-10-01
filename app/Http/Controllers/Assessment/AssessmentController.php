<?php

namespace App\Http\Controllers\Assessment;

use App\Enums\CoverageType;
use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\FeeUnitBasis;
use App\Enums\OfficeId;
use App\Enums\PaymentStatus;
use App\Enums\ScholarshipStatus;
use App\Http\Controllers\Controller;
use App\Models\Charges;
use App\Models\Enrollments;
use App\Models\Feetypes;
use App\Models\Payments;
use App\Models\Scholarshiptypes;
use App\Models\Studentassessments;
use App\Models\Studentscholarships;
use App\Services\EnrollmentStateMachine;
use App\Services\WorkflowService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private WorkflowService $workflowService,
        private EnrollmentStateMachine $stateMachine
    ) {}

    /**
     * Display assessment queue.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Studentassessments::class);

        $query = Studentassessments::with(['enrollment.student', 'enrollment.course', 'enrollment.term', 'charges.feeType', 'scholarships.scholarshipType'])
            ->whereHas('enrollment', fn ($q) => $q->where('enrollmentStatus', EnrollmentStatus::Evaluated))
            ->when($request->search, fn ($q, $search) => $q->whereHas('enrollment.student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->orderByDesc('assessmentId');

        $assessments = $query->paginate(20)->withQueryString();

        // Fee summary across the whole filtered set — the summary tiles must
        // describe the full queue, not just the current page (audit 2026-09-25).
        $summary = (clone $query)
            ->reorder()
            ->selectRaw('coalesce(sum(totalAssessedAmount), 0) as total, coalesce(sum(remainingBalance), 0) as balance,
                sum(case when remainingBalance > 0 and remainingBalance < totalAssessedAmount then 1 else 0 end) as partialCount,
                sum(case when remainingBalance <= 0 then 1 else 0 end) as settledCount')
            ->first();

        $pendingEvaluations = Enrollments::with(['student', 'course', 'term', 'enrolledSubjects.subject'])
            ->where('enrollmentStatus', EnrollmentStatus::Evaluated)
            ->doesntHave('studentassessments')
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->orderByDesc('enrollmentId')
            ->get();

        return Inertia::render('Assessment/Index', [
            'assessments' => $assessments,
            'pendingEvaluations' => $pendingEvaluations,
            'summary' => [
                'total' => (float) ($summary->total ?? 0),
                'balance' => (float) ($summary->balance ?? 0),
                'partialCount' => (int) ($summary->partialCount ?? 0),
                'settledCount' => (int) ($summary->settledCount ?? 0),
            ],
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show assessment computation screen.
     */
    public function show(Studentassessments $assessment): Response
    {
        $this->authorize('view', $assessment);

        $assessment->load([
            'enrollment.student',
            'enrollment.course',
            'enrollment.term',
            'charges.feeType',
            'scholarships.scholarshipType',
            'payments',
            // The desk needs to see which box it is standing at, not only the bill.
            'enrollment.enrollmentworkflow.workflowsteps.office',
            'enrollment.enrollmentworkflow.workflowsteps.signedBy',
        ]);

        return Inertia::render('Assessment/Show', [
            'assessment' => $assessment,
            'feeTypes' => Feetypes::all(['feeTypeId', 'feeName', 'defaultAmount', 'unitBasis']),
            'scholarshipTypes' => Scholarshiptypes::all(['scholarshipTypeId', 'scholarshipName', 'coverageType', 'coveragePercent']),
        ]);
    }

    /**
     * Compute assessment (auto-compute from fee types).
     * BR19: Full (100%) scholarships exclusive; partial stack up to 100% cap
     */
    public function compute(Request $request, Enrollments $enrollment): RedirectResponse
    {
        $this->authorize('assessment.computeAtDesk', $enrollment);

        // Idempotency check: prevent duplicate assessments (DI-1)
        $existing = Studentassessments::where('enrollmentId', $enrollment->enrollmentId)->first();
        if ($existing) {
            return redirect()->route('assessment.show', $existing)->with('info', 'Assessment already exists for this enrollment.');
        }

        $enrolledUnits = $enrollment->enrolledSubjects()
            ->where('status', '!=', EnrolledSubjectStatus::Dropped->value)
            ->with('subject')
            ->get()
            ->sum(fn ($es) => $es->subject->lectureUnits + $es->subject->labUnits);

        $feeTypes = Feetypes::all();
        $charges = [];
        $totalAssessed = 0;

        foreach ($feeTypes as $feeType) {
            $amount = $feeType->unitBasis === FeeUnitBasis::PerUnit
                ? $feeType->defaultAmount * $enrolledUnits
                : $feeType->defaultAmount;

            $charges[] = [
                'feeTypeId' => $feeType->feeTypeId,
                'amount' => $amount,
                'waivedAmount' => 0,
            ];
            $totalAssessed += $amount;
        }

        // Apply School Grant (100% full tuition) - BR19
        // NOTE: Not auto-awarded here. Scholarships are applied explicitly via
        // applyScholarship (office 3) or during clearance, matching the school's
        // real process. Auto-awarding 100% coverage to everyone would zero out
        // every balance and block the Accounting payment step.
        $totalScholarshipCoverage = 0;
        $totalWaived = 0;

        $remainingBalance = $totalAssessed - $totalScholarshipCoverage - $totalWaived;

        $assessment = DB::transaction(function () use ($enrollment, $totalAssessed, $totalScholarshipCoverage, $totalWaived, $remainingBalance, $charges) {
            $assessment = Studentassessments::create([
                'enrollmentId' => $enrollment->enrollmentId,
                'totalAssessedAmount' => $totalAssessed,
                'totalScholarshipCoverage' => $totalScholarshipCoverage,
                'totalWaived' => $totalWaived,
                'remainingBalance' => max(0, $remainingBalance),
                'assessmentDate' => now(),
            ]);

            foreach ($charges as $charge) {
                Charges::create(array_merge($charge, ['assessmentId' => $assessment->assessmentId]));
            }

            return $assessment;
        });

        // Transition enrollment to assessed
        // $this->stateMachine->transition($enrollment, EnrollmentStatus::Assessed, Auth::user(), 'Assessment computed');

        return redirect()->route('assessment.show', $assessment)->with('success', 'Assessment computed successfully.');
    }

    /**
     * Apply outside scholarships.
     * BR19: Partial scholarships stack up to 100% cap
     */
    public function applyScholarship(Request $request, Studentassessments $assessment): RedirectResponse
    {
        $this->authorize('applyScholarships', $assessment);

        $validated = $request->validate([
            'scholarshipTypeId' => 'required|exists:scholarshiptypes,scholarshipTypeId',
        ]);

        $scholarshipType = Scholarshiptypes::findOrFail($validated['scholarshipTypeId']);
        $enrollment = $assessment->enrollment;

        // studentscholarships carries a unique (studentId, scholarshipTypeId, termId).
        // Re-picking the same grant from the screen — the usual double-click — died
        // with a raw duplicate-key error page, so the pair is refused by name first.
        $alreadyHeld = Studentscholarships::where('studentId', $enrollment->studentId)
            ->where('termId', $enrollment->termId)
            ->where('scholarshipTypeId', $scholarshipType->scholarshipTypeId)
            ->exists();

        if ($alreadyHeld) {
            return back()->withErrors([
                'scholarshipTypeId' => "{$scholarshipType->scholarshipName} is already awarded to this student for this term.",
            ]);
        }

        // A 100% grant is exclusive per student per term. The scope is the term: a
        // continuing student who held a full grant last term must be able to be
        // granted again this one, and the cap below is measured against this term's
        // assessment anyway.
        $existingFull = Studentscholarships::where('studentId', $enrollment->studentId)
            ->where('termId', $enrollment->termId)
            ->whereHas('scholarshipType', fn ($q) => $q->where('coverageType', CoverageType::Full))
            ->exists();

        if ($existingFull && $scholarshipType->coverageType === CoverageType::Full) {
            return back()->withErrors(['scholarshipTypeId' => 'Student already has a full scholarship.']);
        }

        // Calculate coverage
        $coverageAmount = $scholarshipType->coverageType === CoverageType::Full
            ? $assessment->remainingBalance
            : min($assessment->remainingBalance, $assessment->totalAssessedAmount * ($scholarshipType->coveragePercent / 100));

        // A grant that awards nothing is not a grant. Once the account is fully
        // covered (or fully paid) the remaining balance is 0, so every award from
        // here on computes to ₱0 — yet still filed an Active row on the student's
        // record. That is BR19's 100% cap seen from the other side: the cap is
        // already reached, so there is nothing left to attach.
        if ((float) $coverageAmount <= 0) {
            return back()->withErrors([
                'scholarshipTypeId' => 'This account has no remaining balance to cover (₱'.number_format((float) $assessment->remainingBalance, 2).'), so the grant would award nothing.',
            ]);
        }

        // Check 100% cap
        $newTotalCoverage = $assessment->totalScholarshipCoverage + $coverageAmount;
        if ($newTotalCoverage > $assessment->totalAssessedAmount) {
            return back()->withErrors(['scholarshipTypeId' => 'Total scholarship coverage cannot exceed 100%.']);
        }

        Studentscholarships::create([
            'studentId' => $enrollment->studentId,
            'scholarshipTypeId' => $scholarshipType->scholarshipTypeId,
            'termId' => $enrollment->termId,
            'status' => ScholarshipStatus::Active,
            'approvedBy' => Auth::user()->userId,
            'awardedBeforeEnrollment' => false,
        ]);

        $totalPaid = Payments::where('enrollmentId', $assessment->enrollmentId)
            ->where('paymentStatus', PaymentStatus::Paid)
            ->sum('amount');

        $assessment->update([
            'totalScholarshipCoverage' => $newTotalCoverage,
            'remainingBalance' => max(0, $assessment->totalAssessedAmount - $newTotalCoverage - $assessment->totalWaived - $totalPaid),
        ]);

        return back()->with('success', 'Scholarship applied.');
    }

    /**
     * Adjust charges.
     */
    public function adjustCharges(Request $request, Studentassessments $assessment): RedirectResponse
    {
        $this->authorize('adjustCharges', $assessment);

        $validated = $request->validate([
            'charges' => 'required|array',
            'charges.*.chargeId' => 'required|exists:charges,chargeId',
            'charges.*.amount' => 'required|numeric|min:0',
            'charges.*.waivedAmount' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($assessment, $validated) {
            foreach ($validated['charges'] as $chargeData) {
                $charge = Charges::findOrFail($chargeData['chargeId']);
                $charge->update([
                    'amount' => $chargeData['amount'],
                    'waivedAmount' => $chargeData['waivedAmount'] ?? 0,
                ]);
            }

            // Recalculate assessment totals
            $assessment->refresh();
            $totalAssessed = $assessment->charges->sum('amount');
            $totalWaived = $assessment->charges->sum('waivedAmount');

            $totalPaid = Payments::where('enrollmentId', $assessment->enrollmentId)
                ->where('paymentStatus', PaymentStatus::Paid)
                ->sum('amount');

            $assessment->update([
                'totalAssessedAmount' => $totalAssessed,
                'totalWaived' => $totalWaived,
                'remainingBalance' => max(0, $totalAssessed - $assessment->totalScholarshipCoverage - $totalWaived - $totalPaid),
            ]);
        });

        return back()->with('success', 'Charges adjusted.');
    }

    /**
     * Finalize assessment.
     */
    public function finalize(Studentassessments $assessment): RedirectResponse
    {
        $this->authorize('finalize', $assessment);

        // Idempotency guard (mirrors compute): finalize is only valid from
        // 'evaluated'. Re-clicking Finalize on an already-finalized assessment
        // would otherwise hit the state machine's InvalidStateTransitionException
        // (422) — same-state transitions have no self-loop.
        if ($assessment->enrollment->enrollmentStatus !== EnrollmentStatus::Evaluated) {
            return back()->with('info', 'Assessment is already finalized for this enrollment.');
        }

        DB::transaction(function () use ($assessment) {
            // Transition enrollment to assessed (moves it into the Accounting queue)
            $this->stateMachine->transition($assessment->enrollment, EnrollmentStatus::Assessed, Auth::user(), 'Assessment finalized');

            // Sign the Assessment step (office 3) — null-safe: skipped for continuing/shifter students
            $workflow = $assessment->enrollment->enrollmentworkflow;
            if ($workflow) {
                // Office 3's record name is Scholarship; the workflow label for the
                // same box is Assessment.
                $this->workflowService->signStepByOffice($workflow, OfficeId::Scholarship->value, Auth::user());
            }
        });

        return back()->with('success', 'Assessment finalized. Ready for payment.');
    }
}
