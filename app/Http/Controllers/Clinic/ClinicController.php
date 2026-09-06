<?php

namespace App\Http\Controllers\Clinic;

use App\Enums\ClinicRecordStatus;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Clinicrecords;
use App\Models\Enrollments;
use App\Services\WorkflowService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClinicController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private WorkflowService $workflowService
    ) {}

    /**
     * Display clinic queue.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Clinicrecords::class);

        $query = Enrollments::with(['student', 'course', 'term', 'clinicrecords'])
            ->where('enrollmentStatus', EnrollmentStatus::Enrolled)
            ->whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q
                ->where('stepStatus', 'pending')
                ->where('officeId', 11)
                ->whereRaw('stepOrder = (SELECT MIN(ws.stepOrder) FROM workflowsteps ws WHERE ws.workflowId = workflowsteps.workflowId AND ws.stepStatus = ?)', ['pending'])
            )
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->orderByDesc('enrollmentId');

        $enrollments = $query->paginate(20)->withQueryString();

        return Inertia::render('Clinic/Index', [
            'enrollments' => $enrollments,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show clinic assessment form.
     * Phase 7: Physical exam, PhilHealth, hard-copy assessments
     */
    public function show(Enrollments $enrollment): Response
    {
        $this->authorize('clinic.view', $enrollment);

        $enrollment->load(['student', 'course', 'term', 'clinicrecords', 'enrollmentworkflow.workflowsteps']);

        $clinicRecord = $enrollment->clinicrecords->first();

        return Inertia::render('Clinic/Show', [
            'enrollment' => $enrollment,
            'clinicRecord' => $clinicRecord,
        ]);
    }

    /**
     * Record clinic assessment.
     */
    public function record(Request $request, Enrollments $enrollment): RedirectResponse
    {
        // Ability name avoids the Spatie permission-name collision — see the
        // gate definition in AuthServiceProvider (office-11 scoping must run).
        $this->authorize('clinic.recordAssessment', $enrollment);

        $validated = $request->validate([
            'heightCm' => 'required|numeric|min:0|max:300',
            'weightKg' => 'required|numeric|min:0|max:300',
            'bloodPressure' => 'required|string|max:20',
            'philhealthNumber' => 'nullable|string|max:50',
            'philhealthRegistered' => 'boolean',
            'assessmentNotes' => 'nullable|string',
            'findings' => 'nullable|string',
            'assessmentDate' => 'required|date',
        ]);

        DB::transaction(function () use ($enrollment, $validated) {
            $clinicRecord = Clinicrecords::updateOrCreate(
                ['enrollmentId' => $enrollment->enrollmentId],
                array_merge($validated, [
                    'clinicStaffId' => Auth::user()->userId,
                    'status' => ClinicRecordStatus::Completed,
                ])
            );

            // Sign workflow step 7 (Clinic)
            $workflow = $enrollment->enrollmentworkflow;
            if ($workflow) {
                $this->workflowService->signStepByOffice($workflow, 11, Auth::user());
            }
        });

        return redirect()->route('clinic.index')->with('success', 'Clinic assessment recorded.');
    }

    /**
     * Update clinic record.
     */
    public function update(Request $request, Clinicrecords $clinic): RedirectResponse
    {
        $this->authorize('update', $clinic);

        $validated = $request->validate([
            'heightCm' => 'nullable|numeric|min:0|max:300',
            'weightKg' => 'nullable|numeric|min:0|max:300',
            'bloodPressure' => 'nullable|string|max:20',
            'philhealthNumber' => 'nullable|string|max:50',
            'philhealthRegistered' => 'boolean',
            'assessmentNotes' => 'nullable|string',
            'findings' => 'nullable|string',
            'assessmentDate' => 'nullable|date',
        ]);

        $clinic->update($validated);

        return back()->with('success', 'Clinic record updated.');
    }

    /**
     * Reopen a completed clinic record.
     */
    public function reopen(Clinicrecords $clinic): RedirectResponse
    {
        $this->authorize('reopen', $clinic);

        DB::transaction(function () use ($clinic) {
            $clinic->update([
                'status' => ClinicRecordStatus::Reopened,
            ]);
        });

        return back()->with('success', 'Clinic record reopened for editing.');
    }
}
