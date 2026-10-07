<?php

namespace App\Http\Controllers\Evaluation;

use App\Enums\AcademicStanding;
use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\ShiftRequestStatus;
use App\Enums\StudentType;
use App\Http\Controllers\Controller;
use App\Models\Academicterms;
use App\Models\Courses;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Shiftingrequests;
use App\Models\Students;
use App\Services\EnrollmentIssuer;
use App\Services\EnrollmentStateMachine;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shift request: a SEAIT student moving from one program to another (ruling 11, G-7).
 *
 * The flow is application-shaped — a paper the student signs, endorsed upward, decided by
 * an office that is not the one asking — but it starts at Academic Department Evaluation
 * rather than the admissions desk, because a student already enrolled here is not an
 * applicant (§11). Three hands sign it: the department files the student's declared will,
 * the dean or program head endorses it, and the Guidance Councillor's signature is the
 * final call either way.
 *
 * What the grant does is the part the system could not do before: it issues the receiving
 * enrollment as `studentType = shifter`, retires the seat the student held in the program
 * they are leaving, and leaves both enrollments named in one row — which is the history
 * §21.4 said no table held. Proof of readiness is this paper plus the credit evaluation the
 * receiving department performs on the new enrollment; a shift does not sit a retention or
 * course examination again (§13.6, and `EvaluationController::retentionBlocker()`).
 */
