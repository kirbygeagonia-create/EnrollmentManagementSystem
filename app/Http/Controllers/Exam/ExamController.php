<?php

namespace App\Http\Controllers\Exam;

use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamType;
use App\Http\Controllers\Controller;
use App\Models\Academicterms;
use App\Models\Admissions;
use App\Models\Courses;
use App\Models\Examresults;
use App\Models\Students;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExamController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display exam recording screen.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Examresults::class);

        $user = $request->user();
        $canGeneral = $user->hasPermissionTo('exam.record.general');
        $canCourseSpecific = $user->hasPermissionTo('exam.record.courseSpecific');
        // Course-specific and retention results are handled and viewed only by
        // the owning academic department (item 4).
        $isDepartment = $canCourseSpecific || $user->hasPermissionTo('exam.record.retention');

        $query = Examresults::with(['student', 'course', 'term'])
            ->when($request->stage, fn ($q, $stage) => $q->where('examStage', $stage))
            ->when($request->type, fn ($q, $type) => $q->where('examType', $type))
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->orderByDesc('examId');

        // Item 4 ownership: Guidance handles and views the School Entrance
        // Examination only — all of its results, pass and failed. Course-specific
        // and retention results are handled and viewed only by the academic
        // department. What crosses the boundary is the passer transfer (BR9):
        // general-stage PASS rows surface to the department as transferred
        // passers — names and results only, never the failed ones.
        if ($canGeneral && $isDepartment) {
            // SysAdmin (both scopes): the full matrix.
        } elseif ($canGeneral) {
            $query->where('examStage', ExamStage::Entrance->value)
                ->where('examType', ExamType::General->value);
        } elseif ($isDepartment) {
            $query->where('examStage', ExamStage::Entrance->value)
                ->where(function ($q) {
                    $q->where('examType', ExamType::CourseSpecific->value)
                        ->orWhere(fn ($sq) => $sq
                            ->where('examType', ExamType::General->value)
                            ->where('examResult', ExamResult::Pass->value));
                });
        } else {
            // View-only: the transferred passer roster.
            $query->where('examType', ExamType::General->value)
                ->where('examResult', ExamResult::Pass->value);
        }

        $exams = $query->paginate(20)->withQueryString();

        return Inertia::render('Exam/Index', [
            'exams' => $exams,
            'filters' => $request->only(['stage', 'type', 'search']),
            'can' => [
                'recordGeneral' => $canGeneral,
                'recordCourseSpecific' => $canCourseSpecific,
            ],
        ]);
    }

    /**
     * Show exam recording form.
     */
    public function create(Request $request): Response
    {
        // Item 4: the Exam module is entrance examinations only — the retention
        // exam is recorded in the Academic Evaluation area by the owning
        // department (BR10).
        abort_unless(ExamStage::tryFrom($request->stage ?? 'entrance') === ExamStage::Entrance, 404);

        $type = ExamType::tryFrom($request->type ?? 'general') ?? ExamType::General;

        // The entrance form is gated by the requested recording permission:
        // general = Guidance (Stage 1), course-specific = the owning academic
        // department (Stage 2) — BR9.
        $this->authorize('exam.record', [Courses::class, ExamStage::Entrance, $type]);

        $courseId = $request->courseId;
        $termId = $request->termId;

        // Course list: entrance-exam courses only.
        $courses = Courses::where('requiresEntranceExam', true);

        return Inertia::render('Exam/Create', [
            'courses' => $courses->get(['courseId', 'courseName', 'courseCode']),
            'terms' => Academicterms::with('academicYear')->get(['termId', 'semester', 'academicYearId']),
            'selectedCourse' => $courseId ? Courses::find($courseId) : null,
            'selectedTerm' => $termId ? Academicterms::find($termId) : null,
            'stage' => ExamStage::Entrance->value,
            'type' => $type->value,
        ]);
    }

    /**
     * Return exam candidates for a course/term:
     *  - general (School Entrance): first-year applicants admitted to the
     *    course/term, not yet enrolled — the exam runs from before enrollment
     *    opens up to enrollment day.
     *  - course-specific: the School Entrance passers Guidance transferred to
     *    the department (the BR9 passer transfer — names and results only).
     */
    public function students(Request $request): JsonResponse
    {
        $type = ExamType::tryFrom($request->input('type', 'general')) ?? ExamType::General;

        $this->authorize('exam.record', [Courses::class, ExamStage::Entrance, $type]);

        if (! $request->filled('courseId') || ! $request->filled('termId')) {
            return response()->json(['students' => []]);
        }

        $request->validate([
            'courseId' => 'required|exists:courses,courseId',
            'termId' => 'required|exists:academicterms,termId',
        ]);

        if ($type === ExamType::CourseSpecific) {
            // Item 4 — the passer transfer (BR9): these are the School Entrance
            // Examination passers. Students are not applicants at this stage —
            // only names and results are shown.
            $passers = Examresults::with('student')
                ->where('courseId', $request->courseId)
                ->where('termId', $request->termId)
                ->where('examStage', ExamStage::Entrance->value)
                ->where('examType', ExamType::General->value)
                ->where('examResult', ExamResult::Pass->value)
                ->get();

            $students = $passers->map(fn ($result) => [
                'studentId' => $result->student->studentId,
                'schoolIdNumber' => $result->student->schoolIdNumber,
                'lastName' => $result->student->lastName,
                'firstName' => $result->student->firstName,
                'middleName' => $result->student->middleName,
                'examResult' => $result->examResult->value,
            ])->values();
        } else {
            // Entrance candidates: students admitted to the course/term who have
            // not yet enrolled (exam happens between admission and evaluation).
            $students = Students::whereHas('admissions', fn ($q) => $q
                ->where('courseId', $request->courseId)
                ->where('termId', $request->termId)
                ->whereIn('admissionStatus', ['pending', 'approved'])
            )->whereDoesntHave('enrollments', fn ($q) => $q
                ->where('termId', $request->termId)
            )->get(['studentId', 'schoolIdNumber', 'lastName', 'firstName', 'middleName']);
        }

        return response()->json(['students' => $students]);
    }

    /**
     * Record general entrance exam (Guidance Office).
     */
    public function recordGeneral(Request $request): RedirectResponse
    {
        $this->authorize('exam.record', [Courses::class, ExamStage::Entrance, ExamType::General]);

        $validated = $request->validate([
            'studentId' => 'required|exists:students,studentId',
            'courseId' => 'required|exists:courses,courseId',
            'termId' => 'required|exists:academicterms,termId',
            'examResult' => 'required|in:pass,fail',
            'examDate' => 'required|date',
        ]);

        $course = Courses::findOrFail($validated['courseId']);
        if (! $course->requiresEntranceExam) {
            return back()->withErrors(['courseId' => 'This course does not require an entrance exam.']);
        }

        // firstOrNew: re-recording corrects the existing result rather than
        // creating a duplicate (mirrors the retention path).
        $exam = Examresults::firstOrNew([
            'studentId' => $validated['studentId'],
            'courseId' => $validated['courseId'],
            'termId' => $validated['termId'],
            'examStage' => ExamStage::Entrance,
            'examType' => ExamType::General,
        ]);
        $exam->fill([
            'examResult' => $validated['examResult'],
            'examDate' => $validated['examDate'],
        ]);
        $exam->save();

        // Update admission status if failed
        if ($validated['examResult'] === 'fail') {
            Admissions::where('studentId', $validated['studentId'])
                ->where('courseId', $validated['courseId'])
                ->where('termId', $validated['termId'])
                ->update(['admissionStatus' => 'rejected']);
        }

        return redirect()->route('exam.index')->with('success', 'General entrance exam recorded.');
    }

    /**
     * Record course-specific entrance exam (Department).
     * BR9: Verifies Guidance result first
     */
    public function recordCourseSpecific(Request $request): RedirectResponse
    {
        $this->authorize('exam.record', [Courses::class, ExamStage::Entrance, ExamType::CourseSpecific]);

        $validated = $request->validate([
            'studentId' => 'required|exists:students,studentId',
            'courseId' => 'required|exists:courses,courseId',
            'termId' => 'required|exists:academicterms,termId',
            'examResult' => 'required|in:pass,fail',
            'examDate' => 'required|date',
        ]);

        // Verify general exam passed first
        $generalExam = Examresults::where('studentId', $validated['studentId'])
            ->where('courseId', $validated['courseId'])
            ->where('termId', $validated['termId'])
            ->where('examStage', ExamStage::Entrance)
            ->where('examType', ExamType::General)
            ->first();

        if (! $generalExam || $generalExam->examResult !== ExamResult::Pass) {
            return back()->withErrors(['generalExam' => 'General entrance exam must be passed first.']);
        }

        $course = Courses::findOrFail($validated['courseId']);
        if (! $course->requiresEntranceExam) {
            return back()->withErrors(['courseId' => 'This course does not require an entrance exam.']);
        }

        Examresults::create([
            'studentId' => $validated['studentId'],
            'courseId' => $validated['courseId'],
            'termId' => $validated['termId'],
            'examStage' => ExamStage::Entrance,
            'examType' => ExamType::CourseSpecific,
            'examResult' => $validated['examResult'],
            'examDate' => $validated['examDate'],
        ]);

        // Update admission status
        $admission = Admissions::where('studentId', $validated['studentId'])
            ->where('courseId', $validated['courseId'])
            ->where('termId', $validated['termId'])
            ->first();

        if ($admission) {
            $admission->update([
                'admissionStatus' => $validated['examResult'] === 'pass' ? 'approved' : 'rejected',
            ]);
        }

        return redirect()->route('exam.index')->with('success', 'Course-specific entrance exam recorded.');
    }

    /**
     * Show pass/fail lists.
     */
    public function results(Request $request): Response
    {
        $this->authorize('viewAny', Examresults::class);

        $user = $request->user();
        $canGeneral = $user->hasPermissionTo('exam.record.general');
        // Course-specific and retention results are handled and viewed only by
        // the owning academic department (item 4).
        $isDepartment = $user->hasPermissionTo('exam.record.courseSpecific')
            || $user->hasPermissionTo('exam.record.retention');

        $query = Examresults::with(['student', 'course', 'term'])
            ->when($request->stage, fn ($q, $stage) => $q->where('examStage', $stage))
            ->when($request->result, fn ($q, $result) => $q->where('examResult', $result))
            ->orderByDesc('examId');

        // Same ownership split as index(): Guidance holds every School Entrance
        // result including failed; the department holds its own course-specific
        // results plus the transferred passers (names and results only).
        if ($canGeneral && $isDepartment) {
            // SysAdmin (both scopes): the full matrix.
        } elseif ($canGeneral) {
            $query->where('examStage', ExamStage::Entrance->value)
                ->where('examType', ExamType::General->value);
        } elseif ($isDepartment) {
            $query->where('examStage', ExamStage::Entrance->value)
                ->where(function ($q) {
                    $q->where('examType', ExamType::CourseSpecific->value)
                        ->orWhere(fn ($sq) => $sq
                            ->where('examType', ExamType::General->value)
                            ->where('examResult', ExamResult::Pass->value));
                });
        } else {
            // View-only: the transferred passer roster.
            $query->where('examType', ExamType::General->value)
                ->where('examResult', ExamResult::Pass->value);
        }

        $exams = $query->paginate(50)->withQueryString();

        return Inertia::render('Exam/Results', [
            'exams' => $exams,
            'filters' => $request->only(['result']),
            'can' => [
                'recordGeneral' => $canGeneral,
                'recordCourseSpecific' => $user->hasPermissionTo('exam.record.courseSpecific'),
            ],
        ]);
    }
}
