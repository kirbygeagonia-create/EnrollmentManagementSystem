<?php

namespace App\Http\Controllers\Evaluation;

use App\Enums\AcademicStanding;
use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Addresses;
use App\Models\Creditedsubjects;
use App\Models\Curriculums;
use App\Models\Curriculumsubjects;
use App\Models\Educationalinstitutions;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Guardians;
use App\Models\Religions;
use App\Models\Subjects;
use App\Models\Transferacademicrecords;
use App\Services\EnrollmentStateMachine;
use App\Services\WorkflowService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $query = Enrollments::with(['student', 'course', 'major', 'term', 'evaluatedBy'])
            ->where('enrollmentStatus', EnrollmentStatus::Pending)
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->latest();

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
    public function show(Enrollments $enrollment): Response
    {
        $this->authorize('view', $enrollment);

        $enrollment->load([
            'student.addresses',
            'student.guardians',
            'student.educationalBackgrounds.institution',
            'course',
            'major',
            'term.academicYear',
            'admission',
            'enrolledSubjects.subject',
        ]);

        $curriculum = Curriculums::where('courseId', $enrollment->courseId)
            ->where('majorId', $enrollment->majorId)
            ->latest('effectiveYear')
            ->first();

        $curriculumSubjects = $curriculum ? Curriculumsubjects::with('subject', 'prerequisiteSubject')
            ->where('curriculumId', $curriculum->curriculumId)
            ->where('yearLevel', $enrollment->yearLevel)
            ->where('semesterOffered', $enrollment->term->semester)
            ->get() : collect();

        return Inertia::render('Evaluation/Show', [
            'enrollment' => $enrollment,
            'curriculumSubjects' => $curriculumSubjects,
            'religions' => Religions::all(['religionId', 'religionName']),
            'academicStandings' => AcademicStanding::cases(),
        ]);
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
            'evaluatedBy' => Auth::id(),
        ]);

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

        // Clear existing proposed subjects
        $enrollment->enrolledSubjects()->where('status', EnrolledSubjectStatus::Proposed)->delete();

        foreach ($validated['subjects'] as $subj) {
            Enrolledsubjects::create([
                'enrollmentId' => $enrollment->enrollmentId,
                'subjectId' => $subj['subjectId'],
                'status' => EnrolledSubjectStatus::Proposed,
            ]);
        }

        // Transition enrollment to evaluated
        $this->stateMachine->transition($enrollment, EnrollmentStatus::Evaluated, Auth::user(), 'Subject load proposed by evaluator');

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

        return back()->with('success', 'Credits processed successfully.');
    }

    /**
     * Sign evaluation (evaluator + dean).
     */
    public function sign(Request $request, Enrollments $enrollment): RedirectResponse
    {
        $this->authorize('sign', $enrollment);

        $enrollment->update([
            'formSignedDate' => now(),
        ]);

        // Create workflow if not exists
        if (! $enrollment->enrollmentworkflow) {
            $this->workflowService->createWorkflow($enrollment);
        }

        return back()->with('success', 'Evaluation signed. Workflow created.');
    }
}
