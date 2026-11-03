<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppliesTo;
use App\Enums\CoverageType;
use App\Enums\FeeUnitBasis;
use App\Enums\OfficeId;
use App\Enums\Semester;
use App\Enums\SemesterOffered;
use App\Enums\SubjectType;
use App\Http\Controllers\Controller;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Admissionrequirements;
use App\Models\Blocks;
use App\Models\Clearancerequirements;
use App\Models\Courses;
use App\Models\Curriculums;
use App\Models\Curriculumsubjects;
use App\Models\Enrollments;
use App\Models\Feetypes;
use App\Models\Gradescale;
use App\Models\Majors;
use App\Models\Offices;
use App\Models\Rooms;
use App\Models\Scholarshiptypes;
use App\Models\Subjects;
use App\Support\ListSort;
use App\Support\ReferenceDataSections;
use App\Support\SchoolTime;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReferenceDataController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display reference data dashboard.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Courses::class);

        return Inertia::render('Admin/ReferenceData/Index', [
            'manageable' => ReferenceDataSections::manageable($request->user()),
            'stats' => [
                'courses' => Courses::count(),
                'majors' => Majors::count(),
                'curriculums' => Curriculums::count(),
                'subjects' => Subjects::count(),
                'terms' => Academicterms::count(),
                'feeTypes' => Feetypes::count(),
                'gradeScale' => Gradescale::count(),
                'scholarshipTypes' => Scholarshiptypes::count(),
                'offices' => Offices::count(),
                'rooms' => Rooms::count(),
                'blocks' => Blocks::count(),
            ],
        ]);
    }

    // ============ COURSES ============
    public function courses(Request $request): Response
    {
        $this->authorize('manageCourses', Courses::class);

        // The builder is split from the paging call so the order is applied in the action, the way every wired
        // screen does it. `unitId` is NOT NULL in the migration and no row on live `ems` is missing one, so the
        // unit hop can be INNER without deleting a row from the desk's list — the check that decides INNER versus
        // LEFT, asked of the schema and the rows rather than assumed from the last screen. The four exam flags are
        // columns the screen paints as badges, so each carries a header: a board desk that wants the flagged
        // programs together is one click, and the query answers it.
        $query = Courses::with('unit')
            ->when($request->search, fn ($q, $search) => $q->where(function ($sq) use ($search) {
                $sq->where('courseName', 'like', "%{$search}%")
                    ->orWhere('courseCode', 'like', "%{$search}%");
            }))
            ->when($request->unit, fn ($q, $unit) => $q->where('courses.unitId', $unit));

        $sortable = [
            'courseCode' => ['column' => 'courseCode'],
            'courseName' => ['column' => 'courseName'],
            'unit' => ['column' => 'unitName', 'join' => 'academicunits', 'foreign' => 'unitId', 'local' => 'courses.unitId'],
            'requiresEntranceExam' => ['column' => 'requiresEntranceExam'],
            'requiresCourseSpecificExam' => ['column' => 'requiresCourseSpecificExam'],
            'entranceExamExemptsTransferee' => ['column' => 'entranceExamExemptsTransferee'],
            'requiresRetentionExam' => ['column' => 'requiresRetentionExam'],
            'courseId' => ['column' => 'courseId'],
        ];
        $sort = ListSort::apply(
            $query,
            (string) $request->input('sort', ''),
            (string) $request->input('direction', 'asc'),
            $sortable,
            'courseCode',
            'courses.courseId'
        );

        $courses = $query->paginate(20)->withQueryString();
        $units = Academicunits::all(['unitId', 'unitName']);

        return Inertia::render('Admin/ReferenceData/Courses', [
            'courses' => $courses,
            'units' => $units,
            // Both order keys come back with the filters: a control the desk set that the handler obeyed but never
            // echoed resets on the next render while the list stays narrowed — the defect lane 46 owns.
            'filters' => $request->only(['search', 'unit']) + [
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
            ],
            'sort' => ListSort::present($sort, $sortable),
        ]);
    }

    public function storeCourse(Request $request): RedirectResponse
    {
        $this->authorize('manageCourses', Courses::class);

        // Audit §4.5: create() must consume the validated array — never the
        // raw $request->all() — so the safe field list cannot silently drift.
        // The two exam flags are NOT NULL, and an unchecked box is simply absent
        // from the payload, so they are read through boolean() rather than rule-passed.
        Courses::create(array_merge(
            $request->validate([
                'unitId' => 'required|exists:academicunits,unitId',
                'courseName' => 'required|string|max:255',
                'courseCode' => 'required|string|max:50|unique:courses,courseCode',
                'requiresEntranceExam' => 'boolean',
                'requiresCourseSpecificExam' => 'boolean',
                'entranceExamExemptsTransferee' => 'boolean',
                'requiresRetentionExam' => 'boolean',
            ]),
            [
                'requiresEntranceExam' => $request->boolean('requiresEntranceExam'),
                'requiresCourseSpecificExam' => $request->boolean('requiresCourseSpecificExam'),
                'entranceExamExemptsTransferee' => $request->boolean('entranceExamExemptsTransferee'),
                'requiresRetentionExam' => $request->boolean('requiresRetentionExam'),
            ]
        ));

        return back()->with('success', 'Course created.');
    }

    public function updateCourse(Request $request, Courses $course): RedirectResponse
    {
        $this->authorize('manageCourses', Courses::class);

        $course->update(array_merge(
            $request->validate([
                'unitId' => 'required|exists:academicunits,unitId',
                'courseName' => 'required|string|max:255',
                'courseCode' => 'required|string|max:50|unique:courses,courseCode,'.$course->courseId.',courseId',
                'requiresEntranceExam' => 'boolean',
                'requiresCourseSpecificExam' => 'boolean',
                'entranceExamExemptsTransferee' => 'boolean',
                'requiresRetentionExam' => 'boolean',
            ]),
            [
                'requiresEntranceExam' => $request->boolean('requiresEntranceExam'),
                'requiresCourseSpecificExam' => $request->boolean('requiresCourseSpecificExam'),
                'entranceExamExemptsTransferee' => $request->boolean('entranceExamExemptsTransferee'),
                'requiresRetentionExam' => $request->boolean('requiresRetentionExam'),
            ]
        ));

        return back()->with('success', 'Course updated.');
    }

    public function destroyCourse(Courses $course): RedirectResponse
    {
        $this->authorize('manageCourses', Courses::class);

        if ($blocked = $this->blockedDeletionMessage([
            'enrollment' => DB::table('enrollments')->where('courseId', $course->courseId)->count(),
            'admission' => DB::table('admissions')->where('courseId', $course->courseId)->count(),
            'block' => DB::table('blocks')->where('courseId', $course->courseId)->count(),
            'major' => DB::table('majors')->where('courseId', $course->courseId)->count(),
            'curriculum' => DB::table('curriculums')->where('courseId', $course->courseId)->count(),
            'exam result' => DB::table('examresults')->where('courseId', $course->courseId)->count(),
            // The shift module names a course twice over, and both keys refuse a delete. It arrived after this
            // guard was written, so the one program a student is moving between was the only course row an
            // admin could not delete safely — the constraint answered the click with a generic conflict flash.
            'shift request' => DB::table('shiftingrequests')
                ->where(fn ($q) => $q->where('currentCourseId', $course->courseId)
                    ->orWhere('targetCourseId', $course->courseId))
                ->count(),
        ])) {
            return back()->with('error', $blocked);
        }

        $course->delete();

        return back()->with('success', 'Course deleted.');
    }

    // ============ MAJORS ============
    public function majors(Request $request): Response
    {
        $this->authorize('manageMajors', Majors::class);

        // Split from the paging call so the order is applied in the action, as on every other wired screen.
        // The Course column is one hop away and `majors.courseId` is `NOT NULL` with no missing value on live
        // `ems`, so the hop is INNER; the default is the title ascending because a reference list is read
        // alphabetically, and the fallback names the desk's key so the echo matches a header.
        $query = Majors::with('course')
            ->when($request->search, fn ($q, $search) => $q->where('majorName', 'like', "%{$search}%"))
            ->when($request->courseId, fn ($q, $courseId) => $q->where('majors.courseId', $courseId));

        $sortable = [
            'majorName' => ['column' => 'majorName'],
            'course' => ['column' => 'courseName', 'join' => 'courses', 'foreign' => 'courseId', 'local' => 'majors.courseId'],
            'majorId' => ['column' => 'majorId'],
        ];
        $sort = ListSort::apply(
            $query,
            (string) $request->input('sort', ''),
            (string) $request->input('direction', 'asc'),
            $sortable,
            'majorName',
            'majors.majorId'
        );

        $majors = $query->paginate(20)->withQueryString();
        $courses = Courses::all(['courseId', 'courseName']);

        return Inertia::render('Admin/ReferenceData/Majors', [
            'majors' => $majors,
            'courses' => $courses,
            'filters' => $request->only(['search', 'courseId']) + [
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
            ],
            'sort' => ListSort::present($sort, $sortable),
        ]);
    }

    public function storeMajor(Request $request): RedirectResponse
    {
        $this->authorize('manageMajors', Majors::class);

        Majors::create($request->validate([
            'courseId' => 'required|exists:courses,courseId',
            'majorName' => 'required|string|max:255',
        ]));

        return back()->with('success', 'Major created.');
    }

    public function updateMajor(Request $request, Majors $major): RedirectResponse
    {
        $this->authorize('manageMajors', Majors::class);
        $major->update($request->validate(['majorName' => 'required|string|max:255']));

        return back()->with('success', 'Major updated.');
    }

    public function destroyMajor(Majors $major): RedirectResponse
    {
        $this->authorize('manageMajors', Majors::class);

        if ($blocked = $this->blockedDeletionMessage([
            'curriculum' => DB::table('curriculums')->where('majorId', $major->majorId)->count(),
            'enrollment' => DB::table('enrollments')->where('majorId', $major->majorId)->count(),
        ])) {
            return back()->with('error', $blocked);
        }

        $major->delete();

        return back()->with('success', 'Major deleted.');
    }

    // ============ CURRICULUMS ============
    public function curriculums(Request $request): Response
    {
        $this->authorize('manageCurriculums', Curriculums::class);

        $curriculums = Curriculums::with(['course', 'major'])
            ->when($request->search, fn ($q, $search) => $q->where(function ($sq) use ($search) {
                $sq->where('curriculumName', 'like', "%{$search}%")
                    ->orWhereHas('course', fn ($cq) => $cq->where('courseName', 'like', "%{$search}%"));
            }));

        // Two hops, chosen by asking the schema AND the rows rather than by copying the last screen. `courseId` is
        // NOT NULL with a foreign key and live `ems` answers 16 curriculums, 0 nulls and 0 orphans, so an INNER hop
        // cannot drop a record. `majorId` is the mirror case: nullable in the migration and NULL on every one of the
        // 16 rows today, so an INNER hop here would not mis-order the list — it would empty it.
        $sortable = [
            'curriculumId' => ['column' => 'curriculumId'],
            'curriculumName' => ['column' => 'curriculumName'],
            'course' => [
                'column' => 'courseName',
                'join' => 'courses',
                'foreign' => 'courseId',
                'local' => 'curriculums.courseId',
            ],
            'major' => [
                'column' => 'majorName',
                'join' => 'majors',
                'foreign' => 'majorId',
                'local' => 'curriculums.majorId',
                'type' => 'left',
            ],
            // The cell prints the YEAR of a DATE column, so this orders by the stored date — the unit is stated in
            // the case rather than assumed from the label a person reads.
            'effectiveYear' => ['column' => 'effectiveYear'],
        ];
        $sort = ListSort::apply(
            $curriculums,
            (string) $request->input('sort', ''),
            (string) $request->input('direction', 'asc'),
            $sortable,
            'curriculumName',
            'curriculums.curriculumId',
        );

        $page = $curriculums->paginate(20)->withQueryString();
        $courses = Courses::all(['courseId', 'courseName']);
        $majors = Majors::all(['majorId', 'majorName']);

        return Inertia::render('Admin/ReferenceData/Curriculums', [
            'curriculums' => $page,
            'courses' => $courses,
            'majors' => $majors,
            'filters' => $request->only(['search'])
                + ['sort' => $sort['column'], 'direction' => $sort['direction']],
            'sort' => ListSort::present($sort, $sortable),
        ]);
    }

    public function storeCurriculum(Request $request): RedirectResponse
    {
        $this->authorize('manageCurriculums', Curriculums::class);

        Curriculums::create($request->validate([
            'courseId' => 'required|exists:courses,courseId',
            'majorId' => 'nullable|exists:majors,majorId',
            'effectiveYear' => 'required|date',
            'curriculumName' => 'required|string|max:255',
        ]));

        return back()->with('success', 'Curriculum created.');
    }

    public function updateCurriculum(Request $request, Curriculums $curriculum): RedirectResponse
    {
        $this->authorize('manageCurriculums', Curriculums::class);
        $curriculum->update($request->validate([
            'courseId' => 'required|exists:courses,courseId',
            'majorId' => 'nullable|exists:majors,majorId',
            'effectiveYear' => 'required|date',
            'curriculumName' => 'required|string|max:255',
        ]));

        return back()->with('success', 'Curriculum updated.');
    }

    public function destroyCurriculum(Curriculums $curriculum): RedirectResponse
    {
        $this->authorize('manageCurriculums', Curriculums::class);

        // Item 7: enrollments pin curriculumId — deleting a pinned curriculum
        // silently un-pins them, and one with subjects 500s on the RESTRICT FK.
        // Surface both instead of letting them happen.
        $pinnedCount = Enrollments::where('curriculumId', $curriculum->curriculumId)->count();
        $subjectCount = Curriculumsubjects::where('curriculumId', $curriculum->curriculumId)->count();
        if ($pinnedCount > 0 || $subjectCount > 0) {
            throw ValidationException::withMessages([
                'curriculum' => "Cannot delete: {$pinnedCount} enrollment(s) pin this curriculum and {$subjectCount} subject(s) are attached. Remove them first.",
            ]);
        }

        $curriculum->delete();

        return back()->with('success', 'Curriculum deleted.');
    }

    // ============ CURRICULUM SUBJECTS ============
    public function curriculumSubjects(Request $request, Curriculums $curriculum): Response
    {
        $this->authorize('manageCurriculumSubjects', Curriculumsubjects::class);

        $subjects = Curriculumsubjects::with(['subject', 'prerequisiteSubject'])
            ->where('curriculumId', $curriculum->curriculumId)
            ->orderBy('yearLevel')
            ->orderBy('semesterOffered')
            ->get();

        $allSubjects = Subjects::all(['subjectId', 'subjectCode', 'subjectName', 'subjectDesc']);
        $semesters = collect(SemesterOffered::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values();

        return Inertia::render('Admin/ReferenceData/CurriculumSubjects', [
            'curriculum' => $curriculum->load(['course', 'major']),
            'subjects' => $subjects,
            'allSubjects' => $allSubjects,
            'semesters' => $semesters,
        ]);
    }

    public function storeCurriculumSubject(Request $request, Curriculums $curriculum): RedirectResponse
    {
        $this->authorize('manageCurriculumSubjects', Curriculumsubjects::class);

        $validated = $this->normalizeElective($request->validate($this->curriculumSubjectRules()));

        Curriculumsubjects::create(array_merge($validated, ['curriculumId' => $curriculum->curriculumId]));

        return back()->with('success', 'Curriculum subject added.');
    }

    public function updateCurriculumSubject(Request $request, Curriculumsubjects $cs): RedirectResponse
    {
        $this->authorize('manageCurriculumSubjects', Curriculumsubjects::class);

        $cs->update($this->normalizeElective($request->validate($this->curriculumSubjectRules())));

        return back()->with('success', 'Curriculum subject updated.');
    }

    /**
     * @return array<string, string>
     */
    private function curriculumSubjectRules(): array
    {
        return [
            'subjectId' => 'required|exists:subjects,subjectId',
            'prerequisiteSubjectId' => 'nullable|exists:subjects,subjectId',
            'yearLevel' => 'required|integer|min:1|max:5',
            'semesterOffered' => 'required|in:1st,2nd,Summer',
            'is_elective' => 'nullable|boolean',
            'elective_group' => 'nullable|string|max:255',
            'elective_min_choices' => 'nullable|integer|min:0|max:255',
            'elective_max_choices' => 'nullable|integer|min:0|max:255',
        ];
    }

    /**
     * The elective band only means something while the subject is an elective, and
     * EvaluationController reads one band per group — so clear the leftovers on a
     * subject switched back to mandatory, and refuse a band that cannot be satisfied.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeElective(array $validated): array
    {
        $validated['is_elective'] = (bool) ($validated['is_elective'] ?? false);

        if (! $validated['is_elective']) {
            $validated['elective_group'] = null;
            $validated['elective_min_choices'] = null;
            $validated['elective_max_choices'] = null;

            return $validated;
        }

        $min = $validated['elective_min_choices'] ?? 0;
        $max = $validated['elective_max_choices'] ?? 255;

        if ($min > $max) {
            throw ValidationException::withMessages([
                'elective_max_choices' => "The maximum choices ({$max}) cannot be lower than the minimum ({$min}).",
            ]);
        }

        return $validated;
    }

    public function destroyCurriculumSubject(Curriculumsubjects $cs): RedirectResponse
    {
        $this->authorize('manageCurriculumSubjects', Curriculumsubjects::class);
        $cs->delete();

        return back()->with('success', 'Curriculum subject deleted.');
    }

    // ============ SUBJECTS ============
    public function subjects(Request $request): Response
    {
        $this->authorize('manageSubjects', Subjects::class);

        // The builder is split from the paging call because the order is applied to it in the action, the way
        // every wired screen does it: a key the helper consumed one call deeper is a key the filter-contract
        // checker cannot see the handler obeying, and it was reported DEAD-SEND for exactly that reason.
        $query = Subjects::query()
            ->when($request->search, fn ($q, $search) => $q->where(function ($sq) use ($search) {
                $sq->where('subjectName', 'like', "%{$search}%")
                    ->orWhere('subjectCode', 'like', "%{$search}%");
            }))
            ->when($request->type, fn ($q, $type) => $q->where('subjectType', $type));

        // `subjectType` is an ENUM and carries a header under the owner's ruling of 2026-10-31: on the school's
        // server it orders by the declaration the school wrote. `subjectDesc` is a column but no screen reads it
        // in this list, so it stays out — a header is offered for what the desk sees. The list's own default is
        // the code, ascending, because a catalogue is read top to bottom and the helper's
        // absence-of-direction answer is descending for queues of newest-first records.
        $sortable = [
            'subjectCode' => ['column' => 'subjectCode'],
            'subjectName' => ['column' => 'subjectName'],
            'subjectType' => ['column' => 'subjectType'],
            'lectureUnits' => ['column' => 'lectureUnits'],
            'labUnits' => ['column' => 'labUnits'],
            'subjectId' => ['column' => 'subjectId'],
        ];
        $sort = ListSort::apply(
            $query,
            (string) $request->input('sort', ''),
            (string) $request->input('direction', 'asc'),
            $sortable,
            'subjectCode',
            'subjects.subjectId'
        );

        $subjects = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/ReferenceData/Subjects', [
            'subjects' => $subjects,
            'subjectTypes' => collect(SubjectType::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
            // Both order keys come back with the filters: a control the desk set that the handler obeyed but
            // never echoed resets on the next render while the list stays narrowed — the defect lane 46 owns.
            'filters' => $request->only(['search', 'type']) + [
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
            ],
            'sort' => ListSort::present($sort, $sortable),
        ]);
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $this->authorize('manageSubjects', Subjects::class);

        Subjects::create($request->validate([
            'subjectCode' => 'required|string|max:20|unique:subjects,subjectCode',
            'subjectName' => 'required|string|max:255',
            'subjectDesc' => 'nullable|string|max:500',
            'lectureUnits' => 'required|numeric|min:0',
            'labUnits' => 'required|numeric|min:0',
            'subjectType' => 'required|in:lecture,lab,both',
        ]));

        return back()->with('success', 'Subject created.');
    }

    public function updateSubject(Request $request, Subjects $subject): RedirectResponse
    {
        $this->authorize('manageSubjects', Subjects::class);
        $subject->update($request->validate([
            'subjectCode' => 'required|string|max:20|unique:subjects,subjectCode,'.$subject->subjectId.',subjectId',
            'subjectName' => 'required|string|max:255',
            'subjectDesc' => 'nullable|string|max:500',
            'lectureUnits' => 'required|numeric|min:0',
            'labUnits' => 'required|numeric|min:0',
            'subjectType' => 'required|in:lecture,lab,both',
        ]));

        return back()->with('success', 'Subject updated.');
    }

    public function destroySubject(Subjects $subject): RedirectResponse
    {
        $this->authorize('manageSubjects', Subjects::class);

        if ($blocked = $this->blockedDeletionMessage([
            'curriculum listing' => DB::table('curriculumsubjects')
                ->where('subjectId', $subject->subjectId)
                ->orWhere('prerequisiteSubjectId', $subject->subjectId)
                ->count(),
            'schedule' => DB::table('schedules')->where('subjectId', $subject->subjectId)->count(),
            'enrolled subject' => DB::table('enrolledsubjects')->where('subjectId', $subject->subjectId)->count(),
            'credited subject' => DB::table('creditedsubjects')->where('creditedToSubjectId', $subject->subjectId)->count(),
        ])) {
            return back()->with('error', $blocked);
        }

        $subject->delete();

        return back()->with('success', 'Subject deleted.');
    }

    // ============ ACADEMIC TERMS ============
    public function terms(Request $request): Response
    {
        $this->authorize('manageTerms', Academicterms::class);

        // "Which term is live" is the school's calendar question, the same one
        // Academicterms::covering() answers for every desk's chrome.
        $today = SchoolTime::today();

        // The day columns are qualified on purpose. `academicyears` holds `startDate` and `endDate` too, and the
        // School Year header joins that very table, so an unqualified name here names two columns at once: live `ems`
        // answered 500 (MySQL "Column 'startDate' in where clause is ambiguous") for the Active tab beside any order
        // that joins, and the suite's SQLite refuses with "ambiguous column name: startDate" — which is why the term
        // list's own case puts the tab and the header on one request.
        $terms = Academicterms::with('academicYear')
            ->when($request->semester, fn ($q, $semester) => $q->where('academicterms.semester', $semester))
            ->when($request->status === 'active', fn ($q) => $q
                ->where('academicterms.startDate', '<=', $today)
                ->where('academicterms.endDate', '>=', $today))
            ->when($request->status === 'inactive', fn ($q) => $q->where(fn ($sq) => $sq
                ->where('academicterms.startDate', '>', $today)
                ->orWhere('academicterms.endDate', '<', $today)))
            ->when($request->search, fn ($q, $search) => $q->whereHas('academicYear', fn ($yq) => $yq->where('yearLabel', 'like', "%{$search}%")));

        // The School Year header is a hop: `academicterms` stores `academicYearId` and the desk reads `yearLabel`
        // from `academicyears`. INNER is earned by both readings — the migration declares the key NOT NULL with a
        // foreign key, and live `ems` answers 9 terms, 0 nulls and 0 orphans — so no term can vanish from an order.
        // `semester` is an ENUM and its sequence is asked of the connection, never typed: MySQL orders it
        // `1st, 2nd, Summer` by declaration while the suite's varchar CHECK sorts the word.
        $sortable = [
            'academicYear' => [
                'column' => 'yearLabel',
                'join' => 'academicyears',
                'foreign' => 'academicYearId',
                'local' => 'academicterms.academicYearId',
            ],
            'semester' => ['column' => 'semester'],
            'startDate' => ['column' => 'startDate'],
            'endDate' => ['column' => 'endDate'],
        ];
        $sort = ListSort::apply(
            $terms,
            (string) $request->input('sort', ''),
            (string) $request->input('direction', 'asc'),
            $sortable,
            'academicYear',
            'academicterms.termId',
        );

        $page = $terms->paginate(20)->withQueryString();
        $years = Academicyears::all(['academicYearId', 'yearLabel']);

        return Inertia::render('Admin/ReferenceData/Terms', [
            'terms' => $page,
            'years' => $years,
            'semesters' => collect(Semester::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
            'filters' => $request->only(['search', 'semester', 'status'])
                + ['sort' => $sort['column'], 'direction' => $sort['direction']],
            'sort' => ListSort::present($sort, $sortable),
        ]);
    }

    public function storeTerm(Request $request): RedirectResponse
    {
        $this->authorize('manageTerms', Academicterms::class);

        Academicterms::create($request->validate([
            'academicYearId' => 'required|exists:academicyears,academicYearId',
            'semester' => 'required|in:1st,2nd,Summer',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after:startDate',
        ]));

        return back()->with('success', 'Term created.');
    }

    public function updateTerm(Request $request, Academicterms $term): RedirectResponse
    {
        $this->authorize('manageTerms', Academicterms::class);
        $term->update($request->validate([
            'academicYearId' => 'required|exists:academicyears,academicYearId',
            'semester' => 'required|in:1st,2nd,Summer',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after:startDate',
        ]));

        return back()->with('success', 'Term updated.');
    }

    public function destroyTerm(Academicterms $term): RedirectResponse
    {
        $this->authorize('manageTerms', Academicterms::class);

        if ($blocked = $this->blockedDeletionMessage([
            'enrollment' => DB::table('enrollments')->where('termId', $term->termId)->count(),
            'admission' => DB::table('admissions')->where('termId', $term->termId)->count(),
            'block' => DB::table('blocks')->where('termId', $term->termId)->count(),
            'clearance period' => DB::table('clearanceperiods')->where('termId', $term->termId)->count(),
            'exam result' => DB::table('examresults')->where('termId', $term->termId)->count(),
            'scholarship grant' => DB::table('studentscholarships')->where('termId', $term->termId)->count(),
        ])) {
            return back()->with('error', $blocked);
        }

        $term->delete();

        return back()->with('success', 'Term deleted.');
    }

    // ============ FEE TYPES ============
    public function feeTypes(Request $request): Response
    {
        $this->authorize('manageFeeTypes', Feetypes::class);

        // Split from the paging call because the order is applied to the builder in the action, the way every wired
        // screen does it — a key consumed one call deeper reads to `lane46` as a key the handler never obeys.
        $query = Feetypes::query()
            ->when($request->search, fn ($q, $search) => $q->where('feeName', 'like', "%{$search}%"))
            ->when($request->unitBasis, fn ($q, $basis) => $q->where('unitBasis', $basis));

        // Every column here sits on the row the desk already reads, so no hop is written and nothing can widen the
        // payload. `unitBasis` is `enum('perUnit','flat')` on the school's server, which carries a header under the
        // owner's ruling of 2026-10-31 because the order it gives is the declaration the school wrote into the
        // migration. The default is the name ascending: a catalogue is read top to bottom, and the helper's
        // absence-of-direction answer serves a queue of newest-first records, not this list.
        $sortable = [
            'feeName' => ['column' => 'feeName'],
            'defaultAmount' => ['column' => 'defaultAmount'],
            'unitBasis' => ['column' => 'unitBasis'],
            // The list's own key: the fallback is read from here, never painted as a header.
            'feeTypeId' => ['column' => 'feetypes.feeTypeId'],
        ];
        $sort = ListSort::apply(
            $query,
            (string) $request->input('sort', ''),
            (string) $request->input('direction', 'asc'),
            $sortable,
            'feeName',
            'feetypes.feeTypeId'
        );

        $feeTypes = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/ReferenceData/FeeTypes', [
            'feeTypes' => $feeTypes,
            'unitBases' => collect(FeeUnitBasis::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
            // Both order keys travel back with the filters: a control the desk set that the handler obeyed but never
            // echoed resets on the next render while the list stays narrowed — the defect lane 46 owns for filters.
            'filters' => $request->only(['search', 'unitBasis']) + [
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
            ],
            'sort' => ListSort::present($sort, $sortable),
        ]);
    }

    public function storeFeeType(Request $request): RedirectResponse
    {
        $this->authorize('manageFeeTypes', Feetypes::class);

        Feetypes::create($request->validate([
            'feeName' => 'required|string|max:255',
            'defaultAmount' => 'required|numeric|min:0',
            'unitBasis' => 'required|in:perUnit,flat',
        ]));

        return back()->with('success', 'Fee type created.');
    }

    public function updateFeeType(Request $request, Feetypes $feeType): RedirectResponse
    {
        $this->authorize('manageFeeTypes', Feetypes::class);
        $feeType->update($request->validate([
            'feeName' => 'required|string|max:255',
            'defaultAmount' => 'required|numeric|min:0',
            'unitBasis' => 'required|in:perUnit,flat',
        ]));

        return back()->with('success', 'Fee type updated.');
    }

    public function destroyFeeType(Feetypes $feeType): RedirectResponse
    {
        $this->authorize('manageFeeTypes', Feetypes::class);

        if ($blocked = $this->blockedDeletionMessage([
            'charge' => DB::table('charges')->where('feeTypeId', $feeType->feeTypeId)->count(),
        ])) {
            return back()->with('error', $blocked);
        }

        $feeType->delete();

        return back()->with('success', 'Fee type deleted.');
    }

    // ============ GRADE SCALE ============
    public function gradeScales(Request $request): Response
    {
        $this->authorize('manageGradeScales', Gradescale::class);

        // Split from the paging call so the order is applied in the action, as on every wired screen. This one
        // already arrived in band order, so the mechanism keeps the desk's view unchanged and only adds the
        // headers: the 'range' column paints `minGrade – maxGrade`, so it is ordered by the floor of the range,
        // which is the only reading under which "sort by grade range" has a meaning. `maxGrade` is painted inside
        // that same cell and never on its own, so it carries no header.
        $query = Gradescale::query()
            ->when($request->search, fn ($q, $search) => $q->where('description', 'like', "%{$search}%"))
            ->when($request->filled('isPassing'), fn ($q) => $q->where('isPassing', $request->boolean('isPassing')));

        $sortable = [
            'range' => ['column' => 'minGrade'],
            'isPassing' => ['column' => 'isPassing'],
            'description' => ['column' => 'description'],
        ];
        $sort = ListSort::apply(
            $query,
            (string) $request->input('sort', ''),
            (string) $request->input('direction', 'asc'),
            $sortable,
            'range',
            'gradescale.gradeScaleId'
        );

        $bands = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/ReferenceData/GradeScales', [
            'bands' => $bands,
            'passingCeiling' => Gradescale::passingCeiling(),
            'hasGradeScale' => Gradescale::isConfigured(),
            'filters' => $request->only(['search', 'isPassing']) + [
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
            ],
            'sort' => ListSort::present($sort, $sortable),
        ]);
    }

    public function storeGradeScale(Request $request): RedirectResponse
    {
        $this->authorize('manageGradeScales', Gradescale::class);

        Gradescale::create($this->validatedGradeScale($request));

        return back()->with('success', 'Grade band created.');
    }

    public function updateGradeScale(Request $request, Gradescale $gradeScale): RedirectResponse
    {
        $this->authorize('manageGradeScales', Gradescale::class);

        $validated = $this->validatedGradeScale($request);

        if ($gradeScale->isPassing && ! $validated['isPassing']) {
            $this->requireAnotherPassingBand($gradeScale);
        }

        $gradeScale->update($validated);

        return back()->with('success', 'Grade band updated.');
    }

    public function destroyGradeScale(Gradescale $gradeScale): RedirectResponse
    {
        $this->authorize('manageGradeScales', Gradescale::class);

        if ($gradeScale->isPassing) {
            $this->requireAnotherPassingBand($gradeScale);
        }

        $gradeScale->delete();

        return back()->with('success', 'Grade band deleted.');
    }

    /**
     * @return array{minGrade: string, maxGrade: string, isPassing: bool, description: string}
     */
    private function validatedGradeScale(Request $request): array
    {
        $validated = $request->validate([
            'minGrade' => ['required', 'numeric', 'between:0,9.99', 'lte:maxGrade'],
            'maxGrade' => ['required', 'numeric', 'between:0,9.99'],
            'isPassing' => ['required', 'boolean'],
            'description' => ['required', 'string', 'max:150'],
        ]);

        $validated['isPassing'] = $request->boolean('isPassing');

        return $validated;
    }

    /**
     * Academic standing is derived against the passing ceiling of this table, and
     * an empty passing set silently falls back to an assumed 3.00 — which would
     * reclassify every student without anyone deciding to. A band may therefore
     * only stop being passing when another passing band is already on file.
     */
    private function requireAnotherPassingBand(Gradescale $gradeScale): void
    {
        $anotherPassingBand = Gradescale::query()
            ->where('gradeScaleId', '!=', $gradeScale->gradeScaleId)
            ->where('isPassing', true)
            ->exists();

        if (! $anotherPassingBand) {
            throw ValidationException::withMessages([
                'isPassing' => 'At least one passing band must stay on file, otherwise every standing falls back to the assumed 3.00 ceiling.',
            ]);
        }
    }

    // ============ SCHOLARSHIP TYPES ============
    public function scholarshipTypes(Request $request): Response
    {
        $this->authorize('manageScholarshipTypes', Scholarshiptypes::class);

        $query = Scholarshiptypes::query()
            ->when($request->search, fn ($q, $search) => $q->where('scholarshipName', 'like', "%{$search}%"))
            ->when($request->coverage, fn ($q, $coverage) => $q->where('coverageType', $coverage));

        // The same catalogue shape as the fee sheet one action above it: three columns, every one of them on the row
        // the board paints, so the screen writes no hop and an order can neither widen a payload nor drop a record.
        // `coverageType` is `enum('full','partial')` on the school's server and carries a header under the owner's
        // ruling of 2026-10-31, and `coveragePercent` is `decimal(10,2)` — a number, not a percentage of anything
        // the query has to compute. The default is the name ascending because a catalogue is read top to bottom.
        $sortable = [
            'scholarshipName' => ['column' => 'scholarshipName'],
            'coverageType' => ['column' => 'coverageType'],
            'coveragePercent' => ['column' => 'coveragePercent'],
            'scholarshipTypeId' => ['column' => 'scholarshiptypes.scholarshipTypeId'],
        ];
        $sort = ListSort::apply(
            $query,
            (string) $request->input('sort', ''),
            (string) $request->input('direction', 'asc'),
            $sortable,
            'scholarshipName',
            'scholarshiptypes.scholarshipTypeId'
        );

        $types = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/ReferenceData/ScholarshipTypes', [
            'types' => $types,
            'coverageTypes' => collect(CoverageType::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
            'filters' => $request->only(['search', 'coverage']) + [
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
            ],
            'sort' => ListSort::present($sort, $sortable),
        ]);
    }

    public function storeScholarshipType(Request $request): RedirectResponse
    {
        $this->authorize('manageScholarshipTypes', Scholarshiptypes::class);

        Scholarshiptypes::create($request->validate([
            'scholarshipName' => 'required|string|max:255',
            'coverageType' => 'required|in:full,partial',
            'coveragePercent' => 'required|numeric|min:0|max:100',
        ]));

        return back()->with('success', 'Scholarship type created.');
    }

    public function updateScholarshipType(Request $request, Scholarshiptypes $type): RedirectResponse
    {
        $this->authorize('manageScholarshipTypes', Scholarshiptypes::class);
        $type->update($request->validate([
            'scholarshipName' => 'required|string|max:255',
            'coverageType' => 'required|in:full,partial',
            'coveragePercent' => 'required|numeric|min:0|max:100',
        ]));

        return back()->with('success', 'Scholarship type updated.');
    }

    public function destroyScholarshipType(Scholarshiptypes $type): RedirectResponse
    {
        $this->authorize('manageScholarshipTypes', Scholarshiptypes::class);

        if ($blocked = $this->blockedDeletionMessage([
            'scholarship grant' => DB::table('studentscholarships')->where('scholarshipTypeId', $type->scholarshipTypeId)->count(),
        ])) {
            return back()->with('error', $blocked);
        }

        $type->delete();

        return back()->with('success', 'Scholarship type deleted.');
    }

    // ============ OFFICES ============
    public function offices(Request $request): Response
    {
        $this->authorize('manageOffices', Offices::class);

        $query = Offices::query()
            ->when($request->search, fn ($q, $search) => $q->where('officeName', 'like', "%{$search}%"));

        // Two of the four columns this table paints cannot be ordered by a query at all: `workflowName` is answered
        // from App\Enums\OfficeId — the enum, not the row — and the three "Used by" counts come from grouped
        // aggregates over the tables that point at an office. A header for either would be the defect §28.3 owns for
        // filters: the desk clicks, the server obeys nothing, and the arrow resets. So only the two stored columns
        // are offered, and the catalogue opens on the name because an office list is read top to bottom.
        $sortable = [
            'officeName' => ['column' => 'officeName'],
            'officeId' => ['column' => 'offices.officeId'],
        ];
        $sort = ListSort::apply(
            $query,
            (string) $request->input('sort', ''),
            (string) $request->input('direction', 'asc'),
            $sortable,
            'officeName',
            'offices.officeId'
        );

        $offices = $query->paginate(20)->withQueryString();

        // §28 X-3: this table is data, but App\Enums\OfficeId is the authority the
        // policies and workflow comparisons actually read, so a row and a desk can
        // drift apart silently. Each is shown as it is used: named by the enum, or
        // a name no code path can reach.
        $usedBy = $this->officeUsage();

        return Inertia::render('Admin/ReferenceData/Offices', [
            'offices' => $offices->through(fn (Offices $office) => [
                'officeId' => $office->officeId,
                'officeName' => $office->officeName,
                'workflowName' => OfficeId::tryFrom($office->officeId)?->name,
                'staffCount' => $usedBy['staff'][$office->officeId] ?? 0,
                'requirementCount' => $usedBy['requirements'][$office->officeId] ?? 0,
                'stepCount' => $usedBy['steps'][$office->officeId] ?? 0,
            ]),
            // Both order keys come back with the filters: a control the desk set that the handler obeyed but never
            // echoed resets on the next render while the list stays narrowed — the defect lane 46 owns.
            'filters' => $request->only(['search']) + [
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
            ],
            'sort' => ListSort::present($sort, $sortable),
        ]);
    }

    /**
     * Rows in each table that points at an office, grouped by that office.
     *
     * @return array{staff: array<int, int>, requirements: array<int, int>, steps: array<int, int>}
     */
    private function officeUsage(): array
    {
        $countBy = fn (string $table) => DB::table($table)
            ->select('officeId', DB::raw('COUNT(*) as total'))
            ->whereNotNull('officeId')
            ->groupBy('officeId')
            ->pluck('total', 'officeId')
            ->all();

        return [
            'staff' => $countBy('staffusers'),
            'requirements' => $countBy('clearancerequirements'),
            'steps' => $countBy('workflowsteps'),
        ];
    }

    public function storeOffice(Request $request): RedirectResponse
    {
        $this->authorize('manageOffices', Offices::class);
        Offices::create($request->validate(['officeName' => 'required|string|max:255']));

        return back()->with('success', 'Office created. No desk in the application uses it yet — a new office becomes a workflow step only when its id is added to App\Enums\OfficeId, which is a code change, not a screen action.');
    }

    public function updateOffice(Request $request, Offices $office): RedirectResponse
    {
        $this->authorize('manageOffices', Offices::class);
        $office->update($request->validate(['officeName' => 'required|string|max:255']));

        return back()->with('success', 'Office updated. Documents print this name; authorization compares the id, so the rename changes no one\'s access.');
    }

    public function destroyOffice(Offices $office): RedirectResponse
    {
        $this->authorize('manageOffices', Offices::class);

        $desk = OfficeId::tryFrom($office->officeId);

        // Deleting a row the enum names would leave every office-scope policy
        // comparing against an id that no longer exists.
        if ($desk !== null) {
            return back()->with('error', "Office {$office->officeId} is a desk the application is wired to (OfficeId::{$desk->name}). Its id cannot be retired from this screen.");
        }

        // staffusers, clearancerequirements and workflowsteps all point at offices
        // with a RESTRICT foreign key, so an attached row would answer the delete
        // with a database error instead of a reason.
        $usedBy = $this->officeUsage();
        $attached = [
            'staff account' => $usedBy['staff'][$office->officeId] ?? 0,
            'clearance requirement' => $usedBy['requirements'][$office->officeId] ?? 0,
            'workflow step' => $usedBy['steps'][$office->officeId] ?? 0,
        ];
        if (array_sum($attached) > 0) {
            $listed = collect($attached)->filter()->keys()->implode(', ');

            return back()->with('error', "This office still has {$listed} attached, so deleting it would orphan them.");
        }

        $office->delete();

        return back()->with('success', 'Office deleted.');
    }

    // ============ ROOMS ============
    public function rooms(Request $request): Response
    {
        $this->authorize('manageRooms', Rooms::class);

        $query = Rooms::query()
            ->when($request->search, fn ($q, $search) => $q->where(function ($sq) use ($search) {
                $sq->where('roomName', 'like', "%{$search}%")
                    ->orWhere('building', 'like', "%{$search}%");
            }));

        // Every column this desk paints is a column the rooms table stores — Name, Building and Capacity — so all
        // three carry headers. roomId is the tie-break, not an offered order, because no column on the screen shows
        // it; and the catalogue opens on the name because a room list is read top to bottom, not newest first.
        $sortable = [
            'roomName' => ['column' => 'roomName'],
            'building' => ['column' => 'building'],
            'capacity' => ['column' => 'capacity'],
        ];
        $sort = ListSort::apply(
            $query,
            (string) $request->input('sort', ''),
            (string) $request->input('direction', 'asc'),
            $sortable,
            'roomName',
            'rooms.roomId'
        );

        $rooms = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/ReferenceData/Rooms', [
            'rooms' => $rooms,
            // Both order keys come back with the filters: a control the desk set that the handler obeyed but never
            // echoed resets on the next render while the list stays narrowed — the defect lane 46 owns.
            'filters' => $request->only(['search']) + [
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
            ],
            'sort' => ListSort::present($sort, $sortable),
        ]);
    }

    public function storeRoom(Request $request): RedirectResponse
    {
        $this->authorize('manageRooms', Rooms::class);
        $validated = $request->validate([
            'roomName' => 'required|string|max:100',
            'capacity' => 'required|integer|min:1',
            'building' => 'nullable|string|max:100',
        ]);

        Rooms::create([
            'roomName' => $validated['roomName'],
            'capacity' => $validated['capacity'],
            // rooms.building is NOT NULL; an empty or absent building still has to insert.
            'building' => $validated['building'] ?? '',
        ]);

        return back()->with('success', 'Room created.');
    }

    public function updateRoom(Request $request, Rooms $room): RedirectResponse
    {
        $this->authorize('manageRooms', Rooms::class);

        $validated = $request->validate([
            'roomName' => 'required|string|max:100',
            'capacity' => 'required|integer|min:1',
            'building' => 'nullable|string|max:100',
        ]);

        $room->update([
            'roomName' => $validated['roomName'],
            'capacity' => $validated['capacity'],
            'building' => $validated['building'] ?? '',
        ]);

        return back()->with('success', 'Room updated.');
    }

    public function destroyRoom(Rooms $room): RedirectResponse
    {
        $this->authorize('manageRooms', Rooms::class);

        if ($blocked = $this->blockedDeletionMessage([
            'schedule' => DB::table('schedules')->where('roomId', $room->roomId)->count(),
        ])) {
            return back()->with('error', $blocked);
        }

        $room->delete();

        return back()->with('success', 'Room deleted.');
    }

    // ============ BLOCKS ============
    public function blocks(Request $request): Response
    {
        $this->authorize('manageBlocks', Blocks::class);

        $query = Blocks::with(['course', 'term.academicYear'])
            ->when($request->search, fn ($q, $search) => $q->where(function ($sq) use ($search) {
                $sq->where('blockName', 'like', "%{$search}%")
                    ->orWhereHas('course', fn ($cq) => $cq->where('courseName', 'like', "%{$search}%"));
            }));

        // Three of the five painted columns are stored on `blocks`; the course name sits one hop away and is reached
        // through `blocks.courseId`, which the migration declares NOT NULL with a foreign key and which live `ems`
        // answers with zero nulls and zero orphans — so an INNER hop cannot drop a row here. The Term column is
        // deliberately not offered: it prints the academic-year label and the semester joined into one string, so no
        // single column is what the desk reads, and an order on either half would mislabel itself. `scheduleStatus`
        // is a real enum but this screen paints nothing of it, and `blockId` is the tie-break rather than a header.
        $sortable = [
            'blockName' => ['column' => 'blockName'],
            'course' => [
                'column' => 'courseName',
                'join' => 'courses',
                'foreign' => 'courseId',
                'local' => 'blocks.courseId',
            ],
            'yearLevel' => ['column' => 'yearLevel'],
            'maxStudents' => ['column' => 'maxStudents'],
        ];
        $sort = ListSort::apply(
            $query,
            (string) $request->input('sort', ''),
            (string) $request->input('direction', 'asc'),
            $sortable,
            'blockName',
            'blocks.blockId'
        );

        $blocks = $query->paginate(20)->withQueryString();
        $courses = Courses::all(['courseId', 'courseName']);
        $terms = Academicterms::with('academicYear')->get(['termId', 'semester', 'academicYearId']);

        return Inertia::render('Admin/ReferenceData/Blocks', [
            'blocks' => $blocks,
            'courses' => $courses,
            'terms' => $terms,
            // Both order keys come back with the filters: a control the desk set that the handler obeyed but never
            // echoed resets on the next render while the list stays narrowed — the defect lane 46 owns.
            'filters' => $request->only(['search']) + [
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
            ],
            'sort' => ListSort::present($sort, $sortable),
        ]);
    }

    public function storeBlock(Request $request): RedirectResponse
    {
        $this->authorize('manageBlocks', Blocks::class);
        Blocks::create($request->validate([
            'courseId' => 'required|exists:courses,courseId',
            'termId' => 'required|exists:academicterms,termId',
            'yearLevel' => 'required|integer|min:1|max:5',
            'blockName' => 'required|string|max:50',
            'maxStudents' => 'required|integer|min:1',
        ]));

        return back()->with('success', 'Block created.');
    }

    public function updateBlock(Request $request, Blocks $block): RedirectResponse
    {
        $this->authorize('manageBlocks', Blocks::class);
        $block->update($request->validate([
            'blockName' => 'required|string|max:50',
            'maxStudents' => 'required|integer|min:1',
        ]));

        return back()->with('success', 'Block updated.');
    }

    public function destroyBlock(Blocks $block): RedirectResponse
    {
        $this->authorize('manageBlocks', Blocks::class);

        if ($blocked = $this->blockedDeletionMessage([
            'schedule' => DB::table('schedules')->where('blockId', $block->blockId)->count(),
            'enrolled subject' => DB::table('enrolledsubjects')->where('blockId', $block->blockId)->count(),
        ])) {
            return back()->with('error', $blocked);
        }

        $block->delete();

        return back()->with('success', 'Block deleted.');
    }

    // ============ ADMISSION REQUIREMENTS ============
    public function admissionRequirements(Request $request): Response
    {
        $this->authorize('manageAdmissionRequirements', Admissionrequirements::class);

        $query = Admissionrequirements::query()
            ->when($request->search, fn ($q, $search) => $q->where('requirementName', 'like', "%{$search}%"))
            ->when($request->appliesTo, fn ($q, $appliesTo) => $q->where('appliesTo', $appliesTo))
            ->when($request->status === 'required', fn ($q) => $q->where('isRequired', true))
            ->when($request->status === 'optional', fn ($q) => $q->where('isRequired', false));

        // All three painted columns are stored, so all three are offered. `appliesTo` is an ENUM, and the owner's
        // ruling of 2026-10-31 is that a desk may sort one: on the school's server it orders by the declaration the
        // school itself wrote into the migration, which is the order a window wants, not an accident of the driver.
        // `isRequired` is a flag, and a flag is a column the query holds — the screen paints it as a badge, but the
        // order is taken from the column, not from the badge. `requirementId` is the tie-break only: no column shows it.
        $sortable = [
            'requirementName' => ['column' => 'requirementName'],
            'appliesTo' => ['column' => 'appliesTo'],
            'isRequired' => ['column' => 'isRequired'],
        ];
        $sort = ListSort::apply(
            $query,
            (string) $request->input('sort', ''),
            (string) $request->input('direction', 'asc'),
            $sortable,
            'requirementName',
            'admissionrequirements.requirementId'
        );

        $requirements = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/ReferenceData/AdmissionRequirements', [
            'requirements' => $requirements,
            'appliesTo' => collect(AppliesTo::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
            // Every control the desk set comes back, the two order keys with the three filters: a control obeyed but
            // never echoed resets on the next render while the list stays narrowed — the defect lane 46 owns.
            'filters' => $request->only(['search', 'appliesTo', 'status']) + [
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
            ],
            'sort' => ListSort::present($sort, $sortable),
        ]);
    }

    public function storeAdmissionRequirement(Request $request): RedirectResponse
    {
        $this->authorize('manageAdmissionRequirements', Admissionrequirements::class);
        // isRequired is NOT NULL and an unchecked box is absent from the payload.
        Admissionrequirements::create(array_merge(
            $request->validate([
                'requirementName' => 'required|string|max:255',
                'appliesTo' => 'required|in:firstYear,transferee,continuing,shifter,all',
                'isRequired' => 'boolean',
            ]),
            ['isRequired' => $request->boolean('isRequired')]
        ));

        return back()->with('success', 'Requirement created.');
    }

    public function updateAdmissionRequirement(Request $request, Admissionrequirements $req): RedirectResponse
    {
        $this->authorize('manageAdmissionRequirements', Admissionrequirements::class);
        $req->update(array_merge(
            $request->validate([
                'requirementName' => 'required|string|max:255',
                'appliesTo' => 'required|in:firstYear,transferee,continuing,shifter,all',
                'isRequired' => 'boolean',
            ]),
            ['isRequired' => $request->boolean('isRequired')]
        ));

        return back()->with('success', 'Requirement updated.');
    }

    public function destroyAdmissionRequirement(Admissionrequirements $req): RedirectResponse
    {
        $this->authorize('manageAdmissionRequirements', Admissionrequirements::class);

        if ($blocked = $this->blockedDeletionMessage([
            'submitted requirement' => DB::table('studentrequirementsubmissions')->where('requirementId', $req->requirementId)->count(),
        ])) {
            return back()->with('error', $blocked);
        }

        $req->delete();

        return back()->with('success', 'Requirement deleted.');
    }

    // ============ CLEARANCE REQUIREMENTS ============
    public function clearanceRequirements(Request $request): Response
    {
        $this->authorize('manageClearanceRequirements', Clearancerequirements::class);

        $requirements = Clearancerequirements::with('office')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('requirementName', 'like', "%{$search}%")
                        ->orWhereHas('office', fn ($oq) => $oq->where('officeName', 'like', "%{$search}%"));
                });
            });

        // The desk reads an office NAME, so the order that opens the list is alphabetical by that name. The
        // sequence the screen had before this wiring was officeId ascending (Registrar, Accounting, Scholarship…),
        // which is the seeder's numbering rather than a reading order, and the two genuinely differ on live ems:
        // by name the first row is Academic Department and the last is Scholarship.
        $sortable = [
            'office' => [
                'column' => 'officeName',
                'join' => 'offices',
                'foreign' => 'officeId',
                'local' => 'clearancerequirements.officeId',
            ],
            'requirementName' => ['column' => 'requirementName'],
        ];
        $sort = ListSort::apply(
            $requirements,
            (string) $request->input('sort', ''),
            (string) $request->input('direction', 'asc'),
            $sortable,
            'office',
            'clearancerequirements.clearanceRequirementId',
        );

        $page = $requirements->paginate(20)->withQueryString();
        $offices = Offices::all(['officeId', 'officeName']);

        return Inertia::render('Admin/ReferenceData/ClearanceRequirements', [
            'requirements' => $page,
            'offices' => $offices,
            'filters' => $request->only(['search'])
                + ['sort' => $sort['column'], 'direction' => $sort['direction']],
            'sort' => ListSort::present($sort, $sortable),
        ]);
    }

    public function storeClearanceRequirement(Request $request): RedirectResponse
    {
        $this->authorize('manageClearanceRequirements', Clearancerequirements::class);
        Clearancerequirements::create($request->validate([
            'officeId' => 'required|exists:offices,officeId',
            'requirementName' => 'required|string|max:150',
        ]));

        return back()->with('success', 'Clearance requirement created.');
    }

    public function updateClearanceRequirement(Request $request, Clearancerequirements $req): RedirectResponse
    {
        $this->authorize('manageClearanceRequirements', Clearancerequirements::class);
        $req->update($request->validate([
            'officeId' => 'required|exists:offices,officeId',
            'requirementName' => 'required|string|max:150',
        ]));

        return back()->with('success', 'Clearance requirement updated.');
    }

    public function destroyClearanceRequirement(Clearancerequirements $req): RedirectResponse
    {
        $this->authorize('manageClearanceRequirements', Clearancerequirements::class);

        // clearanceapprovals rows point at the requirement and the FK refuses a delete,
        // so a slip that has already been signed keeps its line. Saying so beats a raw
        // constraint violation on the screen that only wanted to tidy the list.
        if ($req->clearanceapprovals()->exists()) {
            return back()->with('error', 'This requirement has already been signed on a clearance slip and cannot be deleted.');
        }

        $req->delete();

        return back()->with('success', 'Clearance requirement deleted.');
    }

    /**
     * Reference-data rows are named by transactional rows through foreign keys that
     * either refuse the delete outright or null the reference out. Either way a
     * Delete click on a row in use answers with a database error, or quietly un-pins
     * the enrollments that recorded it. Say what still points at the row instead.
     *
     * @param  array<string, int>  $usages  label => rows still attached
     */
    private function blockedDeletionMessage(array $usages): ?string
    {
        $attached = collect($usages)
            ->filter(fn ($count) => $count > 0)
            ->map(fn ($count, $label) => "{$count} {$label}(s)")
            ->implode(', ');

        return $attached === ''
            ? null
            : "Cannot delete: this record is still named by {$attached}. Detach those first.";
    }
}
