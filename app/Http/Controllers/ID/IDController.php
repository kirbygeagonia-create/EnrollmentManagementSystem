<?php

namespace App\Http\Controllers\ID;

use App\Enums\EnrollmentStatus;
use App\Enums\IdRequestStatus;
use App\Enums\IdValidationStatus;
use App\Http\Controllers\Controller;
use App\Models\Enrollments;
use App\Models\Idrequests;
use App\Models\Studentids;
use App\Services\WorkflowService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class IDController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private WorkflowService $workflowService
    ) {}

    /**
     * Display ID request queue.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Idrequests::class);

        $query = Enrollments::with(['student', 'course', 'term', 'idrequests', 'enrollmentworkflow'])
            ->where('enrollmentStatus', EnrollmentStatus::Enrolled)
            ->whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q
                ->where('stepStatus', 'pending')
                ->where('officeId', 22)
                ->whereRaw('stepOrder = (SELECT MIN(ws.stepOrder) FROM workflowsteps ws WHERE ws.workflowId = workflowsteps.workflowId AND ws.stepStatus = ?)', ['pending'])
            )
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->orderByDesc('enrollmentId');

        $enrollments = $query->paginate(20)->withQueryString();

        return Inertia::render('ID/Index', [
            'enrollments' => $enrollments,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show ID request form.
     * Phase 8: ID request, photo, emergency contact, blood type
     */
    public function show(Enrollments $enrollment): Response
    {
        $this->authorize('id.view', $enrollment);

        $enrollment->load(['student', 'course', 'term', 'idrequests', 'enrollmentworkflow.workflowsteps']);

        $idRequest = $enrollment->idrequests->first();
        // studentids is a HasOne — returns the single model directly (calling
        // ->first() on a model would forward to a fresh query builder and
        // return the first row of the whole table).
        $studentId = $idRequest?->studentids;

        return Inertia::render('ID/Show', [
            'enrollment' => $enrollment,
            'idRequest' => $idRequest,
            'studentId' => $studentId,
            'requestReasons' => collect(IdRequestStatus::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
        ]);
    }

    /**
     * Create ID request.
     */
    public function create(Request $request, Enrollments $enrollment): RedirectResponse
    {
        $this->authorize('id.create', $enrollment);

        $validated = $request->validate([
            'requestReason' => 'required|in:newStudent,lost,renewal,shifted',
            'emergencyContactName' => 'required|string|max:255',
            'emergencyContactNumber' => 'required|string|max:20',
            'bloodType' => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'cardPhotoPath' => 'nullable|string|max:500',
            'producedByVendor' => 'nullable|string|max:255',
        ]);

        $idRequest = Idrequests::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'requestReason' => $validated['requestReason'],
            'emergencyContactName' => $validated['emergencyContactName'],
            'emergencyContactNumber' => $validated['emergencyContactNumber'],
            'bloodType' => $validated['bloodType'],
            'cardPhotoPath' => $validated['cardPhotoPath'] ?? null,
            'producedByVendor' => $validated['producedByVendor'] ?? null,
            'requestDate' => now(),
            'status' => IdRequestStatus::Pending,
        ]);

        return redirect()->route('id.show', $enrollment)->with('success', 'ID request created.');
    }

    /**
     * Produce ID card.
     */
    public function produceCard(Request $request, Idrequests $idRequest): RedirectResponse
    {
        $this->authorize('produceCard', $idRequest);

        $validated = $request->validate([
            'qrCode' => 'required|string|max:100|unique:studentids,qrCode',
            'securityPhotoPath' => 'nullable|string|max:500',
        ]);

        $studentId = Studentids::create([
            'studentId' => $idRequest->enrollment->studentId,
            'idRequestId' => $idRequest->idRequestId,
            'qrCode' => $validated['qrCode'],
            'issueDate' => now(),
            'validationStatus' => IdValidationStatus::PendingValidation,
            'securityPhotoPath' => $validated['securityPhotoPath'] ?? null,
        ]);

        $idRequest->update(['status' => IdRequestStatus::CardProduced]);

        return back()->with('success', 'ID card produced.');
    }

    /**
     * Validate ID (QR code scan).
     */
    public function validate(Request $request, Studentids $studentId): RedirectResponse
    {
        $this->authorize('id.validateCard', $studentId);

        $studentId->update([
            'validationStatus' => IdValidationStatus::Active,
            'validatedBy' => Auth::user()->userId,
            'validatedDate' => now(),
        ]);

        // Sign workflow step 8 (ID Office)
        $workflow = $studentId->idRequest?->enrollment?->enrollmentworkflow;
        if ($workflow) {
            $this->workflowService->signStepByOffice($workflow, 22, Auth::user());
        }

        return back()->with('success', 'ID validated successfully.');
    }

    /**
     * Release ID to student.
     */
    public function release(Request $request, Studentids $studentId): RedirectResponse
    {
        $this->authorize('id.releaseCard', $studentId);

        $studentId->update([
            'validationStatus' => IdValidationStatus::Active,
        ]);

        return back()->with('success', 'ID released to student.');
    }
}
