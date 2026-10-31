<?php

namespace App\Http\Controllers\Exam;

use App\Enums\AdmissionStatus;
use App\Enums\ApplicantType;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Http\Controllers\Controller;
use App\Models\Academicterms;
use App\Models\Admissions;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Examresults;
use App\Models\Students;
use App\Support\CollegeScope;
use App\Support\SchoolTime;
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
        $canVerify = $user->hasPermissionTo('exam.verify');
        // Course-specific and retention results are handled and viewed only by
        // the owning academic department (item 4).
        $isDepartment = $canCourseSpecific || $user->hasPermissionTo('exam.record.retention');

        // No `stage` branch: the desk's stage filter was removed when a department's list became its own
        // course's results (see Exam/Index.jsx), and nothing else links here with that key. The candidate
        // list at exam.students still takes stage, and keeps it.
        $query = Examresults::with(['student:studentId,schoolIdNumber,lastName,firstName,middleName', 'course', 'term', 'verifiedByUser'])
            ->when($request->type, fn ($q, $type) => $q->where('examType', $type))
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->orderByDesc('examId');

        // Item 4 ownership: Guidance handles and views the School Entrance
        // Examination only — all of its results, pass and failed. Course-specific
        // and retention results are handled and viewed only by the academic
        // department. What crosses the boundary is the passer transfer (BR9):
        // general-stage PASS rows surface to the department as transferred
        // passers — names and results only, never the failed ones.
        //
        // §7.2 adds one branch above the rest: the desk that confirms a result official must
        // read every row, at either stage and of either verdict, because a confirmer shown
        // only the transferred passers would never see the retention or departmental rows it
        // is being asked to sign. The right is granted with `exam.view` for exactly this, and
        // SysAdmin holds every permission, so it takes this branch and the matrix it always saw.
        if ($canVerify) {
            // The confirming desk: the full matrix.
        } elseif ($canGeneral && $isDepartment) {
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

        // Tiles over the whole filtered set, cloned before paginate() — paginate() puts the page's
        // LIMIT/OFFSET on the builder it is called on, so a clone taken afterwards answers with nothing from
        // page 2. Counting these client-side described 20 rows under a label that promised the queue.
        $resultCounts = (clone $query)
            ->reorder()
            ->selectRaw('examResult, count(*) as aggregate')
            ->groupBy('examResult')
            ->pluck('aggregate', 'examResult');

        // §7.2: how much of this desk's queue is still waiting on the Registrar. Counted
        // over the same filtered set and cloned before paginate(), for the lane 47 reason.
        $pendingCount = (int) (clone $query)->reorder()
            ->where('resultStatus', ExamStatus::Provisional->value)
            ->count();

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
            'stats' => [
                'total' => $exams->total(),
                'pass' => (int) ($resultCounts[ExamResult::Pass->value] ?? 0),
                'fail' => (int) ($resultCounts[ExamResult::Fail->value] ?? 0),
                'pending' => $pendingCount,
            ],
            'filters' => $request->only(['type', 'search']),
            'can' => [
                'recordGeneral' => $canGeneral,
                'recordCourseSpecific' => $canCourseSpecific,
                'verifyExam' => $canVerify,
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

        // Course list: entrance-exam courses only, and for a college's own desk only the programs of
        // that college (ruled 2026-10-13 — the department is given its own students and applicants).
        $courses = CollegeScope::ofCourses(
            Courses::where('requiresEntranceExam', true),
            $request->user()
        );

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

        // The roster is asked for one program at a time, so the boundary is checked against that program
        // rather than only filtering the picker — an empty list would read as "this college has nobody".
        $this->refuseOtherCollege($request, $course);

        if ($type === ExamType::CourseSpecific && $course?->requiresEntranceExam) {
            // Item 4 — the passer transfer (BR9): these are the School Entrance
            // Examination passers. Students are not applicants at this stage —
            // only names and results are shown.
            $passers = Examresults::with('student:studentId,schoolIdNumber,lastName,firstName,middleName')
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
                // §7.2: a pass the Registrar has not confirmed cannot yet be recorded
                // against, so the roster names the state instead of hiding the row — the
                // department is told before it submits, not by a refusal after.
                'resultStatus' => $result->resultStatus->value,
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
     * A desk bound to a college may not record or read another college's examination population.
     *
     * Ruled 2026-10-13: the department receives the students and applicants of its own college. Refused
     * with the program named, because the screen reached this by a course id chosen from a picker that
     * already hides the other colleges — arriving here means the id was typed, and a silent empty roster
     * would tell the desk its college has nobody rather than that it asked for someone else's.
     */
    private function refuseOtherCollege(Request $request, ?Courses $course): void
    {
        abort_unless(
            CollegeScope::holds($request->user(), $course?->unitId),
            403,
            "Program “{$course?->courseCode}” belongs to another college; a department records its own."
        );
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
        // §7.2: a corrected paper is a new entry, not an amendment the Registrar has
        // already signed. An official row re-recorded here goes back to provisional and
        // loses its verifier, so the signature on file can never be read as covering a
        // figure the desk changed underneath it.
        $exam->fill([
            'examResult' => $validated['examResult'],
            'examDate' => $validated['examDate'],
            'resultStatus' => ExamStatus::Provisional->value,
            'verifiedBy' => null,
            'verifiedDate' => null,
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
        $this->refuseOtherCollege($request, $course);

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

            // §7.2: the prerequisite asks the *confirmed* pass, and says which of the three
            // states it found. A provisional row is not treated as absent — the department is
            // told to wait for the Registrar — because a missing and a not-yet-adopted result
            // need different people to act, and calling the second one the first would send
            // the desk back to Guidance to record something that is already on file.
            if ($waived) {
                // nothing to ask: the program waived the general paper for this applicant type
            } elseif (! $generalExam) {
                return back()->withErrors(['generalExam' => 'General entrance exam must be recorded first.']);
            } elseif (! $generalExam->isOfficial()) {
                return back()->withErrors(['generalExam' => 'The general entrance exam result is on file but the Registrar has not confirmed it official; a department may only record against a confirmed pass.']);
            } elseif ($generalExam->examResult !== ExamResult::Pass) {
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
            'resultStatus' => ExamStatus::Provisional->value,
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
        $canVerify = $user->hasPermissionTo('exam.verify');
        // Course-specific and retention results are handled and viewed only by
        // the owning academic department (item 4).
        $isDepartment = $user->hasPermissionTo('exam.record.courseSpecific')
            || $user->hasPermissionTo('exam.record.retention');

        // The desk's row action links here with the student's School ID, so `search` is honoured the same
        // way index() honours it — the same three matches, the same exact-number alternative. Before this,
        // the link advertised a narrowing this handler never applied and the page opened on every result.
        $query = Examresults::with(['student:studentId,schoolIdNumber,lastName,firstName,middleName', 'course', 'term', 'verifiedByUser'])
            ->when($request->result, fn ($q, $result) => $q->where('examResult', $result))
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->orderByDesc('examId');

        // Same ownership split as index(): Guidance holds every School Entrance
        // result including failed; the department holds its own course-specific
        // results plus the transferred passers (names and results only). A desk that
        // confirms results official sees the whole matrix here too — the same reason
        // given in index(): a list that hides the rows a desk is responsible for
        // signing is not a list, it is a filter that happens to disagree with the right.
        if ($canVerify) {
            // The confirming desk: the full matrix.
        } elseif ($canGeneral && $isDepartment) {
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

        // Result counts over the whole filtered set, taken before paginate() for the reason given in index().
        // The pass rate's denominator is scored rows only: examResult is a nullable enum, so an unscored
        // student is neither a passer nor a failure, and counting them made a screen of 20 unscored rows
        // read "0%".
        $resultCounts = (clone $query)
            ->reorder()
            ->selectRaw('examResult, count(*) as aggregate')
            ->groupBy('examResult')
            ->pluck('aggregate', 'examResult');

        $passCount = (int) ($resultCounts[ExamResult::Pass->value] ?? 0);
        $failCount = (int) ($resultCounts[ExamResult::Fail->value] ?? 0);
        $scoredCount = $passCount + $failCount;

        $exams = $query->paginate(50)->withQueryString();

        return Inertia::render('Exam/Results', [
            'exams' => $exams,
            'stats' => [
                'total' => $exams->total(),
                'pass' => $passCount,
                'fail' => $failCount,
                'scored' => $scoredCount,
                'passRate' => $scoredCount > 0 ? (int) round($passCount / $scoredCount * 100) : null,
            ],
            'filters' => $request->only(['result', 'search']),
            'can' => [
                'recordGeneral' => $canGeneral,
                'recordCourseSpecific' => $user->hasPermissionTo('exam.record.courseSpecific'),
                'verifyExam' => $canVerify,
            ],
        ]);
    }

    /**
     * Confirm a recorded result official (§7.2, ruled 2026-10-26).
     *
     * The recording office's entry is its own opinion of the paper; this act is the school
     * adopting it. Two rights keep the two acts apart — `exam.record.*` writes the row and
     * `exam.verify` signs it — and no seeded role holds both, so the desk that scored a
     * result cannot be the desk that stands behind it.
     *
     * A row nobody scored cannot be adopted: `examResult` is a nullable enum, and an
     * unmarked paper is neither a passer nor a failure (the same reason `results()` takes
     * scored rows as a pass rate's denominator). Confirming it would mint a school verdict
     * out of an absence, which is what the gate downstream would then read as a pass.
     */
    public function verify(Request $request, Examresults $exam): RedirectResponse
    {
        $this->authorize('verify', $exam);

        if ($exam->isOfficial()) {
            return back()->withErrors(['resultStatus' => 'This result is already official; the Registrar confirmed it on '
                .($exam->verifiedDate ? $exam->verifiedDate->toDateString() : 'an earlier day').'.']);
        }

        if ($exam->examResult === null) {
            return back()->withErrors(['resultStatus' => 'This paper has not been scored, so there is no result for the Registrar to confirm.']);
        }

        $exam->update([
            'resultStatus' => ExamStatus::Official->value,
            'verifiedBy' => $request->user()->userId,
            'verifiedDate' => SchoolTime::today(),
        ]);

        return back()->with('success', 'The '.strtolower($exam->examStage->value).' result is official. Every desk that asks an examination outcome now counts this row.');
    }
}
