<?php

namespace App\Http\Controllers\Registrar;

use App\Enums\AcademicStanding;
use App\Enums\ClearanceOverallStatus;
use App\Enums\ClearancePeriodStatus;
use App\Enums\DocumentType;
use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\PaymentStatus;
use App\Enums\StudentType;
use App\Enums\WorkflowStepStatus;
use App\Http\Controllers\Controller;
use App\Models\Clearanceperiods;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Studentclearances;
use App\Models\Students;
use App\Models\Subjects;
use App\Services\AcademicStandingService;
use App\Services\EnrollmentStateMachine;
use App\Services\PrintService;
use App\Services\WorkflowService;
use App\Support\EnrollmentReadiness;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RegistrarController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private EnrollmentStateMachine $stateMachine,
        private WorkflowService $workflowService,
        private AcademicStandingService $standingService
    ) {}

    /**
     * Display registrar approval queue with validation checklist.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Enrollments::class);

        $query = Enrollments::with([
            'student', 'course', 'major', 'term',
            'studentassessments', 'enrollmentworkflow.workflowsteps',
            'enrolledSubjects.subject', 'payments',
        ])
            ->whereIn('enrollmentStatus', [EnrollmentStatus::Assessed, EnrollmentStatus::Paid])
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->orderByDesc('enrollmentId');

        $enrollments = $query->paginate(20)->withQueryString();

        // The desk used to have to open every record to learn whether it could
        // be approved. This prints the same five gates beside each row, keyed by
        // enrollment, naming the ones still outstanding.
        $openPeriod = Clearanceperiods::where('periodStatus', ClearancePeriodStatus::Open)->first();
        $readiness = collect($enrollments->getCollection())
            ->mapWithKeys(function (Enrollments $enrollment) use ($openPeriod) {
                $gates = $this->checklist($enrollment, $openPeriod);

                return [$enrollment->enrollmentId => [
                    'met' => collect($gates)->filter()->count(),
                    'total' => count($gates),
                    'waiting' => array_keys(array_filter($gates, fn ($passed) => ! $passed)),
                ]];
            });

        return Inertia::render('Registrar/Index', [
            'enrollments' => $enrollments,
            'readiness' => $readiness,
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
            'enrollmentworkflow.workflowsteps.signedBy',
            'enrolledSubjects.subject',
            'admission',
            'payments',
        ]);

        // Validation checklist — the same five gates the approval enforces and
        // the queue prints beside each record.
        $checklist = $this->checklist($enrollment);

        $allValid = collect($checklist)->every(fn ($v) => $v);

        return Inertia::render('Registrar/Show', [
            'enrollment' => $enrollment,
            'checklist' => $checklist,
            'allValid' => $allValid,
            // The Registrar makes the FINAL call on the standing, so the desk is
            // given the same evidence the evaluator saw — what the records derive
            // and whether the department agreed with it — rather than an empty
            // dropdown to guess at.
            'standingReport' => $this->standingService->derive($enrollment),
        ]);
    }

    /**
     * The five gates a record must clear before the Registrar signs it, in the
     * order the desks actually clear them.
     *
     * Shared by the queue, the desk and the approval itself so the list can
     * never offer what the server would refuse.
     *
     * @return array<string, bool>
     */
    private function checklist(Enrollments $enrollment, ?Clearanceperiods $openPeriod = null): array
    {
        $assessment = $enrollment->studentassessments;

        // A missing fee sheet used to read as "owes nothing", because null
        // compares below zero. Payment now passes only on a settled assessment
        // or a receipt — which matters once each gate is printed on its own.
        $paymentCompleted = $enrollment->enrollmentStatus === EnrollmentStatus::Paid
            || ($assessment !== null && $assessment->remainingBalance <= 0)
            || $enrollment->payments->contains(fn ($payment) => $payment->paymentStatus === PaymentStatus::Paid);

        $nextPendingOffice = $enrollment->enrollmentworkflow
            ?->workflowsteps
            ->sortBy('stepOrder')
            ->first(fn ($step) => $step->stepStatus === WorkflowStepStatus::Pending)
            ?->officeId;

        return [
            'evaluation_signed' => (bool) $enrollment->evaluatedBy,
            'assessment_completed' => $assessment !== null,
            'payment_completed' => $paymentCompleted,
            'clearance_verified' => $this->checkClearance($enrollment, $openPeriod),
            'registrarApprovalPending' => $nextPendingOffice === OfficeId::Registrar->value,
            // Concerns #28/#32: the two checks this desk was meant to make and
            // did not — that the applicant's own required documents were actually
            // verified, and that the load being approved is one the student is
            // entitled to take.
            'documents_verified' => EnrollmentReadiness::documentsVerified($enrollment),
            'prerequisites_met' => EnrollmentReadiness::prerequisitesMet($enrollment),
        ];
    }

    /**
     * Check clearance for continuing students.
     */
    private function checkClearance(Enrollments $enrollment, ?Clearanceperiods $openPeriod = null): bool
    {
        if (! in_array($enrollment->studentType->value, ['continuing', 'shifter'], true)) {
            return true; // First-year and transferee don't need clearance
        }

        // The whole queue shares one open period, so the list resolves it once
        // and hands it down instead of asking per row.
        $openPeriod ??= Clearanceperiods::where('periodStatus', ClearancePeriodStatus::Open)->first();
        if (! $openPeriod) {
            return true; // No open period
        }

        $clearance = Studentclearances::where('studentId', $enrollment->studentId)
            ->where('clearancePeriodId', $openPeriod->clearancePeriodId)
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
        $this->authorize('registrar.approve', $enrollment);

        // The standing is the Registrar's to finalize. Whatever the evaluating
        // department recorded only becomes the official label once it is
        // confirmed here, so approval cannot be granted while it is unstated —
        // and no document that prints it is ever showing a default nobody decided.
        $validated = $request->validate([
            'academicStanding' => ['required', 'in:'.implode(',', array_column(AcademicStanding::cases(), 'value'))],
        ]);

        // Validate prerequisites — the identical five gates the desk displays,
        // read from one method so the two cannot drift apart.
        $checklist = $this->checklist($enrollment);

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

        DB::transaction(function () use ($enrollment, $enrollmentType, $validated) {
            // Confirm enrolled subjects
            $enrollment->enrolledSubjects()
                ->where('status', EnrolledSubjectStatus::Proposed)
                ->update(['status' => EnrolledSubjectStatus::Confirmed]);

            // Transition to enrolled
            $this->stateMachine->transition($enrollment, EnrollmentStatus::Enrolled, Auth::user(), 'Registrar approved enrollment');

            $enrollment->update([
                'enrollmentType' => $enrollmentType,
                'academicStanding' => AcademicStanding::from($validated['academicStanding']),
                'registrarProcessedBy' => Auth::user()->userId,
                'enrolledDate' => now(),
            ]);

            // Sign workflow step 5 (Registrar Approval)
            $workflow = $enrollment->enrollmentworkflow;
            if ($workflow) {
                $this->workflowService->signStepByOffice($workflow, OfficeId::Registrar->value, Auth::user());
            }
        });

        return redirect()->route('registrar.index')->with('success', 'Enrollment approved successfully.');
    }

    /**
     * Return the paid enrollment back to Department Evaluation (item 8).
     * The registrar must state why — the reason rides on the enrollment
     * (shown to the evaluating department) and mirrors into
     * enrollmentstatushistory via the state machine's remarks.
     */
    public function returnToEvaluation(Request $request, Enrollments $enrollment): RedirectResponse
    {
        $this->authorize('registrar.approve', $enrollment);

        $validated = $request->validate([
            'returnReason' => 'required|string|min:10|max:500',
        ]);

        if ($enrollment->enrollmentStatus !== EnrollmentStatus::Paid) {
            return back()->withErrors(['validation' => 'Only paid enrollments can be returned to Department Evaluation.']);
        }

        DB::transaction(function () use ($enrollment, $validated) {
            $this->stateMachine->transition(
                $enrollment,
                EnrollmentStatus::ReturnedToEvaluation,
                Auth::user(),
                'Returned to Department Evaluation: '.$validated['returnReason']
            );

            $enrollment->update(['returnReason' => $validated['returnReason']]);
        });

        return redirect()->route('registrar.index')->with('success', 'Enrollment returned to Department Evaluation.');
    }

    /**
     * Print enrollment certificate.
     */
    public function printCertificate(Enrollments $enrollment, PrintService $printService): Response
    {
        $this->authorize('registrar.printCertificate', $enrollment);

        $enrollment->load([
            'student', 'course', 'major', 'term.academicYear',
            'enrolledSubjects.subject', 'registrarProcessedByUser',
        ]);

        // Log print
        $printLog = $printService->recordIssue($enrollment->enrollmentId, DocumentType::Certificate, Auth::user()->userId);

        return Inertia::render('Registrar/PrintCertificate', [
            'enrollment' => $enrollment,
            'documentNumber' => $printLog->documentNumber,
            'issuedDate' => $enrollment->formIssuedDate?->toDateString() ?? now()->toDateString(),
        ]);
    }

    /**
     * Print class cards (one per confirmed subject).
     */
    public function printClassCards(Enrollments $enrollment, PrintService $printService): Response
    {
        $this->authorize('registrar.printClassCards', $enrollment);

        $enrollment->load([
            'student', 'course', 'major', 'term.academicYear',
            'enrolledSubjects.subject',
            'enrolledSubjects.schedule.room',
            'enrolledSubjects.schedule.instructor',
            'enrolledSubjects.schedule.meetings',
            'registrarProcessedByUser',
        ]);

        // One log row per card the screen actually prints.
        $printService->cardSubjects($enrollment)->each(
            fn () => $printService->recordIssue(
                $enrollment->enrollmentId,
                DocumentType::ClassCard,
                Auth::user()->userId
            )
        );

        return Inertia::render('Registrar/PrintClassCards', [
            'enrollment' => $enrollment,
        ]);
    }

    /**
     * Print subject load.
     */
    public function printSubjectLoad(Enrollments $enrollment, PrintService $printService): Response
    {
        $this->authorize('registrar.printSubjectLoad', $enrollment);

        $enrollment->load([
            'student', 'course', 'major', 'term.academicYear',
            'enrolledSubjects.subject',
            'enrolledSubjects.schedule.room',
            'enrolledSubjects.schedule.instructor',
            'enrolledSubjects.schedule.meetings',
            'registrarProcessedByUser',
        ]);

        $printService->recordIssue($enrollment->enrollmentId, DocumentType::SubjectLoad, Auth::user()->userId);

        return Inertia::render('Registrar/PrintSubjectLoad', [
            'enrollment' => $enrollment,
        ]);
    }

    /**
     * Download the enrollment certificate as a PDF. A saved copy is an issued copy,
     * so it writes its own print-log row like the print screen does.
     */
    public function downloadCertificate(Enrollments $enrollment, PrintService $printService): BinaryFileResponse
    {
        $this->authorize('registrar.printCertificate', $enrollment);

        return $printService
            ->printEnrollmentCertificate($enrollment, Auth::user()->userId)
            ->asDownload("certificate-{$enrollment->student?->schoolIdNumber}-{$enrollment->enrollmentId}.pdf");
    }

    /**
     * Download one class card as a PDF.
     */
    public function downloadClassCard(
        Enrollments $enrollment,
        Enrolledsubjects $enrolledSubject,
        PrintService $printService
    ): BinaryFileResponse {
        $this->authorize('registrar.printClassCards', $enrollment);

        abort_unless($enrolledSubject->enrollmentId === $enrollment->enrollmentId, 404);

        // Only a subject that actually earns a card may be downloaded as one: a
        // dropped or still-proposed enrolment has no card, and printing one would
        // register a subject the student is not carrying.
        abort_unless(
            $printService->cardSubjects($enrollment)
                ->contains(fn (Enrolledsubjects $card) => $card->enrolledSubjectId === $enrolledSubject->enrolledSubjectId),
            404
        );

        return $printService
            ->printClassCard($enrollment, $enrolledSubject, Auth::user()->userId)
            ->asDownload("class-card-{$enrollment->student?->schoolIdNumber}-{$enrolledSubject->subject?->subjectCode}.pdf");
    }

    /**
     * Download the subject load as a PDF.
     */
    public function downloadSubjectLoad(Enrollments $enrollment, PrintService $printService): BinaryFileResponse
    {
        $this->authorize('registrar.printSubjectLoad', $enrollment);

        return $printService
            ->printSubjectLoad($enrollment, Auth::user()->userId)
            ->asDownload("subject-load-{$enrollment->student?->schoolIdNumber}-{$enrollment->enrollmentId}.pdf");
    }
}
