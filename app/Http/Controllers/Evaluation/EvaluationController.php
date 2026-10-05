<?php

namespace App\Http\Controllers\Evaluation;

use App\Enums\AcademicStanding;
use App\Enums\ClearanceOverallStatus;
use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamType;
use App\Enums\InstitutionType;
use App\Enums\OfficeId;
use App\Enums\PassResult;
use App\Enums\ShiftRequestStatus;
use App\Enums\StudentType;
use App\Http\Controllers\Controller;
use App\Models\Academicterms;
use App\Models\Addresses;
use App\Models\Clearanceperiods;
use App\Models\Courses;
use App\Models\Creditedsubjects;
use App\Models\Curriculums;
use App\Models\Curriculumsubjects;
use App\Models\Educationalinstitutions;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Examresults;
use App\Models\Gradescale;
use App\Models\Guardians;
use App\Models\Religions;
use App\Models\Shiftingrequests;
use App\Models\Studentclearances;
use App\Models\Students;
use App\Models\Subjects;
use App\Models\Transferacademicrecords;
use App\Services\AcademicStandingService;
use App\Services\EnrollmentIssuer;
use App\Services\EnrollmentService;
use App\Services\EnrollmentStateMachine;
use App\Services\WorkflowService;
use App\Support\StudentRecordDefaults;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private EnrollmentStateMachine $stateMachine,
        private WorkflowService $workflowService,
        private AcademicStandingService $standingService,
        private EnrollmentIssuer $issuer
    ) {}

    /**
     * Display evaluation queue.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Enrollments::class);

        $query = Enrollments::with(['student', 'course', 'major', 'term', 'evaluatedByUser'])
            // Item 8: enrollments returned by the Registrar land back on this
            // desk with a return reason, alongside fresh Pending records.
            ->whereIn('enrollmentStatus', [EnrollmentStatus::Pending, EnrollmentStatus::ReturnedToEvaluation])
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->orderByDesc('enrollmentId');

        $enrollments = $query->paginate(20)->withQueryString();

        // Ruling 2 (G-1): this desk issues the returning student's enrollment, so the
        // screen is given the students who actually repeat a term. A student with no
        // earlier term is an applicant and belongs at Admission, which is why they are
        // not offered here.
        $canIssue = $request->user()->hasPermissionTo('evaluation.create');

        return Inertia::render('Evaluation/Index', [
            'enrollments' => $enrollments,
            'filters' => $request->only(['search']),
            'canIssueEnrollment' => $canIssue,
            'returningStudents' => $canIssue ? $this->returningStudents() : [],
            'terms' => $canIssue
                ? Academicterms::with('academicYear:academicYearId,yearLabel')->orderByDesc('termId')
                    ->get(['termId', 'academicYearId', 'semester', 'startDate', 'endDate'])
                : [],
            'courses' => $canIssue ? Courses::orderBy('courseName')->get(['courseId', 'courseCode', 'courseName']) : [],
        ]);
    }

    /**
     * Show enrollment form wizard (demographic profile + subject load).
     * BR32: All demographic fields must be filled
     */
    public function show(Request $request, Enrollments $enrollment): Response
    {
        $this->authorize('view', $enrollment);

        $enrollment->load([
            'student.addresses',
            'student.guardians',
            'student.educationalBackgrounds.institution',
            'course.unit',
            'major',
            'term.academicYear',
            'admission',
            'clearanceConfirmedByUser',
            'enrolledSubjects.subject',
            // Item 6: the credit-transfer panel renders existing credited subjects.
            'creditedsubjects.creditedToSubject',
            // The desk signs the first box of the workflow form, so it shows the
            // boxes its own signature opens.
            'enrollmentworkflow.workflowsteps.office',
            'enrollmentworkflow.workflowsteps.signedBy',
        ]);

        // Item 7: the enrollment stays pinned to the curriculum version the
        // student was admitted under — never silently drifts to a newer one.
        $curriculum = $this->resolveCurriculum($enrollment);

        $semester = $enrollment->term?->semester instanceof \BackedEnum
            ? $enrollment->term->semester->value
            : '1st';

        $curriculumSubjects = $curriculum ? Curriculumsubjects::with('subject', 'prerequisiteSubject')
            ->where('curriculumId', $curriculum->curriculumId)
            ->where('yearLevel', $enrollment->yearLevel)
            ->where('semesterOffered', $semester)
            ->get() : collect();

        // Item 7: which offered subjects have an unmet prerequisite, so the
        // UI can lock them before the evaluator even clicks.
        $satisfied = $this->satisfiedPrerequisites($enrollment);
        $unmetPrerequisiteSubjectIds = $curriculumSubjects
            ->filter(fn ($cs) => $cs->prerequisiteSubjectId && ! in_array($cs->prerequisiteSubjectId, $satisfied))
            ->pluck('subjectId')
            ->values();

        // Item 4: the retention exam result is recorded and viewed here, in the
        // Academic Evaluation area, by the owning academic department (BR10).
        $retentionResult = Examresults::where('studentId', $enrollment->studentId)
            ->where('courseId', $enrollment->courseId)
            ->where('examStage', ExamStage::Retention->value)
            ->orderByDesc('examId')
            ->first();

        return Inertia::render('Evaluation/Show', [
            'enrollment' => $enrollment,
            // Ruling 5: the desk is told whether there is an approved slip on file to
            // confirm, so it is never offered a button that only comes back as a refusal.
            'passSlipOnFile' => $this->approvedPassSlip($enrollment) !== null,
            'curriculumSubjects' => $curriculumSubjects,
            'curriculum' => $curriculum?->only(['curriculumId', 'curriculumName', 'effectiveYear']),
            'unmetPrerequisiteSubjectIds' => $unmetPrerequisiteSubjectIds,
            'religions' => Religions::all(['religionId', 'religionName']),
            'academicStandings' => collect(AcademicStanding::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
            // The standing is decided here, from the grades already on file — so
            // the desk sees the evidence and the recommendation it is based on,
            // not just a dropdown.
            'standingReport' => $this->standingService->derive($enrollment),
            'retentionExam' => $retentionResult ? [
                'examId' => $retentionResult->examId,
                'examResult' => $retentionResult->examResult->value,
                'examDate' => $retentionResult->examDate,
            ] : null,
            'can' => [
                'recordRetention' => $request->user()->can('recordRetention', $enrollment),
                'decideStanding' => $request->user()->can('proposeSubjects', $enrollment),
                'captureProfile' => $request->user()->can('captureProfile', $enrollment),
                // Ruling 5: only the desks that may confirm a pass slip see the control.
                'confirmClearance' => $request->user()->can('confirmClearance', $enrollment),
            ],
            // BR32: the checklist the desk has to clear before it can sign, and
            // the same list sign() refuses on. One definition, two readers. Keyed
            // so the screen can name each reason where that field is shown.
            'profileGaps' => $this->missingProfileFields($enrollment),
            'signBlockers' => $this->signBlockers($enrollment),
        ]);
    }

    /**
     * The students this desk may issue an enrollment for (ruling 2 / G-1), each with the
     * year level their own record implies (G-2) so the issue form opens on a number the
     * desk confirms or corrects instead of one typed from memory.
     *
     * The list is every student with an enrollment that still stands — a dropped one never
     * happened — while the level counts only completed years, which is the narrower
     * question of where the student belongs.
     *
     * @return array<int, array<string, mixed>>
     */
    private function returningStudents(): array
    {
        $notDropped = fn ($query) => $query->whereNotIn('enrollmentStatus', [EnrollmentStatus::Dropped->value]);

        $students = Students::whereHas('enrollments', $notDropped)
            ->with(['enrollments' => fn ($q) => $q->whereNotIn('enrollmentStatus', [EnrollmentStatus::Dropped->value])
                ->latest('termId')->limit(1)->with(['course:courseId,courseCode,courseName', 'major:majorId,majorName', 'term:termId,semester'])])
            ->orderBy('lastName')->orderBy('firstName')
            ->get(['studentId', 'schoolIdNumber', 'firstName', 'middleName', 'lastName']);

        $enteringTermId = Academicterms::covering()?->termId;

        $levels = Enrollments::derivedYearLevels(
            $students->pluck('studentId')->all(),
            // Suggested for the term the calendar covers today, since that is the term a
            // desk is most often issuing into. Picking a different term does not silently
            // re-derive the number: placement stays the department's editable field.
            $enteringTermId === null ? null : (int) $enteringTermId
        );

        return $students
            ->map(fn (Students $student) => [
                ...$student->only(['studentId', 'schoolIdNumber', 'firstName', 'middleName', 'lastName']),
                'enrollments' => $student->enrollments,
                'derivedYearLevel' => $levels[(int) $student->studentId] ?? 1,
            ])
            ->all();
    }

    /**
     * Resolve the curriculum version for an enrollment (item 7). A pinned
     * curriculumId always wins; otherwise fall back to the newest version —
     * which is then pinned on the first proposal.
     */
    private function resolveCurriculum(Enrollments $enrollment): ?Curriculums
    {
        if ($enrollment->curriculumId) {
            return Curriculums::find($enrollment->curriculumId);
        }

        // Records created before the pin existed fall back to the same rule the issuing
        // desks use, so a student's screen and the enrollment form they were issued on
        // cannot disagree about which catalog is "current".
        return Curriculums::currentFor(
            (int) $enrollment->courseId,
            $enrollment->majorId === null ? null : (int) $enrollment->majorId
        );
    }

    /**
     * Subject ids whose prerequisites the student has already satisfied:
     * passed (best grade within the passing band — PH scale, lower is
     * better; Gradescale rows win over the 3.00 fallback) or credited
     * through transfer.
     *
     * @return int[]
     */
    private function satisfiedPrerequisites(Enrollments $enrollment): array
    {
        $passingCeiling = Gradescale::passingCeiling();

        $bestGrades = Enrolledsubjects::query()
            ->join('enrollments as e2', 'e2.enrollmentId', '=', 'enrolledsubjects.enrollmentId')
            ->where('e2.studentId', $enrollment->studentId)
            ->whereNotNull('enrolledsubjects.grade')
            ->groupBy('enrolledsubjects.subjectId')
            ->selectRaw('enrolledsubjects.subjectId, MIN(enrolledsubjects.grade) as best_grade')
            ->pluck('best_grade', 'subjectId');

        $passed = $bestGrades
            ->filter(fn ($grade) => (float) $grade <= $passingCeiling)
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();

        $credited = Creditedsubjects::where('enrollmentId', $enrollment->enrollmentId)
            ->pluck('creditedToSubjectId')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($passed, $credited)));
    }

    /**
     * The demographic fields BR32 requires before this desk may forward the form.
     *
     * One definition serves both readers — the checklist the screen renders and the
     * refusal sign() returns — so a desk can never be shown "complete" for what the
     * server still calls incomplete. The list is captureProfile()'s own required
     * rules: the form that writes these columns is what defines them.
     *
     * @return string[] labels of the fields still missing, in form order
     */
    private function missingProfileFields(Enrollments $enrollment): array
    {
        $student = $enrollment->student;
        $missing = [];

        $personFields = [
            'lastName' => 'Last name',
            'firstName' => 'First name',
            'gender' => 'Gender',
            'birthdate' => 'Birthdate',
            'birthplace' => 'Birthplace',
            'citizenship' => 'Citizenship',
            'religionId' => 'Religion',
            'civilStatus' => 'Civil status',
            'contactNumber' => 'Contact number',
            'email' => 'Email address',
        ];

        foreach ($personFields as $column => $label) {
            if ($student === null || blank($student->{$column})) {
                $missing[] = $label;
            }
        }

        // A first-year has completed zero semesters, and zero is real data —
        // blank(0) is true, so these two are checked against null only.
        foreach (['semestersCompleted' => 'Semesters completed', 'yearsInInstitution' => 'Years in the institution'] as $column => $label) {
            if ($student === null || $student->{$column} === null) {
                $missing[] = $label;
            }
        }

        $addresses = $student->addresses ?? collect();
        if ($addresses->count() < 2) {
            $missing[] = 'Home and current address';
        } elseif ($addresses->contains(fn ($address) => blank($address->barangay)
            || blank($address->cityMunicipality)
            || blank($address->province)
            || blank($address->country))) {
            $missing[] = 'Address barangay, city, province and country';
        }

        $guardians = $student->guardians ?? collect();
        if ($guardians->isEmpty()) {
            $missing[] = 'Parent or guardian';
        } elseif ($guardians->contains(fn ($guardian) => blank($guardian->fullName) || blank($guardian->contactNumber))) {
            $missing[] = 'Guardian name and contact number';
        }

        if (blank($enrollment->academicStanding)) {
            $missing[] = 'Academic standing';
        }

        if (blank($enrollment->formIssuedDate)) {
            $missing[] = 'Form issued date';
        }

        return $missing;
    }

    /**
     * Capture full demographic profile from enrollment form.
     * BR32: Every field must be filled
     */
    public function captureProfile(Request $request, Enrollments $enrollment): RedirectResponse
    {
        $this->authorize('captureProfile', $enrollment);

        $validated = $request->validate([
            'lastName' => 'required|string|max:100',
            'firstName' => 'required|string|max:100',
            'middleName' => 'nullable|string|max:100',
            'suffix' => 'nullable|string|max:20',
            'gender' => 'required|in:male,female',
            'birthdate' => 'required|date',
            'birthplace' => 'required|string|max:255',
            'citizenship' => 'required|string|max:100',
            'religionId' => 'required|exists:religions,religionId',
            'civilStatus' => 'required|in:single,married,widowed,separated',
            'contactNumber' => 'required|string|max:20',
            'telephoneNumber' => 'nullable|string|max:20',
            'email' => 'required|email|max:255',
            'addresses' => 'required|array|min:2',
            'addresses.*.addressType' => 'required|in:home,current,permanent',
            'addresses.*.houseBuildingNo' => 'nullable|string|max:100',
            'addresses.*.street' => 'nullable|string|max:255',
            'addresses.*.sitioPurok' => 'nullable|string|max:100',
            'addresses.*.barangay' => 'required|string|max:100',
            'addresses.*.cityMunicipality' => 'required|string|max:100',
            'addresses.*.district' => 'nullable|string|max:100',
            'addresses.*.province' => 'required|string|max:100',
            'addresses.*.region' => 'nullable|string|max:100',
            'addresses.*.zipCode' => 'nullable|string|max:20',
            'addresses.*.country' => 'required|string|max:100',
            'guardians' => 'required|array|min:1',
            'guardians.*.relationship' => 'required|in:mother,father,guardian,other',
            'guardians.*.fullName' => 'required|string|max:255',
            'guardians.*.contactNumber' => 'required|string|max:20',
            'guardians.*.email' => 'nullable|email|max:255',
            'guardians.*.isEmergencyContact' => 'boolean',
            'guardians.*.isAuthorizedToActOnBehalf' => 'boolean',
            'semestersCompleted' => 'required|integer|min:0',
            'yearsInInstitution' => 'required|integer|min:0',
            'academicStanding' => 'required|in:regular,irregular',
            'formIssuedDate' => 'required|date',
        ]);

        DB::transaction(function () use ($enrollment, $validated) {
            $student = $enrollment->student;
            $student->update(StudentRecordDefaults::person([
                'lastName' => $validated['lastName'],
                'firstName' => $validated['firstName'],
                'middleName' => $validated['middleName'] ?? null,
                'suffix' => $validated['suffix'] ?? null,
                'gender' => $validated['gender'],
                'birthdate' => $validated['birthdate'],
                'birthplace' => $validated['birthplace'],
                'citizenship' => $validated['citizenship'],
                'religionId' => $validated['religionId'],
                'civilStatus' => $validated['civilStatus'],
                'contactNumber' => $validated['contactNumber'],
                'telephoneNumber' => $validated['telephoneNumber'] ?? null,
                'email' => $validated['email'],
                'semestersCompleted' => $validated['semestersCompleted'],
                'yearsInInstitution' => $validated['yearsInInstitution'],
            ]));

            // Update addresses (home, current, permanent)
            foreach ($validated['addresses'] as $addr) {
                Addresses::updateOrCreate(
                    ['studentId' => $student->studentId, 'addressType' => $addr['addressType']],
                    StudentRecordDefaults::address(array_merge($addr, ['studentId' => $student->studentId]))
                );
            }

            // Update guardians
            $student->guardians()->delete();
            foreach ($validated['guardians'] as $guardian) {
                Guardians::create(StudentRecordDefaults::guardian(array_merge($guardian, ['studentId' => $student->studentId])));
            }

            $enrollment->update([
                'academicStanding' => $validated['academicStanding'],
                'formIssuedDate' => $validated['formIssuedDate'],
                'evaluatedBy' => Auth::user()->userId,
            ]);
        });

        return back()->with('success', 'Profile captured successfully.');
    }

    /**
     * Record the standing and the year level the evaluating department decided.
     *
     * BR18: academic standing drives what study load the student may carry, so it
     * has to be settled here — before the load is proposed — by the desk that can
     * see the student's grades. Admission no longer guesses it, and the Registrar
     * still has the last word at approval.
     *
     * The level travels with it because this is the only desk that can see the
     * evidence that sets it (§28 G-2): intake writes 1 for every student, and the
     * curriculum lookup and the block search both read this column, so a transferee
     * placed in second year is offered first-year subjects until someone records
     * the placement. Promoting the level automatically each term is a separate
     * question the Registrar has still to answer; this is only the capture.
     *
     * Gated on `proposeSubjects` rather than a new ability: deciding the standing
     * is the same academic judgement as prescribing the load for it, it already
     * restricts the action to the owning evaluator or a delegate, and it already
     * refuses once the enrollment has left this desk.
     */
    public function decideStanding(Request $request, Enrollments $enrollment): RedirectResponse
    {
        $this->authorize('proposeSubjects', $enrollment);

        $validated = $request->validate([
            'academicStanding' => ['required', 'in:'.implode(',', array_column(AcademicStanding::cases(), 'value'))],
            'yearLevel' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $standing = AcademicStanding::from($validated['academicStanding']);
        $yearLevel = (int) $validated['yearLevel'];
        $previousLevel = (int) $enrollment->yearLevel;

        $enrollment->update([
            'academicStanding' => $standing,
            'yearLevel' => $yearLevel,
            'evaluatedBy' => Auth::user()->userId,
        ]);

        $derived = $this->standingService->derive($enrollment);

        // When the department's call differs from what the records suggest, say so
        // plainly instead of silently accepting it — the override is legitimate
        // (the evaluator has the documents, the database does not), but it should
        // be visible on the desk and to the Registrar.
        $overrides = $derived['canDerive'] && $derived['derived'] !== $standing->value;

        // The level is what the curriculum lookup and the block search read, so a
        // placement that moved has to be said out loud: the load proposed before
        // this change was resolved against the old year.
        $placed = $previousLevel !== $yearLevel
            ? " Placement set to year level {$yearLevel} (was {$previousLevel}); re-propose the load if it was drawn against the old year."
            : '';

        return back()->with('success', ($overrides
            ? "Standing recorded as {$standing->value}, against the derivation ({$derived['derived']})."
            : "Standing recorded as {$standing->value}.").$placed
            .' The Registrar confirms the final call at approval.');
    }

    /**
     * Propose subject load from curriculum.
     * BR17: Student type determines phases
     * BR18: Academic standing affects subject assignment
     */
    public function proposeSubjects(Request $request, Enrollments $enrollment): RedirectResponse
    {
        $this->authorize('proposeSubjects', $enrollment);

        $validated = $request->validate([
            'subjects' => 'required|array',
            'subjects.*.subjectId' => 'required|exists:subjects,subjectId',
            'subjects.*.curriculumSubjectId' => 'nullable|exists:curriculumsubjects,curriculumSubjectId',
        ]);

        // Validate no duplicate subjectId in the same payload
        $subjectIds = collect($validated['subjects'])->pluck('subjectId')->all();
        if (count($subjectIds) !== count(array_unique($subjectIds))) {
            throw ValidationException::withMessages([
                'subjects' => 'Duplicate subject IDs are not allowed in the same proposal.',
            ]);
        }

        // Load curriculum subjects for this enrollment's year level and term
        // (item 7: resolved through the enrollment's pinned version).
        $curriculum = $this->resolveCurriculum($enrollment);

        $semesterVal = $enrollment->term?->semester instanceof \BackedEnum
            ? $enrollment->term->semester->value
            : '1st';

        $curriculumSubjects = $curriculum ? Curriculumsubjects::with('subject')
            ->where('curriculumId', $curriculum->curriculumId)
            ->where('yearLevel', $enrollment->yearLevel)
            ->where('semesterOffered', $semesterVal)
            ->get() : collect();

        // Elective group validation
        $electiveGroups = $curriculumSubjects->where('is_elective', true)->groupBy('elective_group');
        foreach ($electiveGroups as $groupName => $groupSubjects) {
            if (! $groupName) {
                continue; // Skip electives without a group
            }
            $minChoices = $groupSubjects->first()->elective_min_choices ?? 0;
            $maxChoices = $groupSubjects->first()->elective_max_choices ?? $groupSubjects->count();
            $groupSubjectIds = $groupSubjects->pluck('subjectId')->toArray();
            $proposedInGroup = array_intersect($subjectIds, $groupSubjectIds);
            $count = count($proposedInGroup);

            if ($count < $minChoices || $count > $maxChoices) {
                throw ValidationException::withMessages([
                    'subjects' => "Elective group '{$groupName}' requires between {$minChoices} and {$maxChoices} subjects. {$count} selected.",
                ]);
            }
        }

        // Mandatory subjects validation (non-elective curriculum subjects must be proposed)
        // Subtract subjects already credited (transferee/shifter credit transfers)
        $mandatorySubjectIds = $curriculumSubjects->where('is_elective', false)->pluck('subjectId')->toArray();

        $creditedSubjectIds = Creditedsubjects::where('enrollmentId', $enrollment->enrollmentId)
            ->pluck('creditedToSubjectId')
            ->toArray();
        $mandatorySubjectIds = array_diff($mandatorySubjectIds, $creditedSubjectIds);

        // Irregular, transferee, and shifter students may carry a partial
        // subject load — only enforce mandatory-block completeness for
        // regular students.
        $isFlexibleStudent = $enrollment->academicStanding === AcademicStanding::Irregular
            || in_array($enrollment->studentType->value, ['transferee', 'shifter']);

        if (! $isFlexibleStudent) {
            $missingMandatory = array_diff($mandatorySubjectIds, $subjectIds);
            if (! empty($missingMandatory)) {
                throw ValidationException::withMessages([
                    'subjects' => 'The following mandatory subjects are required but not in the proposal: '.implode(', ', $missingMandatory),
                ]);
            }
        }

        // Item 7: prerequisite auto-gate — a subject whose curriculum-defined
        // prerequisite the student has neither passed nor credited cannot be
        // proposed. Evaluated across the WHOLE pinned curriculum, not just
        // this term's offerings.
        if ($curriculum) {
            $satisfied = $this->satisfiedPrerequisites($enrollment);
            $violations = Curriculumsubjects::with('subject', 'prerequisiteSubject')
                ->where('curriculumId', $curriculum->curriculumId)
                ->whereNotNull('prerequisiteSubjectId')
                ->whereIn('subjectId', $subjectIds)
                ->get()
                ->filter(fn ($cs) => ! in_array($cs->prerequisiteSubjectId, $satisfied));

            if ($violations->isNotEmpty()) {
                $details = $violations->map(fn ($cs) => sprintf(
                    '%s requires %s',
                    $cs->subject->subjectCode ?? $cs->subjectId,
                    $cs->prerequisiteSubject->subjectCode ?? $cs->prerequisiteSubjectId
                ))->implode('; ');

                throw ValidationException::withMessages([
                    'subjects' => 'Prerequisite requirements not met: '.$details.'.',
                ]);
            }
        }

        DB::transaction(function () use ($enrollment, $validated, $curriculum) {
            // Item 7: pin the enrollment to the curriculum version it was
            // evaluated against (first proposal stamps it).
            if ($curriculum && ! $enrollment->curriculumId) {
                $enrollment->update(['curriculumId' => $curriculum->curriculumId]);
            }

            // Clear existing proposed subjects
            $enrollment->enrolledSubjects()->where('status', EnrolledSubjectStatus::Proposed)->delete();

            foreach ($validated['subjects'] as $subj) {
                $attemptInfo = EnrollmentService::determineAttemptNumber($enrollment->enrollmentId, $subj['subjectId']);

                Enrolledsubjects::create([
                    'enrollmentId' => $enrollment->enrollmentId,
                    'subjectId' => $subj['subjectId'],
                    'status' => EnrolledSubjectStatus::Proposed,
                    'attempt_number' => $attemptInfo['attempt'],
                    'original_enrolled_subject_id' => $attemptInfo['originalId'],
                ]);
            }

            // Transition enrollment to evaluated
            $this->stateMachine->transition($enrollment, EnrollmentStatus::Evaluated, Auth::user(), 'Subject load proposed by evaluator');
        });

        return back()->with('success', 'Subject load proposed. Enrollment moved to evaluated status.');
    }

    /**
     * Process credit transfer (transferee/shifter).
     */
    public function processCredits(Request $request, Enrollments $enrollment): RedirectResponse
    {
        $this->authorize('processCredits', $enrollment);

        // Every rule below is bounded by the column it lands in:
        // transferacademicrecords.subjectNameAtOldSchool is varchar(150),
        // unitsAtOldSchool/gradeAtOldSchool are decimal(3,1)/decimal(3,2) and both
        // NOT NULL, and creditedsubjects.remarks is a NOT NULL text.
        $validated = $request->validate([
            'credits' => 'required|array',
            'credits.*.previousSubjectName' => 'required|string|max:150',
            'credits.*.creditedToSubjectId' => 'required|exists:subjects,subjectId',
            'credits.*.creditedUnits' => 'required|numeric|min:0|max:9.9',
            'credits.*.institutionName' => 'required|string|max:150',
            'credits.*.institutionType' => ['required', Rule::enum(InstitutionType::class)],
            'credits.*.cityMunicipality' => 'required|string|max:150',
            'credits.*.province' => 'required|string|max:150',
            'credits.*.grade' => 'required|numeric|between:1,5',
            'credits.*.remarks' => 'nullable|string',
        ]);

        // The recorded grade decides whether the prior subject was passed; it
        // cannot be assumed. A credit is what subtracts a mandatory subject from
        // the load and satisfies a prerequisite, so writing every transfer row as
        // "passed" exempted subjects the student had actually failed elsewhere.
        $ceiling = Gradescale::passingCeiling();
        $credited = 0;
        $retained = 0;

        DB::transaction(function () use ($enrollment, $validated, $ceiling, &$credited, &$retained) {
            foreach ($validated['credits'] as $credit) {
                $institution = Educationalinstitutions::firstOrCreate(
                    ['institutionName' => $credit['institutionName']],
                    [
                        'institutionType' => $credit['institutionType'],
                        'cityMunicipality' => $credit['cityMunicipality'],
                        'province' => $credit['province'],
                    ]
                );

                $passed = (float) $credit['grade'] <= $ceiling;

                // Every prior subject and grade stays on the record, passed or not.
                $transferRecord = Transferacademicrecords::create([
                    'studentId' => $enrollment->studentId,
                    'institutionId' => $institution->institutionId,
                    'subjectNameAtOldSchool' => $credit['previousSubjectName'],
                    'unitsAtOldSchool' => $credit['creditedUnits'],
                    'gradeAtOldSchool' => $credit['grade'],
                    'passResult' => ($passed ? PassResult::Passed : PassResult::Failed)->value,
                ]);

                if (! $passed) {
                    $retained++;

                    continue;
                }

                Creditedsubjects::create([
                    'enrollmentId' => $enrollment->enrollmentId,
                    'transferRecordId' => $transferRecord->transferRecordId,
                    'previousSubjectName' => $credit['previousSubjectName'],
                    'creditedToSubjectId' => $credit['creditedToSubjectId'],
                    'creditedUnits' => $credit['creditedUnits'],
                    'remarks' => $credit['remarks'] ?? '',
                ]);

                $credited++;
            }
        });

        return back()->with('success', $retained > 0
            ? "Credits processed: {$credited} credited, {$retained} kept on the record but not credited — the grade is beyond the {$ceiling} passing line."
            : 'Credits processed successfully.');
    }

    /**
     * Sign evaluation (evaluator + dean).
     */
    public function sign(Request $request, Enrollments $enrollment): RedirectResponse
    {
        $this->authorize('sign', $enrollment);

        // BR32: the form cannot be forwarded while a required demographic field
        // is missing, and concern #14: a continuing student cannot be forwarded
        // up a year level without the retention examination this department has
        // recorded as passed. The screen disables the button from the same list,
        // so this is the server holding the identical line — a stale page or a
        // direct POST cannot push an unproven enrollment on to the desks behind
        // it, which is how a fee sheet used to get computed on top of it.
        $blockers = $this->signBlockers($enrollment);
        if ($blockers !== []) {
            throw ValidationException::withMessages($blockers);
        }

        DB::transaction(function () use ($enrollment) {
            $enrollment->update([
                'formSignedDate' => now(),
            ]);

            // Create workflow if not exists
            if (! $enrollment->enrollmentworkflow) {
                $this->workflowService->createWorkflow($enrollment);
            }

            // Sign the Department Evaluation step (office 4) — the evaluator signs here
            $enrollment->load('enrollmentworkflow');
            $this->workflowService->signStepByOffice($enrollment->enrollmentworkflow, OfficeId::Guidance->value, Auth::user());
        });

        return back()->with('success', 'Evaluation signed. Workflow created.');
    }

    /**
     * Every reason this enrollment cannot be signed yet, keyed by the field the
     * desk has to fix. One definition for the server and the screen, so the
     * button can never offer what the POST will refuse.
     *
     * @return array<string, string>
     */
    private function signBlockers(Enrollments $enrollment): array
    {
        $blockers = [];

        $missing = $this->missingProfileFields($enrollment);
        if ($missing !== []) {
            $blockers['profile'] = 'Capture the profile first — still missing: '.implode(', ', $missing).'.';
        }

        if (($retention = $this->retentionBlocker($enrollment)) !== null) {
            $blockers['retention'] = $retention;
        }

        return $blockers;
    }

    /**
     * Concern #14, verbatim: "other students continuing takes the retention exam
     * as a proof that they really learned anything before proceeding to higher
     * year level". Until this the result was recorded and displayed but gated
     * nothing, so a continuing student with a fail — or with no examination at
     * all — moved forward exactly like one who had passed.
     */
    private function retentionBlocker(Enrollments $enrollment): ?string
    {
        // Ruling 11: for a shift, proof of readiness is the form plus the credit
        // evaluation — no retention examination and no course examination. The student has
        // already proven they can carry SEAIT work; what the receiving department answers
        // is which subjects they are exempt from, and that is the credit panel on this same
        // screen. Demanding a retention paper here would examine them for a program they
        // are leaving.
        if (Shiftingrequests::where('grantedEnrollmentId', $enrollment->enrollmentId)
            ->where('requestStatus', ShiftRequestStatus::Granted->value)
            ->exists()) {
            return null;
        }

        $isReturning = in_array($enrollment->studentType->value, [StudentType::Continuing->value, StudentType::Shifter->value], true);

        if (! $isReturning || ! $enrollment->course?->requiresRetentionExam) {
            return null;
        }

        // Read against this enrollment's own term: a pass from an earlier term
        // proves fitness to have continued then, not now.
        $retention = Examresults::where('studentId', $enrollment->studentId)
            ->where('courseId', $enrollment->courseId)
            ->where('termId', $enrollment->termId)
            ->where('examStage', ExamStage::Retention->value)
            ->orderByDesc('examId')
            ->first();

        if (! $retention) {
            return 'No retention examination recorded for this term — a returning student in a program that examines retention must pass it before the form is forwarded.';
        }

        if ($retention->examResult !== ExamResult::Pass) {
            return 'The retention examination on file reads '.$retention->examResult->value.', not pass.';
        }

        return null;
    }

    /**
     * Issue the enrollment form for a returning student (G-1, ruling 2).
     *
     * §6.3: stages 1-3 — intake, the general entrance examination, the admission
     * decision — are not repeated by a student who has already completed a SEAIT term;
     * they live on the student record permanently. What repeats every term starts at this
     * desk. Until now the only code in app/ that created an enrollments row was the
     * admission decision, so the returning student's ladder could only be demonstrated by
     * writing the row by hand, and a returning student reached this queue by accident of
     * the seeded data rather than by an action a desk could take on screen.
     *
     * The workflow form is built here, not at signing: the six or seven boxes are the
     * record's shape from its first moment (WorkflowService::stepsFor() drops Assessment
     * for returning types), and every desk behind this one reads that form.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'studentId' => 'required|exists:students,studentId',
            'termId' => 'required|exists:academicterms,termId',
            'courseId' => 'required|exists:courses,courseId',
            'majorId' => 'nullable|exists:majors,majorId',
            'yearLevel' => 'required|integer|min:1|max:5',
            'studentType' => ['required', Rule::in([StudentType::Continuing->value, StudentType::Shifter->value])],
        ]);

        $student = Students::findOrFail($validated['studentId']);
        $course = Courses::findOrFail($validated['courseId']);

        // Ruling 2: no new permission name. The desk that already holds
        // `evaluation.create` — the right to issue an enrollment form — is the desk
        // allowed to make the record.
        $this->authorize('create', [Enrollments::class, $student, $course]);

        // A student with no earlier term is an applicant, not a returnee. Their first
        // enrollment comes from the admission decision, which sits behind the intake and
        // the entrance examination — creating one here would let a student into the
        // program without either.
        $prior = Enrollments::where('studentId', $student->studentId)
            ->where('termId', '!=', $validated['termId'])
            ->latest('termId')
            ->first();

        if ($prior === null) {
            return back()->withErrors([
                'studentId' => "{$student->lastName}, {$student->firstName} has no enrollment in an earlier term — a student's first enrollment is created by the Admission decision, not here.",
            ]);
        }

        // Detection rule, ruling 11: "when the enrolled course does not match history,
        // confirm and mark shifter". A program change is a shift request — the form the
        // student signs, the dean or program head endorses and the Guidance Councillor
        // decides — so issuing a plain continuation here would let a shift happen as an
        // unremarkable edit and leave G-7 exactly where it was.
        if ((int) $prior->courseId !== (int) $validated['courseId']) {
            return back()->withErrors([
                'courseId' => "This student's last term was in a different program (course {$prior->courseId}). A program change is filed as a shift request, which the dean or program head endorses and Guidance decides — not issued as an ordinary enrollment.",
            ]);
        }

        // Ruling 3 (G-4): one active enrollment per student per term, read from the one
        // place that question is asked. A dropped record does not hold the seat.
        $holding = EnrollmentIssuer::seatHolder((int) $student->studentId, (int) $validated['termId']);

        if ($holding !== null) {
            return back()->withErrors([
                'termId' => "This student already holds enrollment #{$holding->enrollmentId} in the chosen term ({$holding->enrollmentStatus->value}) — one active enrollment per student per term.",
            ]);
        }

        $enrollment = $this->issuer->issue([
            'studentId' => $student->studentId,
            'courseId' => $validated['courseId'],
            'majorId' => $validated['majorId'] ?? null,
            'termId' => $validated['termId'],
            'yearLevel' => $validated['yearLevel'],
            // No application is filed again for an internal continuation (§11).
            'admissionId' => null,
            'studentType' => $validated['studentType'],
            // BR31: a returning student's record updates the one the school already holds.
            'enrollmentType' => EnrollmentType::Old,
        ], Auth::user());

        return redirect()->route('evaluation.show', $enrollment->enrollmentId)
            ->with('success', 'Enrollment form issued for '.strtolower($student->lastName).', '.strtolower($student->firstName).' — Year '.$enrollment->yearLevel.'.');
    }

    /**
     * The student's cleared slip in the window now accepting clearances, or null.
     *
     * This is the screen's answer to "is there anything to confirm" — confirmClearance()
     * reads the same `accepting()` window but keeps its own granular refusal, because a
     * desk told "no window is open" and a desk told "this slip is not approved" have
     * different work to do.
     */
    private function approvedPassSlip(Enrollments $enrollment): ?Studentclearances
    {
        $window = Clearanceperiods::accepting()->first();

        if ($window === null) {
            return null;
        }

        $slip = Studentclearances::where('studentId', $enrollment->studentId)
            ->where('clearancePeriodId', $window->clearancePeriodId)
            ->first();

        return $slip?->overallStatus === ClearanceOverallStatus::Approved ? $slip : null;
    }

    /**
     * Confirm the student's clearance pass slip at this desk (ruling 5).
     *
     * A confirmation is a human act with a name on it: the department looked at the slip
     * the student carried in. It is refused when there is no cleared slip in the window now
     * accepting clearances, because then there is nothing on file to have looked at, and it
     * can be withdrawn — a confirmation lifts the Registrar's block, and a wrong one must
     * not be permanent. Withdrawing is not deleting: the audit log keeps both acts.
     */
    public function confirmClearance(Request $request, Enrollments $enrollment): RedirectResponse
    {
        $this->authorize('confirmClearance', $enrollment);

        $request->validate(['confirmed' => 'required|boolean']);

        if (! $request->boolean('confirmed')) {
            $enrollment->update([
                'clearanceConfirmedBy' => null,
                'clearanceConfirmedAt' => null,
            ]);

            return back()->with('success', 'Clearance confirmation withdrawn. The Registrar will hold this record again.');
        }

        $window = Clearanceperiods::accepting()->first();

        $slip = $window === null ? null : Studentclearances::where('studentId', $enrollment->studentId)
            ->where('clearancePeriodId', $window->clearancePeriodId)
            ->first();

        if ($slip === null) {
            return back()->withErrors([
                'clearanceConfirmed' => $window === null
                    ? 'No clearance window is accepting slips, so there is nothing on file to confirm.'
                    : 'This student has no clearance slip in the window now accepting clearances, so there is nothing on file to confirm.',
            ]);
        }

        if ($slip->overallStatus !== ClearanceOverallStatus::Approved) {
            return back()->withErrors([
                'clearanceConfirmed' => 'The slip on file reads '.$slip->overallStatus->value.', not approved — the offices have not passed this student yet.',
            ]);
        }

        $enrollment->update([
            'clearanceConfirmedBy' => Auth::user()->userId,
            'clearanceConfirmedAt' => now(),
        ]);

        return back()->with('success', 'Pass slip confirmed. The clearance-passed indicator now carries through to the Registrar.');
    }

    /**
     * Record the retention exam result for the enrollment. Item 4: retention
     * exams are handled and viewed only by the owning academic department, in
     * the Academic Evaluation area (BR10) — mainly board courses, though any
     * course flagged requiresRetentionExam gates here.
     */
    public function recordRetention(Request $request, Enrollments $enrollment): RedirectResponse
    {
        $this->authorize('recordRetention', $enrollment);

        $validated = $request->validate([
            'examResult' => 'required|in:pass,fail',
            'examDate' => 'required|date',
        ]);

        // One retention result per student/course/term — re-recording on the
        // evaluation page corrects it rather than duplicating it. The exam
        // attaches to the enrollment's own term: the gate applies to this
        // term's progression decision.
        $retention = Examresults::firstOrNew([
            'studentId' => $enrollment->studentId,
            'courseId' => $enrollment->courseId,
            'termId' => $enrollment->termId,
            'examStage' => ExamStage::Retention->value,
        ]);
        $retention->examType = ExamType::CourseSpecific;
        $retention->examResult = $validated['examResult'];
        $retention->examDate = $validated['examDate'];
        $retention->save();

        return redirect()->route('evaluation.show', $enrollment->enrollmentId)
            ->with('success', 'Retention exam recorded.');
    }
}