class ShiftRequestController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private EnrollmentIssuer $issuer,
        private EnrollmentStateMachine $stateMachine
    ) {}

    /**
     * The shift docket every desk in the flow reads.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Shiftingrequests::class);

        $requests = Shiftingrequests::with([
            'student', 'currentCourse', 'targetCourse', 'term.academicYear',
            'currentEnrollment.course', 'grantedEnrollment',
            'requestedByUser', 'departmentSignedByUser', 'decisionByUser',
        ])
            ->when($request->status, fn ($q, $status) => $q->where('requestStatus', $status))
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq
                ->where('lastName', 'like', "%{$search}%")
                ->orWhere('firstName', 'like', "%{$search}%")
                ->orWhere('schoolIdNumber', $search)))
            ->orderByRaw("CASE requestStatus WHEN 'endorsed' THEN 0 WHEN 'pending' THEN 1 WHEN 'granted' THEN 2 ELSE 3 END")
            ->latest('shiftingRequestId')
            ->paginate(20)
            ->withQueryString();

        $user = $request->user();

        return Inertia::render('Evaluation/ShiftRequests', [
            'requests' => $requests,
            'filters' => $request->only(['search', 'status']),
            // Only a desk that can file the paper is given the pick-lists, and the
            // candidates are limited to students who already hold a program to leave.
            'students' => $user->checkPermissionTo('shift.request.create')
                ? Students::whereHas('enrollments')
                    ->with(['enrollments' => fn ($q) => $q->whereNotIn('enrollmentStatus', [EnrollmentStatus::Dropped->value])
                        ->latest('termId')->limit(1)->with('course:courseId,courseCode,courseName')])
                    ->orderBy('lastName')->orderBy('firstName')
                    ->get(['studentId', 'schoolIdNumber', 'firstName', 'middleName', 'lastName'])
                : [],
            'courses' => Courses::orderBy('courseName')->get(['courseId', 'courseCode', 'courseName']),
            'terms' => Academicterms::with('academicYear:academicYearId,yearLabel')->orderByDesc('termId')
                ->get(['termId', 'academicYearId', 'semester', 'startDate', 'endDate']),
            'can' => [
                'file' => $user->checkPermissionTo('shift.request.create'),
                'endorse' => $user->checkPermissionTo('shift.sign.department'),
                'decide' => $user->checkPermissionTo('shift.grant'),
            ],
            'stats' => [
                'pending' => Shiftingrequests::where('requestStatus', ShiftRequestStatus::Pending)->count(),
                'endorsed' => Shiftingrequests::where('requestStatus', ShiftRequestStatus::Endorsed)->count(),
                'granted' => Shiftingrequests::where('requestStatus', ShiftRequestStatus::Granted)->count(),
                'rejected' => Shiftingrequests::where('requestStatus', ShiftRequestStatus::Rejected)->count(),
            ],
        ]);
    }

    /**
     * File the student's declared will to change program.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Shiftingrequests::class);

        $validated = $request->validate([
            'studentId' => 'required|exists:students,studentId',
            'targetCourseId' => 'required|exists:courses,courseId',
            'willStatement' => 'required|string|min:20|max:2000',
        ]);

        $student = Students::findOrFail($validated['studentId']);

        // The program being left. A shift is a move off a real record, so the paper names
        // the enrollment it retires rather than leaving the change to be reconstructed
        // from a differing courseId two terms later (§21.4).
        $current = Enrollments::where('studentId', $student->studentId)
            ->whereNotIn('enrollmentStatus', [EnrollmentStatus::Dropped->value])
            ->latest('termId')
            ->first();

        if ($current === null) {
            return back()->withErrors([
                'studentId' => "{$student->lastName}, {$student->firstName} holds no enrollment to leave — a student coming into SEAIT for the first time is admitted, not shifted.",
            ]);
        }

        if ((int) $current->courseId === (int) $validated['targetCourseId']) {
            return back()->withErrors([
                'targetCourseId' => 'The target program is the one this student is already in — there is nothing to shift.',
            ]);
        }

        // One open paper per student: two requests in flight for the same student would
        // mean two departments asking Guidance for opposite answers.
        $open = Shiftingrequests::where('studentId', $student->studentId)
            ->whereIn('requestStatus', [ShiftRequestStatus::Pending->value, ShiftRequestStatus::Endorsed->value])
            ->first();

        if ($open !== null) {
            return back()->withErrors([
                'studentId' => "Shift request #{$open->shiftingRequestId} for this student is already {$open->requestStatus->value} and waiting on {$open->waitingOn()} — a second paper would ask for the same decision twice.",
            ]);
        }

        Shiftingrequests::create([
            'studentId' => $student->studentId,
            'currentCourseId' => $current->courseId,
            'targetCourseId' => $validated['targetCourseId'],
            'currentEnrollmentId' => $current->enrollmentId,
            'willStatement' => $validated['willStatement'],
            'requestStatus' => ShiftRequestStatus::Pending,
            'requestedBy' => Auth::user()->userId,
            'requestedAt' => now(),
        ]);

        return back()->with('success', 'Shift request filed — it now waits on the dean or program head signature.');
    }

    /**
     * The dean or program head signature, which is also where the receiving department
     * states the term and year level the shift lands the student at.
     */
    public function endorse(Request $request, Shiftingrequests $shiftRequest): RedirectResponse
    {
        $this->authorize('endorse', $shiftRequest);

        $validated = $request->validate([
            'termId' => 'required|exists:academicterms,termId',
            'yearLevel' => 'required|integer|min:1|max:5',
        ]);

        $shiftRequest->update([
            'termId' => $validated['termId'],
            'yearLevel' => $validated['yearLevel'],
            'departmentSignedBy' => Auth::user()->userId,
            'departmentSignedAt' => now(),
            'requestStatus' => ShiftRequestStatus::Endorsed,
        ]);

        return back()->with('success', 'Endorsed — the paper now waits on the Guidance Councillor, whose signature is the final call.');
    }

    /**
     * The Guidance Councillor's signature: the final call, granting or refusing.
     *
     * On grant, the receiving enrollment is issued and the seat the student held in the
     * program they are leaving is retired. The retirement is done here rather than left to
     * the Registrar's drop (ruling 17) because this paper *is* the documented reason: the
     * student's own will, the department head's endorsement and Guidance's decision are on
     * the row, and the state machine writes who moved the record and when beside it.
     */
    public function decide(Request $request, Shiftingrequests $shiftRequest): RedirectResponse
    {
        $this->authorize('decide', $shiftRequest);

        $validated = $request->validate([
            'decision' => 'required|in:grant,reject',
            'remarks' => 'nullable|required_if:decision,reject|string|min:10|max:500',
        ]);

        if ($validated['decision'] === 'reject') {
            $shiftRequest->update([
                'requestStatus' => ShiftRequestStatus::Rejected,
                'decisionBy' => Auth::user()->userId,
                'decidedAt' => now(),
                'decisionRemarks' => $validated['remarks'],
            ]);

            return back()->with('success', 'Shift refused — the student stays in their current program.');
        }

        $termId = (int) $shiftRequest->termId;
        $yearLevel = (int) $shiftRequest->yearLevel;

        if ($termId === 0 || $yearLevel === 0) {
            // The endorsement step is what states the term and the level; a paper that
            // reached Guidance without them cannot be granted into a seat.
            return back()->withErrors([
                'decision' => 'This request has no receiving term and year level — the dean or program head has to endorse it with both before it can be granted.',
            ]);
        }

        // Every active enrollment in the receiving term has to be accounted for: this
        // paper retires the one it names, and anything else in that term is somebody
        // else's decision. A dataset that already holds two active rows for the seat is
        // the G-4 defect, and granting into it would compound it rather than fix it.
        $held = Enrollments::where('studentId', $shiftRequest->studentId)
            ->where('termId', $termId)
            ->whereNotIn('enrollmentStatus', [EnrollmentStatus::Dropped->value])
            ->get()
            ->reject(fn (Enrollments $enrollment) => (int) $enrollment->enrollmentId === (int) $shiftRequest->currentEnrollmentId);

        if ($held->isNotEmpty()) {
            $foreign = $held->first();

            return back()->withErrors([
                'decision' => "The seat in this term is also held by enrollment #{$foreign->enrollmentId} ({$foreign->enrollmentStatus->value}), which is not the record this shift leaves. That enrollment is a different decision — the Registrar has to drop it before a shift can take the seat.",
            ]);
        }

        $retiring = Enrollments::find($shiftRequest->currentEnrollmentId);
        $isSameTermAsReceiving = $retiring !== null && (int) $retiring->termId === $termId;

        $student = Students::findOrFail($shiftRequest->studentId);

        DB::transaction(function () use ($shiftRequest, $retiring, $isSameTermAsReceiving, $student, $termId, $yearLevel, $validated) {
            if ($isSameTermAsReceiving) {
                $this->stateMachine->transition(
                    $retiring,
                    EnrollmentStatus::Dropped,
                    Auth::user(),
                    "Shift approved: moved from course {$shiftRequest->currentCourseId} to course {$shiftRequest->targetCourseId} (shift request #{$shiftRequest->shiftingRequestId})"
                );

                // The block seat goes with the record: Blocking weighs distinct students
                // who are not dropped, so a student who left the program stops holding a
                // place in its section the moment the shift is granted.
                Enrolledsubjects::where('enrollmentId', $retiring->enrollmentId)
                    ->whereIn('status', [
                        EnrolledSubjectStatus::Proposed->value,
                        EnrolledSubjectStatus::Confirmed->value,
                    ])
                    ->update(['status' => EnrolledSubjectStatus::Dropped->value]);
            }

            $issued = $this->issuer->issue([
                'studentId' => $student->studentId,
                'courseId' => $shiftRequest->targetCourseId,
                'majorId' => null,
                'termId' => $termId,
                'yearLevel' => $yearLevel,
                'admissionId' => null,
                'studentType' => StudentType::Shifter,
                // C-2, ruled 2026-10-06: a program change — into another department or within
                // the same one — makes the student Irregular, because the load they carry in was
                // not earned as this program's regular progression. The receiving department
                // still credits subject by subject, and the standing report keeps deriving from
                // those grades; what no longer happens is the new record reading regular by
                // default while half its subjects came from elsewhere.
                'academicStanding' => StudentType::Shifter->arrivesIrregular()
                    ? AcademicStanding::Irregular
                    : null,
                'enrollmentType' => EnrollmentType::Old,
            ], Auth::user());

            $shiftRequest->update([
                'requestStatus' => ShiftRequestStatus::Granted,
                'grantedEnrollmentId' => $issued->enrollmentId,
                'decisionBy' => Auth::user()->userId,
                'decidedAt' => now(),
                'decisionRemarks' => $validated['remarks'] ?? null,
            ]);
        });

        return back()->with('success', "Shift granted — {$student->lastName}, {$student->firstName} is enrolled as a shifter in the receiving program, and the load is on Department Evaluation for credit evaluation.");
    }
}
