<?php

namespace App\Http\Controllers;

use App\Models\Academicterms;
use App\Models\Admissions;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Payments;
use App\Models\Settings;
use App\Models\Staffusers;
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
}
