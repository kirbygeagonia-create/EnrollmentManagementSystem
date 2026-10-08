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
use App\Support\ReferenceDataSections;
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

        $courses = Courses::with('unit')
            ->when($request->search, fn ($q, $search) => $q->where(function ($sq) use ($search) {
                $sq->where('courseName', 'like', "%{$search}%")
                    ->orWhere('courseCode', 'like', "%{$search}%");
            }))
            ->when($request->unit, fn ($q, $unit) => $q->where('unitId', $unit))
            ->orderByDesc('courseId')
            ->paginate(20)
            ->withQueryString();
        $units = Academicunits::all(['unitId', 'unitName']);

        return Inertia::render('Admin/ReferenceData/Courses', [
            'courses' => $courses,
            'units' => $units,
            'filters' => $request->only(['search', 'unit']),
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

        $majors = Majors::with('course')
            ->when($request->search, fn ($q, $search) => $q->where('majorName', 'like', "%{$search}%"))
            ->when($request->courseId, fn ($q, $courseId) => $q->where('courseId', $courseId))
            ->orderByDesc('majorId')
            ->paginate(20)
            ->withQueryString();
        $courses = Courses::all(['courseId', 'courseName']);

        return Inertia::render('Admin/ReferenceData/Majors', [
            'majors' => $majors,
            'courses' => $courses,
            'filters' => $request->only(['search', 'courseId']),
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
            }))
            ->orderByDesc('curriculumId')
            ->paginate(20)
            ->withQueryString();
        $courses = Courses::all(['courseId', 'courseName']);
        $majors = Majors::all(['majorId', 'majorName']);

        return Inertia::render('Admin/ReferenceData/Curriculums', [
            'curriculums' => $curriculums,
            'courses' => $courses,
            'majors' => $majors,
            'filters' => $request->only(['search']),
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

        $subjects = Subjects::query()
            ->when($request->search, fn ($q, $search) => $q->where(function ($sq) use ($search) {
                $sq->where('subjectName', 'like', "%{$search}%")
                    ->orWhere('subjectCode', 'like', "%{$search}%");
            }))
            ->when($request->type, fn ($q, $type) => $q->where('subjectType', $type))
            ->orderByDesc('subjectId')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/ReferenceData/Subjects', [
            'subjects' => $subjects,
            'subjectTypes' => collect(SubjectType::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
            'filters' => $request->only(['search', 'type']),
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

        $terms = Academicterms::with('academicYear')
            ->when($request->semester, fn ($q, $semester) => $q->where('semester', $semester))
            ->when($request->status === 'active', fn ($q) => $q->where('startDate', '<=', now()->toDateString())->where('endDate', '>=', now()->toDateString()))
            ->when($request->status === 'inactive', fn ($q) => $q->where(fn ($sq) => $sq->where('startDate', '>', now()->toDateString())->orWhere('endDate', '<', now()->toDateString())))
            ->when($request->search, fn ($q, $search) => $q->whereHas('academicYear', fn ($yq) => $yq->where('yearLabel', 'like', "%{$search}%")))
            ->orderByDesc('termId')
            ->paginate(20)
            ->withQueryString();
        $years = Academicyears::all(['academicYearId', 'yearLabel']);

        return Inertia::render('Admin/ReferenceData/Terms', [
            'terms' => $terms,
            'years' => $years,
            'semesters' => collect(Semester::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
            'filters' => $request->only(['search', 'semester', 'status']),
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

        $feeTypes = Feetypes::query()
            ->when($request->search, fn ($q, $search) => $q->where('feeName', 'like', "%{$search}%"))
            ->when($request->unitBasis, fn ($q, $basis) => $q->where('unitBasis', $basis))
            ->orderByDesc('feeTypeId')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/ReferenceData/FeeTypes', [
            'feeTypes' => $feeTypes,
            'unitBases' => collect(FeeUnitBasis::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
            'filters' => $request->only(['search', 'unitBasis']),
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

        $bands = Gradescale::query()
            ->when($request->search, fn ($q, $search) => $q->where('description', 'like', "%{$search}%"))
            ->when($request->filled('isPassing'), fn ($q) => $q->where('isPassing', $request->boolean('isPassing')))
            ->orderBy('minGrade')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/ReferenceData/GradeScales', [
            'bands' => $bands,
            'passingCeiling' => Gradescale::passingCeiling(),
            'hasGradeScale' => Gradescale::isConfigured(),
            'filters' => $request->only(['search', 'isPassing']),
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

        $types = Scholarshiptypes::query()
            ->when($request->search, fn ($q, $search) => $q->where('scholarshipName', 'like', "%{$search}%"))
            ->when($request->coverage, fn ($q, $coverage) => $q->where('coverageType', $coverage))
            ->orderByDesc('scholarshipTypeId')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/ReferenceData/ScholarshipTypes', [
            'types' => $types,
            'coverageTypes' => collect(CoverageType::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
            'filters' => $request->only(['search', 'coverage']),
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

        $offices = Offices::query()
            ->when($request->search, fn ($q, $search) => $q->where('officeName', 'like', "%{$search}%"))
            ->orderBy('officeId')
            ->paginate(20)
            ->withQueryString();

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
            'filters' => $request->only(['search']),
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

        $rooms = Rooms::query()
            ->when($request->search, fn ($q, $search) => $q->where(function ($sq) use ($search) {
                $sq->where('roomName', 'like', "%{$search}%")
                    ->orWhere('building', 'like', "%{$search}%");
            }))
            ->orderByDesc('roomId')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/ReferenceData/Rooms', [
            'rooms' => $rooms,
            'filters' => $request->only(['search']),
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

        $blocks = Blocks::with(['course', 'term.academicYear'])
            ->when($request->search, fn ($q, $search) => $q->where(function ($sq) use ($search) {
                $sq->where('blockName', 'like', "%{$search}%")
                    ->orWhereHas('course', fn ($cq) => $cq->where('courseName', 'like', "%{$search}%"));
            }))
            ->orderByDesc('blockId')
            ->paginate(20)
            ->withQueryString();
        $courses = Courses::all(['courseId', 'courseName']);
        $terms = Academicterms::with('academicYear')->get(['termId', 'semester', 'academicYearId']);

        return Inertia::render('Admin/ReferenceData/Blocks', [
            'blocks' => $blocks,
            'courses' => $courses,
            'terms' => $terms,
            'filters' => $request->only(['search']),
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

        $requirements = Admissionrequirements::query()
            ->when($request->search, fn ($q, $search) => $q->where('requirementName', 'like', "%{$search}%"))
            ->when($request->appliesTo, fn ($q, $appliesTo) => $q->where('appliesTo', $appliesTo))
            ->when($request->status === 'required', fn ($q) => $q->where('isRequired', true))
            ->when($request->status === 'optional', fn ($q) => $q->where('isRequired', false))
            ->orderByDesc('requirementId')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/ReferenceData/AdmissionRequirements', [
            'requirements' => $requirements,
            'appliesTo' => collect(AppliesTo::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
            'filters' => $request->only(['search', 'appliesTo', 'status']),
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
            })
            ->orderBy('officeId')
            ->orderBy('clearanceRequirementId')
            ->paginate(20)
            ->withQueryString();
        $offices = Offices::all(['officeId', 'officeName']);

        return Inertia::render('Admin/ReferenceData/ClearanceRequirements', [
            'requirements' => $requirements,
            'offices' => $offices,
            'filters' => $request->only(['search']),
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
