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

        $query = Examresults::with(['student', 'course', 'term'])
            ->when($request->stage, fn ($q, $stage) => $q->where('examStage', $stage))
            ->when($request->type, fn ($q, $type) => $q->where('examType', $type))
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->orderByDesc('examId');

        $exams = $query->paginate(20)->withQueryString();

        return Inertia::render('Exam/Index', [
            'exams' => $exams,
            'filters' => $request->only(['stage', 'type', 'search']),
        ]);
    }

    /**
     * Show exam recording form.
     */
    public function create(Request $request): Response
    {
        $this->authorize('exam.record', [Courses::class, ExamStage::Entrance, ExamType::General]);

        $courseId = $request->courseId;
        $termId = $request->termId;

        return Inertia::render('Exam/Create', [
            'courses' => Courses::where('requiresEntranceExam', true)->get(['courseId', 'courseName', 'courseCode']),
            'terms' => Academicterms::with('academicYear')->get(['termId', 'semester', 'academicYearId']),
            'selectedCourse' => $courseId ? Courses::find($courseId) : null,
            'selectedTerm' => $termId ? Academicterms::find($termId) : null,
            'stage' => $request->stage ?? 'entrance',
            'type' => $request->type ?? 'general',
        ]);
    }

    /**
     * Return students enrolled in a course/term for exam recording.
     */
    public function students(Request $request): JsonResponse
    {
        $this->authorize('exam.record', [Courses::class, ExamStage::Entrance, ExamType::General]);

        if (! $request->filled('courseId') || ! $request->filled('termId')) {
            return response()->json(['students' => []]);
        }

        $request->validate([
            'courseId' => 'required|exists:courses,courseId',
            'termId' => 'required|exists:academicterms,termId',
        ]);

        $students = Students::whereHas('enrollments', fn ($q) => $q
            ->where('courseId', $request->courseId)
            ->where('termId', $request->termId)
            ->where('enrollmentStatus', 'enrolled')
        )->get(['studentId', 'schoolIdNumber', 'lastName', 'firstName', 'middleName']);

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

        Examresults::create([
            'studentId' => $validated['studentId'],
            'courseId' => $validated['courseId'],
            'termId' => $validated['termId'],
            'examStage' => ExamStage::Entrance,
            'examType' => ExamType::General,
            'examResult' => $validated['examResult'],
            'examDate' => $validated['examDate'],
        ]);

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
     * Record retention exam (Board course continuing students).
     * BR10: Required for continuing board-course students
     */
    public function recordRetention(Request $request): RedirectResponse
    {
        $this->authorize('exam.record', [Courses::class, ExamStage::Retention, ExamType::CourseSpecific]);

        $validated = $request->validate([
            'studentId' => 'required|exists:students,studentId',
            'courseId' => 'required|exists:courses,courseId',
            'termId' => 'required|exists:academicterms,termId',
            'examResult' => 'required|in:pass,fail',
            'examDate' => 'required|date',
        ]);

        $course = Courses::findOrFail($validated['courseId']);
        if (! $course->requiresRetentionExam) {
            return back()->withErrors(['courseId' => 'This course does not require a retention exam.']);
        }

        Examresults::create([
            'studentId' => $validated['studentId'],
            'courseId' => $validated['courseId'],
            'termId' => $validated['termId'],
            'examStage' => ExamStage::Retention,
            'examType' => ExamType::CourseSpecific,
            'examResult' => $validated['examResult'],
            'examDate' => $validated['examDate'],
        ]);

        return redirect()->route('exam.index')->with('success', 'Retention exam recorded.');
    }

    /**
     * Show pass/fail lists.
     */
    public function results(Request $request): Response
    {
        $this->authorize('viewAny', Examresults::class);

        $query = Examresults::with(['student', 'course', 'term'])
            ->when($request->stage, fn ($q, $stage) => $q->where('examStage', $stage))
            ->when($request->result, fn ($q, $result) => $q->where('examResult', $result))
            ->orderByDesc('examId');

        $exams = $query->paginate(50)->withQueryString();

        return Inertia::render('Exam/Results', [
            'exams' => $exams,
            'filters' => $request->only(['stage', 'result']),
        ]);
    }
}
