<?php

namespace App\Http\Controllers;

use App\Enums\AdmissionStatus;
use App\Enums\ClearanceOverallStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\IdRequestStatus;
use App\Enums\OfficeId;
use App\Enums\WorkflowStepStatus;
use App\Models\Academicterms;
use App\Models\Admissions;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Idrequests;
use App\Models\Payments;
use App\Models\Staffusers;
use App\Models\Studentclearances;
use App\Models\Students;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the role-aware dashboard with live stats.
     */
    public function __invoke(Request $request): Response
    {
        // Item 2: the admin System Admin dashboard tab shows a per-applicant
        // progress workflow view — recent enrollments with their 6-7 step
        // workflow progress — instead of module link tiles. Gated to the
        // Admin role (the tab is admin-only in the UI) AND students.view,
        // so the payload never ships to a non-admin office head who holds
        // students.view but cannot see the tab, and the links below always
        // resolve (students.show requires students.view too).
        $progressTracking = [];
        $user = $request->user();
        if ($user !== null && $user->role->value === 'admin' && $user->can('viewAny', Students::class)) {
            $progressTracking = Enrollments::with(['student', 'course', 'enrollmentworkflow.workflowsteps'])
                ->latest('enrollmentId')
                ->limit(8)
                ->get()
                ->map(function (Enrollments $e) {
                    // stepStatus is cast to an enum, so these must compare enum
                    // instances — a string comparison against the cast value never
                    // matched, which left every row reading "0/N steps signed" with
                    // no phase on it.
                    $steps = $e->enrollmentworkflow?->workflowsteps->sortBy('stepOrder') ?? collect();
                    $pendingStep = $steps->first(fn ($step) => $step->stepStatus === WorkflowStepStatus::Pending);

                    return [
                        'enrollmentId' => $e->enrollmentId,
                        'studentId' => $e->studentId,
                        'studentName' => $e->student
                            ? $e->student->lastName.', '.$e->student->firstName
                            : '—',
                        'courseCode' => $e->course?->courseCode,
                        'enrollmentStatus' => $e->enrollmentStatus->value,
                        'totalSteps' => $steps->count(),
                        'completedSteps' => $steps->filter(fn ($step) => $step->stepStatus === WorkflowStepStatus::Completed)->count(),
                        'currentPhase' => $pendingStep === null
                            ? null
                            : (WorkflowService::stepLabels()[$pendingStep->officeId] ?? null),
                    ];
                })
                ->values()
                ->all();
        }

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalAdmissions' => Admissions::count(),
                'pendingEvaluations' => Enrollments::where('enrollmentStatus', EnrollmentStatus::Pending->value)->count(),
                'enrolledStudents' => Enrollments::where('enrollmentStatus', EnrollmentStatus::Enrolled->value)->count(),
                'monthlyRevenue' => Payments::held()
                    ->whereMonth('paymentDate', now()->month)
                    ->whereYear('paymentDate', now()->year)
                    ->sum('amount'),
                'totalStaff' => Staffusers::count(),
                // Count terms active TODAY by date range — not by the
                // currentTermId setting, which may be unset (audit 2026-09-25).
                'activeTerms' => Academicterms::whereDate('startDate', '<=', now())->whereDate('endDate', '>=', now())->count(),
                'totalCourses' => Courses::count(),
            ],
            'progressTracking' => $progressTracking,
        ]);
    }

    /**
     * Per-office queue counts for live desk polling (JSON).
     */
    public function queueCounts(Request $request): JsonResponse
    {
        return response()->json([
            'queueCounts' => [
                'admission' => Admissions::where('admissionStatus', AdmissionStatus::Pending->value)->count(),
                'evaluation' => Enrollments::where('enrollmentStatus', EnrollmentStatus::Pending->value)->count(),
                'assessment' => Enrollments::where('enrollmentStatus', EnrollmentStatus::Evaluated->value)->count(),
                'accounting' => Enrollments::where('enrollmentStatus', EnrollmentStatus::Assessed->value)->count(),
                // Mirror RegistrarController::index — the registrar queue holds
                // both assessed (payment collected, awaiting approval) and paid
                // enrollments, so the dashboard badge must match what the desk sees.
                'registrar' => Enrollments::whereIn('enrollmentStatus', [EnrollmentStatus::Assessed->value, EnrollmentStatus::Paid->value])->count(),
                // First-pending-step logic (mirrors ClinicController::index): the
                // stepper only marks the FIRST pending step current, so an enrollment
                // with both blocking and clinic steps pending must count once — at
                // the desk whose step is first — not in both queues.
                'blocking' => Enrollments::where('enrollmentStatus', EnrollmentStatus::Enrolled->value)
                    ->whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q
                        ->where('stepStatus', WorkflowStepStatus::Pending->value)
                        ->where('officeId', OfficeId::Blocking->value)
                        ->whereRaw('stepOrder = (SELECT MIN(ws.stepOrder) FROM workflowsteps ws WHERE ws.workflowId = workflowsteps.workflowId AND ws.stepStatus = ?)', ['pending'])
                    )
                    ->count(),
                'clinic' => Enrollments::where('enrollmentStatus', EnrollmentStatus::Enrolled->value)
                    ->whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q
                        ->where('stepStatus', WorkflowStepStatus::Pending->value)
                        ->where('officeId', OfficeId::Clinic->value)
                        ->whereRaw('stepOrder = (SELECT MIN(ws.stepOrder) FROM workflowsteps ws WHERE ws.workflowId = workflowsteps.workflowId AND ws.stepStatus = ?)', ['pending'])
                    )
                    ->count(),
                'id' => Idrequests::where('status', IdRequestStatus::Pending->value)->count(),
                'clearance' => Studentclearances::where('overallStatus', ClearanceOverallStatus::Pending->value)->count(),
            ],
        ]);
    }
}
