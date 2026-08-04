<?php

namespace App\Http\Controllers;

use App\Enums\AdmissionStatus;
use App\Enums\ClearanceOverallStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\IdRequestStatus;
use App\Models\Academicterms;
use App\Models\Admissions;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Idrequests;
use App\Models\Payments;
use App\Models\Settings;
use App\Models\Staffusers;
use App\Models\Studentclearances;
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
        $currentTermId = Settings::where('settingKey', 'currentTermId')->value('settingValue');

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalAdmissions' => Admissions::count(),
                'pendingEvaluations' => Enrollments::where('enrollmentStatus', 'pending')->count(),
                'enrolledStudents' => Enrollments::where('enrollmentStatus', 'enrolled')->count(),
                'monthlyRevenue' => Payments::where('paymentStatus', 'paid')
                    ->whereMonth('paymentDate', now()->month)
                    ->whereYear('paymentDate', now()->year)
                    ->sum('amount'),
                'totalStaff' => Staffusers::count(),
                'activeTerms' => $currentTermId ? Academicterms::where('termId', $currentTermId)->count() : 0,
                'totalCourses' => Courses::count(),
            ],
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
                'registrar' => Enrollments::where('enrollmentStatus', EnrollmentStatus::Paid->value)->count(),
                'blocking' => Enrollments::where('enrollmentStatus', EnrollmentStatus::Enrolled->value)
                    ->whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q->where('stepStatus', 'pending')->where('officeId', 5))
                    ->count(),
                'clinic' => Enrollments::where('enrollmentStatus', EnrollmentStatus::Enrolled->value)
                    ->whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q->where('stepStatus', 'pending')->where('officeId', 11))
                    ->count(),
                'id' => Idrequests::where('status', IdRequestStatus::Pending->value)->count(),
                'clearance' => Studentclearances::where('overallStatus', ClearanceOverallStatus::Pending->value)->count(),
            ],
        ]);
    }
}
