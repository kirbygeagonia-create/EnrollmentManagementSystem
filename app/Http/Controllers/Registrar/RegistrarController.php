<?php

namespace App\Http\Controllers\Registrar;

use App\Enums\AcademicStanding;
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
        $openPeriod = Clearanceperiods::accepting()->first();
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
            // The same sentences approve() refuses with, so the page explains the red box
            // before the desk presses the button and is told.
            'blockingReasons' => $this->blockingReasons($enrollment),
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
            'clearance_verified' => EnrollmentReadiness::clearanceVerdict($enrollment, $openPeriod)['passed'],
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
     * The sentence a desk owes the student for each gate this record cannot clear.
     *
     * The checklist answers "may I approve"; this answers "what has to happen first".
     * The refusal and the record page read the same map, so the desk never has to guess
     * which of seven red boxes stopped it — and the clearance sentence comes from the
     * one method that decided it, so the explanation cannot drift from the rule.
     *
     * @return array<string, string>
     */
    private function blockingReasons(Enrollments $enrollment, ?Clearanceperiods $openPeriod = null): array
    {
        $reasons = [];

        if (! $enrollment->evaluatedBy) {
            $reasons['evaluation_signed'] = 'Department Evaluation has not signed this load.';
        }

        if (! EnrollmentReadiness::documentsVerified($enrollment)) {
            $reasons['documents_verified'] = 'An admission document the applicant was required to submit is still unverified.';
        }

        if (! EnrollmentReadiness::prerequisitesMet($enrollment)) {
            $reasons['prerequisites_met'] = 'A subject on the confirmed load sits behind a prerequisite the student has not passed.';
        }

        if (! $enrollment->studentassessments) {
            $reasons['assessment_completed'] = 'Assessment has not costed this load yet.';
        }

        $checklist = $this->checklist($enrollment, $openPeriod);

        if (! $checklist['payment_completed']) {
            $reasons['payment_completed'] = 'Accounting has not settled the assessed balance.';
        }

        $clearance = EnrollmentReadiness::clearanceVerdict($enrollment, $openPeriod);

        if (! $clearance['passed']) {
            $reasons['clearance_verified'] = $clearance['reason'];
        }

        if (! $checklist['registrarApprovalPending']) {
            $reasons['registrarApprovalPending'] = 'A workflow box ahead of the Registrar is still unsigned.';
        }

        return $reasons;
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

        // Validate prerequisites — the identical gates the desk displays, read from one
        // method so the two cannot drift apart. The refusal names what is outstanding
        // rather than leaving the desk to work out which of seven boxes is red.
        $outstanding = $this->blockingReasons($enrollment);

        if ($outstanding !== []) {
            return back()->withErrors([
                'validation' => 'Cannot approve — '.implode('; ', array_values($outstanding)).'.',
            ]);
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
     * Drop an enrollment, with the reason the record has to carry (ruling 17).
     *
     * The reason goes in two places on purpose: beside the record, where the desk and any
     * later reader of this enrollment see it without asking, and in the status history the
     * state machine writes, which pairs it with who dropped the record and when.
     *
     * The subject rows retire with it. A student who has left the term must stop holding a
     * seat in a block — Blocking counts the distinct students who are not dropped — and
     * releasing that seat is what lets the same student be re-enrolled in the same term.
     * A drop is terminal: the record is never un-dropped, the student comes back on a new
     * one.
     */
    public function drop(Request $request, Enrollments $enrollment): RedirectResponse
    {
        $this->authorize('registrar.drop', $enrollment);

        $validated = $request->validate([
            'dropReason' => 'required|string|min:10|max:500',
        ]);

        DB::transaction(function () use ($enrollment, $validated) {
            $this->stateMachine->transition(
                $enrollment,
                EnrollmentStatus::Dropped,
                Auth::user(),
                'Dropped by the Registrar: '.$validated['dropReason']
            );

            $enrollment->enrolledSubjects()
                ->whereIn('status', [
                    EnrolledSubjectStatus::Proposed->value,
                    EnrolledSubjectStatus::Confirmed->value,
                ])
                ->update(['status' => EnrolledSubjectStatus::Dropped->value]);

            $enrollment->update(['dropReason' => $validated['dropReason']]);
        });

        return redirect()->route('registrar.index')
            ->with('success', 'Enrollment dropped. The seat in this term is released, so the student may be re-enrolled.');
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
        $printLog = $printService->recordIssue($enrollment->enrollmentId, DocumentType::Certificate, Auth::user()->userId, $enrollment->studentId);

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
                Auth::user()->userId,
                $enrollment->studentId
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

        $printService->recordIssue($enrollment->enrollmentId, DocumentType::SubjectLoad, Auth::user()->userId, $enrollment->studentId);

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
