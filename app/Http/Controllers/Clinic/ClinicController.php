<?php

namespace App\Http\Controllers\Clinic;

use App\Enums\ClinicRecordStatus;
use App\Enums\OfficeId;
use App\Http\Controllers\Controller;
use App\Models\Clinicrecords;
use App\Models\Enrollments;
use App\Services\WorkflowService;
use App\Support\DeskQueues;
use App\Support\ListSort;
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

        // The queue's population comes from App\Support\DeskQueues, which is the same builder the dashboard's
        // clinic badge counts — one definition of "waiting at the clinic box", not two that must be kept in step.
        $query = DeskQueues::clinic()
            ->with(['student', 'course', 'term', 'clinicrecords'])
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)));

        // The tiles describe the whole queue, not the 20 rows on the page: "how many of these students still
        // need an assessment" is a question about the queue. Cloned before paginate(), because paginate() puts
        // the page's LIMIT/OFFSET on the builder it is called on — reorder() keeps the aggregate clear of the
        // list's ORDER BY, which ONLY_FULL_GROUP_BY would otherwise refuse. They are also cloned before the
        // order is applied: a name sort joins `students`, and a count must not learn about a join it never needed.
        $queueSize = (clone $query)->reorder()->count();
        $assessed = (clone $query)->reorder()->has('clinicrecords')->count();

        // Student Type and Standing are deliberately absent: both are ENUM columns, so the school's MySQL orders
        // them by the index each was declared with while the suite's `enum()` is a varchar with a CHECK and orders
        // the stored word. A header whose sequence depends on which server answered is not an order the desk can
        // be given, and the vocabulary question those two columns raise is the school's, not a query's.
        $sortable = [
            'name' => ['column' => 'lastName', 'join' => 'students', 'foreign' => 'studentId', 'local' => 'enrollments.studentId'],
            'schoolId' => ['column' => 'schoolIdNumber', 'join' => 'students', 'foreign' => 'studentId', 'local' => 'enrollments.studentId'],
            'course' => ['column' => 'courseName', 'join' => 'courses', 'foreign' => 'courseId', 'local' => 'enrollments.courseId'],
            'enrollmentId' => ['column' => 'enrollments.enrollmentId'],
        ];

        $sort = ListSort::apply(
            $query,
            (string) $request->input('sort', ''),
            (string) $request->input('direction', ''),
            $sortable,
            'enrollmentId',
            'enrollments.enrollmentId'
        );

        $enrollments = $query->paginate(20)->withQueryString();

        return Inertia::render('Clinic/Index', [
            'enrollments' => $enrollments,
            'stats' => [
                'queue' => $queueSize,
                'completed' => $assessed,
                'pending' => max(0, $queueSize - $assessed),
            ],
            // The order the queue applied comes back beside the search the desk typed, so neither control resets
            // while the list stays narrowed by the other.
            'filters' => $request->only(['search']) + [
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
            ],
            'sort' => ListSort::present($sort, $sortable),
        ]);
    }

    /**
     * Show clinic assessment form.
     * Phase 7: Physical exam, PhilHealth, hard-copy assessments
     */
    public function show(Enrollments $enrollment): Response
    {
        $this->authorize('clinic.viewAtDesk', $enrollment);

        $enrollment->load(['student', 'course', 'term', 'clinicrecords', 'enrollmentworkflow.workflowsteps.office', 'enrollmentworkflow.workflowsteps.signedBy']);

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
                $this->workflowService->signStepByOffice($workflow, OfficeId::Clinic->value, Auth::user());
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
