<?php

namespace App\Http\Controllers\Assessment;

use App\Http\Controllers\Controller;
use App\Models\Studentassessments;
use App\Models\Enrollments;
use App\Models\Charges;
use App\Models\Feetypes;
use App\Models\Studentscholarships;
use App\Models\Scholarshiptypes;
use App\Models\Students;
use App\Models\Courses;
use App\Enums\EnrollmentStatus;
use App\Enums\ScholarshipStatus;
use App\Enums\CoverageType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display assessment queue.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Studentassessments::class);

        $query = Studentassessments::with(['enrollment.student', 'enrollment.course', 'enrollment.term', 'charges.feeType', 'scholarships.scholarshipType'])
            ->whereHas('enrollment', fn($q) => $q->where('enrollmentStatus', EnrollmentStatus::Evaluated))
            ->when($request->search, fn($q, $search) => $q->whereHas('enrollment.student', fn($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->latest();

        $assessments = $query->paginate(20)->withQueryString();

        return Inertia::render('Assessment/Index', [
            'assessments' => $assessments,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show assessment computation screen.
     */
    public function show(Studentassessments $assessment): Response
    {
        $this->authorize('view', $assessment);

        $assessment->load(['enrollment.student', 'enrollment.course', 'enrollment.term', 'charges.feeType', 'scholarships.scholarshipType']);

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
        $this->authorize('compute', $enrollment);

        $enrolledUnits = $enrollment->enrolledSubjects()
            ->where('status', '!=', 'dropped')
            ->with('subject')
            ->get()
            ->sum(fn($es) => $es->subject->lectureUnits + $es->subject->labUnits);

        $feeTypes = Feetypes::all();
        $charges = [];
        $totalAssessed = 0;

        foreach ($feeTypes as $feeType) {
            $amount = $feeType->unitBasis === 'perUnit' 
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
        $schoolGrant = Scholarshiptypes::where('scholarshipName', 'School Grant (Free Tuition)')->first();
        $totalScholarshipCoverage = 0;
        $totalWaived = 0;

        if ($schoolGrant) {
            $totalScholarshipCoverage = $totalAssessed;
            $totalWaived = 0; // Lab fees might be waived separately
        }

        $remainingBalance = $totalAssessed - $totalScholarshipCoverage - $totalWaived;

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

        // Auto-award School Grant
        if ($schoolGrant) {
            Studentscholarships::create([
                'studentId' => $enrollment->studentId,
                'scholarshipTypeId' => $schoolGrant->scholarshipTypeId,
                'termId' => $enrollment->termId,
                'status' => ScholarshipStatus::Active,
                'approvedBy' => Auth::id(),
                'awardedBeforeEnrollment' => true,
            ]);
        }

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
        
        // Check if student already has full scholarship
        $existingFull = $assessment->scholarships()
            ->whereHas('scholarshipType', fn($q) => $q->where('coverageType', CoverageType::Full))
            ->exists();

        if ($existingFull && $scholarshipType->coverageType === CoverageType::Full) {
            return back()->withErrors(['scholarshipTypeId' => 'Student already has a full scholarship.']);
        }

        // Calculate coverage
        $coverageAmount = $scholarshipType->coverageType === CoverageType::Full
            ? $assessment->remainingBalance
            : min($assessment->remainingBalance, $assessment->totalAssessedAmount * ($scholarshipType->coveragePercent / 100));

        // Check 100% cap
        $newTotalCoverage = $assessment->totalScholarshipCoverage + $coverageAmount;
        if ($newTotalCoverage > $assessment->totalAssessedAmount) {
            return back()->withErrors(['scholarshipTypeId' => 'Total scholarship coverage cannot exceed 100%.']);
        }

        Studentscholarships::create([
            'studentId' => $assessment->enrollment->studentId,
            'scholarshipTypeId' => $scholarshipType->scholarshipTypeId,
            'termId' => $assessment->enrollment->termId,
            'status' => ScholarshipStatus::Active,
            'approvedBy' => Auth::id(),
            'awardedBeforeEnrollment' => false,
        ]);

        $assessment->update([
            'totalScholarshipCoverage' => $newTotalCoverage,
            'remainingBalance' => $assessment->totalAssessedAmount - $newTotalCoverage - $assessment->totalWaived,
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

        foreach ($validated['charges'] as $chargeData) {
            $charge = Charges::findOrFail($chargeData['chargeId']);
            $charge->update([
                'amount' => $chargeData['amount'],
                'waivedAmount' => $chargeData['waivedAmount'] ?? 0,
            ]);
        }

        // Recalculate assessment totals
        $totalAssessed = $assessment->charges->sum('amount');
        $totalWaived = $assessment->charges->sum('waivedAmount');
        
        $assessment->update([
            'totalAssessedAmount' => $totalAssessed,
            'totalWaived' => $totalWaived,
            'remainingBalance' => $totalAssessed - $assessment->totalScholarshipCoverage - $totalWaived,
        ]);

        return back()->with('success', 'Charges adjusted.');
    }

    /**
     * Finalize assessment.
     */
    public function finalize(Studentassessments $assessment): RedirectResponse
    {
        $this->authorize('finalize', $assessment);

        // Transition enrollment to assessed
        // $this->stateMachine->transition($assessment->enrollment, EnrollmentStatus::Assessed, Auth::user(), 'Assessment finalized');

        return back()->with('success', 'Assessment finalized. Ready for payment.');
    }
}