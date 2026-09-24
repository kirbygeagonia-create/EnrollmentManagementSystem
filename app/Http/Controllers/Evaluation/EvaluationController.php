<?php

namespace App\Http\Controllers\Evaluation;

use App\Enums\AcademicStanding;
use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\ExamStage;
use App\Enums\ExamType;
use App\Http\Controllers\Controller;
use App\Models\Addresses;
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
use App\Models\Subjects;
use App\Models\Transferacademicrecords;
use App\Services\EnrollmentService;
use App\Services\EnrollmentStateMachine;
use App\Services\WorkflowService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private EnrollmentStateMachine $stateMachine,
        private WorkflowService $workflowService
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

        return Inertia::render('Evaluation/Index', [
            'enrollments' => $enrollments,
            'filters' => $request->only(['search']),
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
            'enrolledSubjects.subject',
            // Item 6: the credit-transfer panel renders existing credited subjects.
            'creditedsubjects.creditedToSubject',
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
            'curriculumSubjects' => $curriculumSubjects,
            'curriculum' => $curriculum?->only(['curriculumId', 'curriculumName', 'effectiveYear']),
            'unmetPrerequisiteSubjectIds' => $unmetPrerequisiteSubjectIds,
            'religions' => Religions::all(['religionId', 'religionName']),
            'academicStandings' => collect(AcademicStanding::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
            'retentionExam' => $retentionResult ? [
                'examId' => $retentionResult->examId,
                'examResult' => $retentionResult->examResult->value,
                'examDate' => $retentionResult->examDate,
            ] : null,
            'can' => [
                'recordRetention' => $request->user()->can('recordRetention', $enrollment),
            ],
        ]);
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

        return Curriculums::where('courseId', $enrollment->courseId)
            ->when($enrollment->majorId, fn ($q) => $q->where('majorId', $enrollment->majorId))
            ->latest('effectiveYear')
            ->first() ?? Curriculums::where('courseId', $enrollment->courseId)->latest('effectiveYear')->first();
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
        $passingCeiling = Gradescale::where('isPassing', true)->max('maxGrade');
        $passingCeiling = $passingCeiling !== null ? (float) $passingCeiling : 3.0;

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
            $student->update([
                'lastName' => $validated['lastName'],
                'firstName' => $validated['firstName'],
                'middleName' => $validated['middleName'],
                'suffix' => $validated['suffix'],
                'gender' => $validated['gender'],
                'birthdate' => $validated['birthdate'],
                'birthplace' => $validated['birthplace'],
                'citizenship' => $validated['citizenship'],
                'religionId' => $validated['religionId'],
                'civilStatus' => $validated['civilStatus'],
                'contactNumber' => $validated['contactNumber'],
                'telephoneNumber' => $validated['telephoneNumber'],
                'email' => $validated['email'],
                'semestersCompleted' => $validated['semestersCompleted'],
                'yearsInInstitution' => $validated['yearsInInstitution'],
            ]);

            // Update addresses (home, current, permanent)
            foreach ($validated['addresses'] as $addr) {
                Addresses::updateOrCreate(
                    ['studentId' => $student->studentId, 'addressType' => $addr['addressType']],
                    array_merge($addr, ['studentId' => $student->studentId])
                );
            }

            // Update guardians
            $student->guardians()->delete();
            foreach ($validated['guardians'] as $guardian) {
                Guardians::create(array_merge($guardian, ['studentId' => $student->studentId]));
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

        $validated = $request->validate([
            'credits' => 'required|array',
            'credits.*.previousSubjectName' => 'required|string|max:255',
            'credits.*.creditedToSubjectId' => 'required|exists:subjects,subjectId',
            'credits.*.creditedUnits' => 'required|numeric|min:0',
            'credits.*.institutionName' => 'required|string|max:255',
            'credits.*.institutionType' => 'required|in:elementary,secondary,seniorHigh,college,graduate',
            'credits.*.grade' => 'nullable|numeric',
            'credits.*.remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($enrollment, $validated) {
            foreach ($validated['credits'] as $credit) {
                $institution = Educationalinstitutions::firstOrCreate(
                    ['institutionName' => $credit['institutionName']],
                    ['institutionType' => $credit['institutionType']]
                );

                $transferRecord = Transferacademicrecords::create([
                    'studentId' => $enrollment->studentId,
                    'institutionId' => $institution->institutionId,
                    'subjectNameAtOldSchool' => $credit['previousSubjectName'],
                    'unitsAtOldSchool' => $credit['creditedUnits'],
                    'gradeAtOldSchool' => $credit['grade'],
                    'passResult' => 'passed',
                ]);

                Creditedsubjects::create([
                    'enrollmentId' => $enrollment->enrollmentId,
                    'transferRecordId' => $transferRecord->transferRecordId,
                    'previousSubjectName' => $credit['previousSubjectName'],
                    'creditedToSubjectId' => $credit['creditedToSubjectId'],
                    'creditedUnits' => $credit['creditedUnits'],
                    'remarks' => $credit['remarks'],
                ]);
            }
        });

        return back()->with('success', 'Credits processed successfully.');
    }

    /**
     * Sign evaluation (evaluator + dean).
     */
    public function sign(Request $request, Enrollments $enrollment): RedirectResponse
    {
        $this->authorize('sign', $enrollment);

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
            $this->workflowService->signStepByOffice($enrollment->enrollmentworkflow, 4, Auth::user());
        });

        return back()->with('success', 'Evaluation signed. Workflow created.');
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
