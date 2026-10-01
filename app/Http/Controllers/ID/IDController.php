<?php

namespace App\Http\Controllers\ID;

use App\Enums\EnrollmentStatus;
use App\Enums\IdRequestReason;
use App\Enums\IdRequestStatus;
use App\Enums\OfficeId;
use App\Enums\WorkflowStepStatus;
use App\Http\Controllers\Controller;
use App\Models\Enrollments;
use App\Models\Idrequests;
use App\Services\WorkflowService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $base = Enrollments::query()
            ->where('enrollmentStatus', EnrollmentStatus::Enrolled)
            ->whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q
                ->where('stepStatus', WorkflowStepStatus::Pending->value)
                ->where('officeId', OfficeId::IdOffice->value)
                ->whereRaw('stepOrder = (SELECT MIN(ws.stepOrder) FROM workflowsteps ws WHERE ws.workflowId = workflowsteps.workflowId AND ws.stepStatus = ?)', ['pending'])
            )
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)));

        $enrollments = (clone $base)
            ->with(['student', 'course', 'term', 'idrequests', 'enrollmentworkflow'])
            ->orderByDesc('enrollmentId')
            ->paginate(20)->withQueryString();

        // ID-request status counts across the whole filtered set — first
        // request per enrollment, matching what the table shows (audit 2026-09-25).
        $idStats = (clone $base)
            ->join('idrequests', fn ($join) => $join
                ->on('idrequests.enrollmentId', '=', 'enrollments.enrollmentId')
                ->whereRaw('idrequests.idrequestId = (SELECT MIN(ir2.idrequestId) FROM idrequests ir2 WHERE ir2.enrollmentId = enrollments.enrollmentId)'))
            ->selectRaw('idrequests.status as status, count(*) as aggregate')
            ->groupBy('idrequests.status')
            ->pluck('aggregate', 'status');

        return Inertia::render('ID/Index', [
            'enrollments' => $enrollments,
            'stats' => [
                'pending' => (int) ($idStats[IdRequestStatus::Pending->value] ?? 0),
                'validated' => (int) ($idStats[IdRequestStatus::Validated->value] ?? 0),
            ],
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show ID validation desk.
     * Phase 8: ID request, photo, emergency contact, blood type
     */
    public function show(Enrollments $enrollment): Response
    {
        $this->authorize('id.viewAtDesk', $enrollment);

        $enrollment->load(['student', 'course', 'term', 'idrequests.validatedBy', 'enrollmentworkflow.workflowsteps.office', 'enrollmentworkflow.workflowsteps.signedBy']);

        $idRequest = $enrollment->idrequests->first();

        return Inertia::render('ID/Show', [
            'enrollment' => $enrollment,
            'idRequest' => $idRequest,
            'requestReasons' => collect(IdRequestReason::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => match ($c) {
                    IdRequestReason::NewStudent => 'New Student',
                    IdRequestReason::Shifted => 'Shifted Program',
                    IdRequestReason::Lost => 'Lost Replacement',
                    IdRequestReason::Replaced => 'Damaged Replacement',
                    IdRequestReason::Renewed => 'Annual Renewal',
                },
            ])->values(),
        ]);
    }

    /**
     * Create ID request.
     */
    public function create(Request $request, Enrollments $enrollment): RedirectResponse
    {
        $this->authorize('id.create', $enrollment);

        $validated = $request->validate([
            'requestReason' => 'required|in:newStudent,lost,replaced,renewed,shifted',
            'emergencyContactName' => 'required|string|max:255',
            'emergencyContactNumber' => 'required|string|max:20',
            'bloodType' => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'cardPhotoPath' => 'nullable|string|max:500',
        ]);

        $idRequest = Idrequests::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'requestReason' => $validated['requestReason'],
            'emergencyContactName' => $validated['emergencyContactName'],
            'emergencyContactNumber' => $validated['emergencyContactNumber'],
            'bloodType' => $validated['bloodType'],
            'cardPhotoPath' => $validated['cardPhotoPath'] ?? null,
            'requestDate' => now(),
            'status' => IdRequestStatus::Pending,
        ]);

        return redirect()->route('id.show', $enrollment)->with('success', 'ID request created.');
    }

    /**
     * Attach the captured face photo to the ID request (validation prep).
     */
    public function attachPhoto(Request $request, Idrequests $idRequest): RedirectResponse
    {
        $this->authorize('id.photo.attach', $idRequest);

        $validated = $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $disk = config('filesystems.default', 'public');
        $path = $request->file('photo')->store('id-photos', $disk);

        $idRequest->update(['cardPhotoPath' => $path]);

        return back()->with('success', 'Face photo attached to the ID request.');
    }

    /**
     * Stream the captured face photo back to the desk.
     *
     * The stored path is relative to the private default disk, which the
     * framework only serves through a signed URL, so an <img> pointed at it
     * cannot load. This answers on the same policy as the request screen that
     * shows the photo.
     */
    public function photo(Idrequests $idRequest): StreamedResponse
    {
        $this->authorize('view', $idRequest);

        $disk = Storage::disk(config('filesystems.default'));
        abort_unless(filled($idRequest->cardPhotoPath) && $disk->exists($idRequest->cardPhotoPath), 404, 'No face photo is stored for this request.');

        return $disk->response($idRequest->cardPhotoPath);
    }

    /**
     * Validate ID: mark the request validated and sign the ID Office
     * workflow step.
     */
    public function validate(Request $request, Idrequests $idRequest): RedirectResponse
    {
        $this->authorize('id.validateRequest', $idRequest);

        DB::transaction(function () use ($idRequest) {
            $idRequest->update([
                'status' => IdRequestStatus::Validated,
                'validatedBy' => Auth::user()->userId,
                'validatedDate' => now(),
            ]);

            // Sign the ID Office workflow step
            $workflow = $idRequest->enrollment->enrollmentworkflow;
            if ($workflow) {
                $this->workflowService->signStepByOffice($workflow, OfficeId::IdOffice->value, Auth::user());
            }
        });

        return back()->with('success', 'ID validated successfully.');
    }
}
