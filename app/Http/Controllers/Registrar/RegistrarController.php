<?php

namespace App\Http\Controllers\Registrar;

use App\Enums\ClearanceOverallStatus;
use App\Enums\ClearancePeriodStatus;
use App\Enums\DocumentType;
use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\StudentType;
use App\Http\Controllers\Controller;
use App\Models\Clearanceperiods;
use App\Models\Documentprintlog;
use App\Models\Enrollments;
use App\Models\Studentclearances;
use App\Models\Students;
use App\Models\Subjects;
use App\Services\EnrollmentStateMachine;
use App\Services\WorkflowService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegistrarController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private EnrollmentStateMachine $stateMachine,
        private WorkflowService $workflowService
    ) {}

    /**
     * Display registrar approval queue with validation checklist.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Enrollments::class);

        $query = Enrollments::with([
            'student', 'course', 'major', 'term',
            'studentassessments', 'enrollmentworkflow',
            'enrolledSubjects.subject',
        ])
            ->whereIn('enrollmentStatus', [EnrollmentStatus::Assessed, EnrollmentStatus::Paid])
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->latest();

        $enrollments = $query->paginate(20)->withQueryString();

        return Inertia::render('Registrar/Index', [
            'enrollments' => $enrollments,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show enrollment for approval with validation checklist.
     */
    public function show(Enrollments $enrollment): Response
    {
        $this->authorize('view', $enrollment);

        $enrollment->load([
            'student.addresses',
            'student.guardians',
            'course',
            'major',
            'term.academicYear',
            'studentassessments.charges.feeType',
            'enrollmentworkflow.workflowsteps.office',
            'enrolledSubjects.subject',
            'admission',
        ]);

        // Validation checklist
        $checklist = [
            'evaluation_signed' => (bool) $enrollment->evaluatedBy,
            'assessment_completed' => (bool) $enrollment->studentassessments,
            'payment_completed' => $enrollment->studentassessments?->remainingBalance <= 0,
            'clearance_verified' => $this->checkClearance($enrollment),
            'registrarApprovalPending' => (bool) ($enrollment->enrollmentworkflow?->workflowsteps()->where('stepStatus', 'pending')->orderBy('stepOrder')->first()?->officeId === 1),
        ];

        $allValid = collect($checklist)->every(fn ($v) => $v);

        return Inertia::render('Registrar/Show', [
            'enrollment' => $enrollment,
            'checklist' => $checklist,
            'allValid' => $allValid,
        ]);
    }

    /**
     * Check clearance for continuing students.
     */
    private function checkClearance(Enrollments $enrollment): bool
    {
        if (! in_array($enrollment->studentType->value, ['continuing', 'shifter'])) {
            return true; // First-year and transferee don't need clearance
        }

        $currentPeriod = Clearanceperiods::where('periodStatus', ClearancePeriodStatus::Open)->first();
        if (! $currentPeriod) {
            return true; // No open period
        }

        $clearance = Studentclearances::where('studentId', $enrollment->studentId)
            ->where('clearancePeriodId', $currentPeriod->clearancePeriodId)
            ->first();

        return $clearance
            && $clearance->overallStatus === ClearanceOverallStatus::Approved
            && $clearance->receivedBy
            && $clearance->receivedDate;
    }

    /**
     * Approve enrollment (mark as enrolled).
     * BR31: enrollmentType derived from studentType (new/old)
     * BR12: Must pass all prior phases
     */
    public function approve(Request $request, Enrollments $enrollment): RedirectResponse
    {
        $this->authorize('approve', $enrollment);

        // Validate prerequisites
        $checklist = [
            'evaluation_signed' => (bool) $enrollment->evaluatedBy,
            'assessment_completed' => (bool) $enrollment->studentassessments,
            'payment_completed' => $enrollment->studentassessments?->remainingBalance <= 0,
            'clearance_verified' => $this->checkClearance($enrollment),
        ];

        if (collect($checklist)->contains(false)) {
            return back()->withErrors(['validation' => 'Not all prerequisites are met.']);
        }

        // Determine enrollment type (BR31)
        $enrollmentType = in_array($enrollment->studentType->value, ['firstYear', 'transferee'])
            ? EnrollmentType::New
            : EnrollmentType::Old;

        // Record/update student data
        if ($enrollmentType === EnrollmentType::New) {
            // First-year/transferee: record new data (already captured in evaluation)
        } else {
            // Continuing/shifter: update existing data
            // Data already updated in evaluation phase
        }

        // Confirm enrolled subjects
        $enrollment->enrolledSubjects()
            ->where('status', EnrolledSubjectStatus::Proposed)
            ->update(['status' => EnrolledSubjectStatus::Confirmed]);

        // Transition to enrolled
        $this->stateMachine->transition($enrollment, EnrollmentStatus::Enrolled, Auth::user(), 'Registrar approved enrollment');

        $enrollment->update([
            'enrollmentType' => $enrollmentType,
            'registrarProcessedBy' => Auth::id(),
            'enrolledDate' => now(),
        ]);

        // Sign workflow step 5 (Registrar Approval)
        $workflow = $enrollment->enrollmentworkflow;
        if ($workflow) {
            $this->workflowService->signStepByOffice($workflow, 1, Auth::user());
        }

        return redirect()->route('registrar.index')->with('success', 'Enrollment approved successfully.');
    }

    /**
     * Print enrollment certificate.
     */
    public function printCertificate(Enrollments $enrollment): Response
    {
        $this->authorize('printCertificate', $enrollment);

        $enrollment->load([
            'student', 'course', 'major', 'term.academicYear',
            'enrolledSubjects.subject', 'registrarProcessedBy',
        ]);

        // Log print
        Documentprintlog::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'documentType' => DocumentType::Certificate,
            'printedDate' => now(),
            'printedBy' => Auth::id(),
            'documentNumber' => Documentprintlog::where('enrollmentId', $enrollment->enrollmentId)
                ->where('documentType', DocumentType::Certificate)
                ->count() + 1,
        ]);

        return Inertia::render('Registrar/PrintCertificate', [
            'enrollment' => $enrollment,
        ]);
    }

    /**
     * Print class cards (one per subject).
     */
    public function printClassCards(Enrollments $enrollment): Response
    {
        $this->authorize('printClassCards', $enrollment);

        $enrollment->load([
            'student', 'course', 'major', 'term.academicYear',
            'enrolledSubjects.subject.schedule.room', 'enrolledSubjects.subject.schedule.instructor',
            'registrarProcessedBy',
        ]);

        // Log prints
        foreach ($enrollment->enrolledSubjects as $index => $subject) {
            Documentprintlog::create([
                'enrollmentId' => $enrollment->enrollmentId,
                'documentType' => DocumentType::ClassCard,
                'printedDate' => now(),
                'printedBy' => Auth::id(),
                'documentNumber' => $index + 1,
            ]);
        }

        return Inertia::render('Registrar/PrintClassCards', [
            'enrollment' => $enrollment,
        ]);
    }

    /**
     * Print subject load.
     */
    public function printSubjectLoad(Enrollments $enrollment): Response
    {
        $this->authorize('printSubjectLoad', $enrollment);

        $enrollment->load([
            'student', 'course', 'major', 'term.academicYear',
            'enrolledSubjects.subject', 'registrarProcessedBy',
        ]);

        Documentprintlog::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'documentType' => DocumentType::SubjectLoad,
            'printedDate' => now(),
            'printedBy' => Auth::id(),
            'documentNumber' => Documentprintlog::where('enrollmentId', $enrollment->enrollmentId)
                ->where('documentType', DocumentType::SubjectLoad)
                ->count() + 1,
        ]);

        return Inertia::render('Registrar/PrintSubjectLoad', [
            'enrollment' => $enrollment,
        ]);
    }
}
