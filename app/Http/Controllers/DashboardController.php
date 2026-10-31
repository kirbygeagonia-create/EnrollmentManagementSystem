<?php

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Enums\OfficeId;
use App\Enums\WorkflowStepStatus;
use App\Models\Academicterms;
use App\Models\Admissions;
use App\Models\Clinicrecords;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Idrequests;
use App\Models\Payments;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Models\Studentclearances;
use App\Models\Students;
use App\Services\WorkflowService;
use App\Support\DeskQueues;
use App\Support\SchoolTime;
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
            $progressTracking = Enrollments::with(['student:studentId,schoolIdNumber,lastName,firstName,middleName', 'course', 'enrollmentworkflow.workflowsteps'])
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
                    // The month the *school* is in. `now()->month` answered in UTC, so the first eight hours of
                    // every Manila day — a morning of receipts — were counted onto the previous month's tile.
                    ->whereMonth('paymentDate', (int) substr(SchoolTime::today(), 5, 2))
                    ->whereYear('paymentDate', (int) substr(SchoolTime::today(), 0, 4))
                    ->sum('amount'),
                'totalStaff' => Staffusers::count(),
                // Count terms active TODAY by date range — not by the
                // currentTermId setting, which may be unset (audit 2026-09-25). "Today" is the school's date:
                // `now()` is a UTC instant, and comparing a DATE column to it made a term that opens on the 1st
                // look inactive until 08:00 Manila.
                'activeTerms' => Academicterms::whereDate('startDate', '<=', SchoolTime::today())->whereDate('endDate', '>=', SchoolTime::today())->count(),
                'totalCourses' => Courses::count(),
            ],
            'progressTracking' => $progressTracking,
            // The cards the desk route would actually open for this account, so the chrome and the
            // JSON answer to one rule instead of two (see QUEUES).
            'queueKeys' => $this->visibleQueueKeys($user),
        ]);
    }

    /**
     * Each dashboard queue against the authorization its own desk route performs, so the badge and
     * the page behind it can never disagree. Two models carry no import here on purpose: adding a
     * `use` line would shift DashboardController.php:41, which the documentation cites by line.
     *
     * blocking.viewAny is a Gate::define (AuthServiceProvider), not a model policy, so it takes no
     * model argument.
     */
    private const QUEUES = [
        'admission' => ['viewAny', Admissions::class],
        'evaluation' => ['viewAny', Enrollments::class],
        'assessment' => ['viewAny', Studentassessments::class],
        'accounting' => ['viewAny', Payments::class],
        'registrar' => ['viewAny', Enrollments::class],
        'blocking' => ['blocking.viewAny', null],
        'clinic' => ['viewAny', Clinicrecords::class],
        'id' => ['viewAny', Idrequests::class],
        'clearance' => ['viewAny', Studentclearances::class],
    ];

    /**
     * Per-office queue counts for live desk polling (JSON).
     *
     * Measured 2026-10-09: this answered with all nine institution-wide totals to ANY signed-in
     * staff account, while the page itself hides the cards an office does not run. The owner ruled
     * the payload follow the desk, so a count is computed and returned only for a queue whose own
     * index route would open for this user — the same `can()` call, not a parallel list of rights
     * that can drift from it. It also stops the nine queries running for desks the caller cannot
     * reach.
     */
    public function queueCounts(Request $request): JsonResponse
    {
        $user = $request->user();
        $visible = $this->visibleQueueKeys($user);

        $admitted = [];
        foreach ($visible as $key) {
            $admitted[$key] = true;
        }

        return response()->json([
            'queueCounts' => array_intersect_key([
                // Every one of these is the base the desk's own list is built from — see
                // App\Support\DeskQueues. Restating the filters here is what let the badge count the whole
                // school while the page behind it showed one college, and count `Pending` while the desk also
                // listed what the Registrar had sent back.
                'admission' => DeskQueues::admission($user)->count(),
                'evaluation' => DeskQueues::evaluation($user)->count(),
                'assessment' => DeskQueues::assessment()->count(),
                'accounting' => DeskQueues::accounting()->count(),
                'registrar' => DeskQueues::registrar()->count(),
                // First-pending-step logic (shared with ClinicController::index and IDController::index through
                // DeskQueues::waitingAtFirstPendingBox): the stepper only marks the FIRST pending step current,
                // so an enrollment with both blocking and clinic steps pending must count once — at
                // the desk whose step is first — not in both queues.
                //
                // `blocking` is the one badge whose *subject* is still a question rather than a filter: its
                // badge counts enrollments waiting at that office, while BlockingController::index() lists
                // timetable blocks. Which one the card should name is the school's to say — it is item 9 on the
                // 2026-10-29 decision sheet, and the docx register does not hold that question yet. The predicate
                // it waits on, though, is not in question and comes from the same helper.
                'blocking' => DeskQueues::waitingAtFirstPendingBox(OfficeId::Blocking)->count(),
                'clinic' => DeskQueues::clinic()->count(),
                'id' => DeskQueues::id()->count(),
                'clearance' => DeskQueues::clearance()->count(),
            ], $admitted),
        ]);
    }

    /**
     * The queue keys this account may open, decided by the authorization its desk route itself
     * makes. Returning the list to the page is what lets the cards follow the server instead of a
     * second, hand-maintained set of office numbers — the set that still named office 8 after
     * ruling 12 folded it into Registrar, hiding the Clearance queue from every office but one.
     *
     * @return list<string>
     */
    private function visibleQueueKeys(?Staffusers $user): array
    {
        if ($user === null) {
            return [];
        }

        $visible = [];
        foreach (self::QUEUES as $key => [$ability, $model]) {
            if ($model === null ? $user->can($ability) : $user->can($ability, $model)) {
                $visible[] = $key;
            }
        }

        return $visible;
    }
}
