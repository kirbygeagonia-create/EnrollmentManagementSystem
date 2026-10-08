<?php

namespace App\Http\Controllers\Exam;

use App\Enums\AdmissionStatus;
use App\Enums\ApplicantType;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamType;
use App\Http\Controllers\Controller;
use App\Models\Academicterms;
use App\Models\Admissions;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Examresults;
use App\Models\Students;
use Illuminate\Database\Eloquent\Collection;
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

        // An exam record is per (student, term) with no enrollmentId, so the
        // standing this desk reads comes from the matching enrollment in one
        // lookup. Applicants not enrolled yet simply show as undecided.
        $standings = Enrollments::standingMapFor(
            $exams->getCollection()->map(fn (Examresults $e) => [$e->studentId, $e->termId])
        );
        $exams->getCollection()->each(function (Examresults $row) use ($standings) {
            $match = $standings[$row->studentId.'-'.$row->termId] ?? null;
            $row->setAttribute('studentType', $match?->studentType?->value);
            $row->setAttribute('academicStanding', $match?->academicStanding?->value);
        });

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

        $course = Courses::find($request->courseId);

        if ($type === ExamType::CourseSpecific && $course?->requiresEntranceExam) {
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

            $rows = $passers->map(fn ($result) => [
                'studentId' => $result->student->studentId,
                'schoolIdNumber' => $result->student->schoolIdNumber,
                'lastName' => $result->student->lastName,
                'firstName' => $result->student->firstName,
                'middleName' => $result->student->middleName,
                'examResult' => $result->examResult->value,
                'generalExamWaived' => false,
            ])->all();

            // C-4 (ruled 2026-10-07): a program may waive the school-wide General
            // Entrance Examination for the transferees it takes on credit. Those
            // applicants have no passer row to transfer, so without this second
            // query the waiver would be a trap: the department could never reach
            // them on its own roster, could never record its own result, and the
            // admission would stay blocked forever — the waiver would make such an
            // applicant unapprovable rather than exempt.
            if ($course->entranceExamExemptsTransferee) {
                $listed = array_column($rows, 'studentId');

                $waived = Students::whereHas('admissions', fn ($q) => $q
                    ->where('courseId', $request->courseId)
                    ->where('termId', $request->termId)
                    ->where('applicantType', ApplicantType::Transferee->value)
                    ->whereIn('admissionStatus', [
                        AdmissionStatus::Pending->value,
                        AdmissionStatus::Approved->value,
                    ])
                )->whereDoesntHave('examResults', fn ($q) => $q
                    ->where('courseId', $request->courseId)
                    ->where('termId', $request->termId)
                    ->where('examStage', ExamStage::Entrance->value)
                    ->where('examType', ExamType::General->value)
                )->whereNotIn('studentId', $listed)->get([
                    'studentId', 'schoolIdNumber', 'lastName', 'firstName', 'middleName',
                ]);

                foreach ($waived as $student) {
                    $rows[] = [
                        'studentId' => $student->studentId,
                        'schoolIdNumber' => $student->schoolIdNumber,
                        'lastName' => $student->lastName,
                        'firstName' => $student->firstName,
                        'middleName' => $student->middleName,
                        'examResult' => null,
                        'generalExamWaived' => true,
                    ];
                }
            }

            $students = collect($rows);
        } else {
            // The School Entrance candidates, and the departmental roster of a program that
            // runs no general paper at all — BSA, BSCE and BSEE require their board's exam and
            // administer no Stage 1, so there is no passer transfer to read and the applicants
            // themselves are the only honest list. Scoring them from an empty roster is what
            // made those three programs' requirement impossible to satisfy on 2026-10-07.
            $students = $this->entranceApplicants((int) $request->courseId, (int) $request->termId);
        }

        return response()->json(['students' => $students]);
    }

    /**
     * Students a program has on file for a term and has not yet enrolled — the population
     * the examinations run over.
     *
     * @return Collection<int, Students>
     */
    private function entranceApplicants(int $courseId, int $termId): Collection
    {
        return Students::whereHas('admissions', fn ($q) => $q
            ->where('courseId', $courseId)
            ->where('termId', $termId)
            ->whereIn('admissionStatus', [AdmissionStatus::Pending->value, AdmissionStatus::Approved->value])
        )->whereDoesntHave('enrollments', fn ($q) => $q
            ->where('termId', $termId)
        )->get(['studentId', 'schoolIdNumber', 'lastName', 'firstName', 'middleName']);
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

        // The examination module writes examresults rows and nothing else. Deciding
        // the application belongs to the Admission office: AdmissionPolicy::approve
        // already refuses an application whose general result is missing or failed,
        // and only AdmissionController::approve creates the enrollment that the next
        // six desks read. Set the status here and a failed applicant simply vanishes
        // from the Admission queue with no signature and no audit row (G-8).
        return redirect()->route('exam.index')
            ->with('success', 'General entrance exam recorded. The Admission office decides the application.');
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

        $course = Courses::findOrFail($validated['courseId']);
        if (! $course->requiresEntranceExam && ! $course->requiresCourseSpecificExam) {
            return back()->withErrors(['courseId' => 'This course administers no entrance examination.']);
        }

        // BR9: Stage 1 before Stage 2 — where the program runs a Stage 1 at all. Two
        // rulings narrow this, and both were found the same way: a rule the blockers ask
        // for that the recording path refuses to let anyone produce.
        //   C-4 (2026-10-07): a program may waive the general paper for its transferees.
        //   C-5 (2026-10-07): a board program may require its own paper while administering
        //   no general one — BSA, BSCE and BSEE are seeded that way, and requiring their
        //   examination behind a general pass they never run made every one of their
        //   applicants unapprovable.
        if ($course->requiresEntranceExam) {
            $admission = Admissions::query()
                ->where('studentId', $validated['studentId'])
                ->where('courseId', $validated['courseId'])
                ->where('termId', $validated['termId'])
                ->first();

            $waived = $course->waivesGeneralEntranceExam($admission?->applicantType);

            $generalExam = Examresults::where('studentId', $validated['studentId'])
                ->where('courseId', $validated['courseId'])
                ->where('termId', $validated['termId'])
                ->where('examStage', ExamStage::Entrance)
                ->where('examType', ExamType::General)
                ->first();

            if (! $waived && (! $generalExam || $generalExam->examResult !== ExamResult::Pass)) {
                return back()->withErrors(['generalExam' => 'General entrance exam must be passed first.']);
            }
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

        // Same boundary as the general stage: this is where the department's opinion
        // ends. A pass here used to write admissionStatus = approved directly, which
        // left the application reading as approved with no enrollment row — and
        // because AdmissionPolicy::approve only accepts a pending application, the
        // Admission office could no longer correct it, so the student never reached
        // the Evaluation queue (G-8). A fail used to reject the application outright.
        return redirect()->route('exam.index')
            ->with('success', 'Course-specific entrance exam recorded. The Admission office decides the application.');
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
